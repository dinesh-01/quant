<?php

namespace Tests\Feature\TestSpecification;

use App\Actions\TestSpecification\CreateTestCase;
use App\Actions\TestSpecification\CreateTestCaseStep;
use App\Actions\TestSpecification\CreateTestSuite;
use App\Actions\TestSpecification\ExpandGhostMarkup;
use App\Enums\Ability;
use App\Enums\TestCaseExecutionType;
use App\Models\TestProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GhostMarkupTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_ghost_markup_expands_to_the_referenced_step(): void
    {
        $project = TestProject::factory()->create(['prefix' => 'PAY']);
        $user = $this->userWhoCan($project, Ability::ManageTestCases, Ability::ViewTestCases);
        $suite = app(CreateTestSuite::class)($user, $project, null, [
            'name' => 'Shared',
        ]);
        $source = app(CreateTestCase::class)($user, $suite, [
            'name' => 'Sign in',
            'summary' => '<p>Open the app</p>',
        ]);
        app(CreateTestCaseStep::class)($user, $source->latestVersion, [
            'actions' => '<p>Enter the password</p>',
            'expected_results' => '<p>Home</p>',
            'execution_type' => TestCaseExecutionType::Manual,
        ]);

        $expanded = app(ExpandGhostMarkup::class)(
            '[ghost]"TestCase":"'.$source->fullExternalId().'","Version":"1","Step":"1"[/ghost]',
            $project,
        );

        $this->assertSame('<p>Enter the password</p>', $expanded);
    }

    public function test_unknown_ghost_markup_is_left_alone(): void
    {
        $project = TestProject::factory()->create(['prefix' => 'PAY']);

        $markup = '[ghost]"TestCase":"PAY-99","Version":"1"[/ghost]';

        $this->assertSame($markup, app(ExpandGhostMarkup::class)($markup, $project));
    }

    public function test_a_step_token_in_expected_results_uses_the_source_expected_results(): void
    {
        $project = TestProject::factory()->create(['prefix' => 'PAY']);
        $user = $this->userWhoCan($project, Ability::ManageTestCases, Ability::ViewTestCases);
        $suite = app(CreateTestSuite::class)($user, $project, null, [
            'name' => 'Shared',
        ]);
        $source = app(CreateTestCase::class)($user, $suite, [
            'name' => 'Sign in',
            'summary' => '<p>Open the app</p>',
        ]);
        app(CreateTestCaseStep::class)($user, $source->latestVersion, [
            'actions' => '<p>Enter the password</p>',
            'expected_results' => '<p>Home</p>',
            'execution_type' => TestCaseExecutionType::Manual,
        ]);

        $expanded = app(ExpandGhostMarkup::class)(
            '[ghost]"TestCase":"'.$source->fullExternalId().'","Version":"1","Step":"1"[/ghost]',
            $project,
            'expected_results',
        );

        $this->assertSame('<p>Home</p>', $expanded);
    }
}
