<?php

namespace App\Actions\Builds;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\Build;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Removes a build from a plan.
 *
 * A build that holds execution history is refused. Those rows are what a
 * person observed; closing the build is the reversible alternative.
 */
final class DeleteBuild
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $user, Build $build): void
    {
        $build->loadMissing('testPlan');

        Gate::forUser($user)->authorize(Ability::ManageBuilds->value, $build->testPlan);

        if ($build->executions()->exists()) {
            throw ValidationException::withMessages([
                'build' => 'This build has execution history. Close it instead of deleting it.',
            ]);
        }

        DB::transaction(function () use ($user, $build): void {
            $this->audit->record(AuditAction::BuildDeleted, $user, $build, [
                'name' => $build->name,
                'test_plan_id' => $build->test_plan_id,
            ]);

            $build->delete();
        });
    }
}
