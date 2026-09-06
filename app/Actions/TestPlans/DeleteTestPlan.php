<?php

namespace App\Actions\TestPlans;

use App\Actions\Attachments\PurgeAttachments;
use App\Actions\Audit\AuditLogger;
use App\Actions\CustomFields\PurgeCustomFieldValues;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TestPlan;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Deletes a test plan.
 *
 * This loses the plan's role assignments, builds, linked case versions,
 * tester assignments and execution history. Execution history is the least
 * reproducible data in the application, since it records what a person
 * observed at a point in time. Closing the plan is the reversible alternative.
 *
 * The typed-name confirmation in TestPlanDeleteRequest is therefore in place
 * before there is anything irreplaceable to lose, rather than being added once
 * someone has already lost it.
 */
final class DeleteTestPlan
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PurgeAttachments $purgeAttachments,
        private readonly PurgeCustomFieldValues $purgeCustomFieldValues,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, TestPlan $plan): void
    {
        Gate::forUser($user)->authorize(Ability::CreateTestPlans->value, $plan);

        DB::transaction(function () use ($user, $plan): void {
            /* Polymorphic, so no foreign key cascade reaches these. */
            $attachments = $this->purgeAttachments->forTestPlan($plan);
            $customFieldValues = $this->purgeCustomFieldValues->forTestPlan($plan);

            $this->audit->record(AuditAction::TestPlanDeleted, $user, $plan, [
                'name' => $plan->name,
                'test_project_id' => $plan->test_project_id,
                'attachments' => $attachments,
                'custom_field_values' => $customFieldValues,
                'builds' => $plan->builds()->count(),
                'items' => $plan->items()->count(),
                'tester_assignments' => $plan->testerAssignments()->count(),
                'executions' => $plan->executions()->count(),
            ]);

            $plan->delete();
        });
    }
}
