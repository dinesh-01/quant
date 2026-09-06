<?php

namespace App\Actions\TestSpecification;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TestCaseStep;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Removes a step from an open test case version and closes the gap.
 *
 * The remaining steps are renumbered from 1 in the same transaction. A step's
 * `sort_order` is what the UI shows as its number, so leaving a hole would
 * display steps numbered 1, 3, 4. Legacy left the holes.
 */
final class DeleteTestCaseStep
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException when the owning version is frozen
     */
    public function __invoke(User $user, TestCaseStep $step): void
    {
        $step->loadMissing('testCaseVersion.testCase.testProject');
        $version = $step->testCaseVersion;

        Gate::forUser($user)->authorize(Ability::ManageTestCases->value, $version->testCase->testProject);

        if ($version->isFrozen()) {
            throw ValidationException::withMessages([
                'step' => 'This test case version is frozen. Reopen it or create a new version to remove steps.',
            ]);
        }

        DB::transaction(function () use ($user, $step, $version): void {
            $this->audit->record(AuditAction::TestCaseStepDeleted, $user, $version->testCase, [
                'version' => $version->version,
                'step' => $step->sort_order,
            ]);

            $step->delete();

            $remaining = $version->steps()->orderBy('sort_order')->orderBy('id')->pluck('id');

            foreach ($remaining as $position => $id) {
                TestCaseStep::query()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });
    }
}
