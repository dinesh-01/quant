<?php

namespace App\Actions\TestSpecification;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\TestCaseExecutionType;
use App\Models\TestCaseStep;
use App\Models\TestCaseVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Appends a step to an open test case version.
 */
final class CreateTestCaseStep
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{actions?: string|null, expected_results?: string|null, execution_type?: TestCaseExecutionType}  $attributes
     *
     * @throws AuthorizationException
     * @throws ValidationException when the version is frozen
     */
    public function __invoke(User $user, TestCaseVersion $version, array $attributes): TestCaseStep
    {
        $version->loadMissing('testCase.testProject');

        Gate::forUser($user)->authorize(Ability::ManageTestCases->value, $version->testCase->testProject);

        if ($version->isFrozen()) {
            throw ValidationException::withMessages([
                'step' => 'This test case version is frozen. Reopen it or create a new version to add steps.',
            ]);
        }

        $step = new TestCaseStep;
        $step->fill($attributes);
        $step->test_case_version_id = $version->getKey();
        $step->sort_order = (int) $version->steps()->max('sort_order') + 1;
        $step->save();

        $this->audit->record(AuditAction::TestCaseStepCreated, $user, $version->testCase, [
            'version' => $version->version,
            'step' => $step->sort_order,
        ]);

        return $step;
    }
}
