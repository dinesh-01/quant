<?php

namespace App\Actions\TestSpecification;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TestCaseScriptLink;
use App\Models\TestCaseVersion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Pins an automation script to a test case version.
 *
 * `manage_test_cases` is the grant, matching legacy `mgt_modify_tc`. The
 * tracker config is a separate catalogue.
 */
final class LinkAutomationScript
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{project_key: string, repository: string, path: string, branch: string|null, commit: string|null}  $attributes
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $user, TestCaseVersion $version, array $attributes): TestCaseScriptLink
    {
        $version->loadMissing('testCase.testProject');

        Gate::forUser($user)->authorize(Ability::ManageTestCases->value, $version->testCase->testProject);

        $already = TestCaseScriptLink::query()
            ->where('test_case_version_id', $version->getKey())
            ->where('project_key', $attributes['project_key'])
            ->where('repository', $attributes['repository'])
            ->where('path', $attributes['path'])
            ->exists();

        if ($already) {
            throw ValidationException::withMessages([
                'path' => 'That script is already linked to this version.',
            ]);
        }

        $link = new TestCaseScriptLink;
        $link->fill($attributes);
        $link->testCaseVersion()->associate($version);
        $link->save();

        $this->audit->record(AuditAction::AutomationScriptLinked, $user, $version->testCase, [
            'version' => $version->version,
            'repository' => $link->repository,
            'path' => $link->path,
        ]);

        return $link;
    }
}
