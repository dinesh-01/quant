<?php

namespace App\Actions\TestPlanItems;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TestPlanItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Removes a pinned version from a plan.
 *
 * An item that already has a completed run also needs
 * `unlink_executed_test_cases`. Drafts are not history and do not raise the
 * bar. Cascading the delete would otherwise silently take observed results.
 */
final class UnlinkTestPlanItem
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, TestPlanItem $item): void
    {
        $item->loadMissing('testPlan', 'testCaseVersion.testCase', 'platform');

        Gate::forUser($user)->authorize(Ability::PlanTestCases->value, $item->testPlan);

        if ($item->executions()->where('is_draft', false)->exists()) {
            Gate::forUser($user)->authorize(Ability::UnlinkExecutedTestCases->value, $item->testPlan);
        }

        DB::transaction(function () use ($user, $item): void {
            $this->audit->record(AuditAction::TestPlanItemUnlinked, $user, $item->testPlan, [
                'name' => $item->testPlan->name,
                'test_case_id' => $item->testCaseVersion->test_case_id,
                'test_case_name' => $item->testCaseVersion->testCase->name,
                'version' => $item->testCaseVersion->version,
                'platform' => $item->platform?->name,
            ]);

            $item->delete();
        });
    }
}
