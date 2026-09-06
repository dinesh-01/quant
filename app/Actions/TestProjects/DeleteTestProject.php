<?php

namespace App\Actions\TestProjects;

use App\Actions\Attachments\PurgeAttachments;
use App\Actions\Audit\AuditLogger;
use App\Actions\CustomFields\PurgeCustomFieldValues;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Deletes a test project and everything hanging off it.
 *
 * This is the widest destructive operation in the application: the foreign key
 * cascades take out every suite, nested suite, case, version and step, plus the
 * project's plans and its role assignments. It is why suite nesting is capped
 * at TestSuite::MAX_DEPTH — InnoDB abandons cascading after 15 levels, and a
 * project that cannot be deleted would have no remedy.
 *
 * Deactivating a project is the reversible alternative and is what should
 * normally be offered. The typed-name confirmation lives in
 * TestProjectDeleteRequest.
 */
final class DeleteTestProject
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PurgeAttachments $purgeAttachments,
        private readonly PurgeCustomFieldValues $purgeCustomFieldValues,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, TestProject $project): void
    {
        Gate::forUser($user)->authorize(Ability::ManageTestProjects->value);

        DB::transaction(function () use ($user, $project): void {
            /*
             * Attachments hang off a polymorphic pair, which cannot carry a
             * foreign key, so the cascade below does not reach them. Purged
             * first, while the ids they point at still exist to be found.
             */
            $attachments = $this->purgeAttachments->forProject($project);
            $customFieldValues = $this->purgeCustomFieldValues->forProject($project);

            /**
             * Recorded before the delete, with the counts of what the cascade
             * takes with it. This is the most destructive act in the
             * application and the trail is all that will be left of it.
             *
             * Audit rows pointing at the cascaded suites, cases and plans
             * survive — only `user_id` is a foreign key — so the history of
             * those records remains readable even though the subjects are gone.
             */
            $this->audit->record(AuditAction::TestProjectDeleted, $user, $project, [
                'name' => $project->name,
                'prefix' => $project->prefix,
                'test_suites' => $project->testSuites()->count(),
                'test_cases' => $project->testCases()->count(),
                'test_plans' => $project->testPlans()->count(),
                'attachments' => $attachments,
                'custom_field_values' => $customFieldValues,
            ]);

            $project->delete();
        });
    }
}
