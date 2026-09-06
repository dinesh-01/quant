<?php

namespace App\Actions\TestSpecification;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\TestCaseExecutionType;
use App\Models\TestCaseStep;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Edits a step of an open test case version.
 *
 * Repositioning is ReorderTestCaseSteps' job, so `sort_order` is not accepted
 * here.
 */
final class UpdateTestCaseStep
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{actions?: string|null, expected_results?: string|null, execution_type?: TestCaseExecutionType}  $attributes
     *
     * @throws AuthorizationException
     * @throws ValidationException when the owning version is frozen
     */
    public function __invoke(User $user, TestCaseStep $step, array $attributes): TestCaseStep
    {
        $step->loadMissing('testCaseVersion.testCase.testProject');
        $version = $step->testCaseVersion;

        Gate::forUser($user)->authorize(Ability::ManageTestCases->value, $version->testCase->testProject);

        if ($version->isFrozen()) {
            throw ValidationException::withMessages([
                'step' => 'This test case version is frozen. Reopen it or create a new version to edit its steps.',
            ]);
        }

        $step->fill(Arr::only($attributes, ['actions', 'expected_results', 'execution_type']));

        $properties = $this->audit->changes($step);

        $step->save();

        if ($properties !== []) {
            /**
             * Recorded even though the version already names its last updater:
             * a step's expected result is what a tester is judged against, so
             * a silent change to it is the one content edit worth being able
             * to attribute exactly.
             */
            $this->audit->record(AuditAction::TestCaseStepUpdated, $user, $version->testCase, [
                'version' => $version->version,
                'step' => $step->sort_order,
                ...$properties,
            ]);
        }

        return $step;
    }
}
