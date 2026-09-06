<?php

namespace Tests\Feature\TestSpecification;

use App\Enums\Ability;
use App\Enums\TestCaseExecutionType;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestCaseStep;
use App\Models\TestCaseVersion;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestCaseStepEndpointsTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_steps_are_appended_in_order()
    {
        [$user, $version] = $this->versionFor(Ability::ManageTestCases);

        $this->actingAs($user)
            ->post(route('test-case-steps.store', $version), $this->step(['actions' => 'First']))
            ->assertSessionHasNoErrors();

        $this->actingAs($user)->post(route('test-case-steps.store', $version), $this->step(['actions' => 'Second']));

        $steps = $version->refresh()->steps;

        $this->assertSame(['First', 'Second'], $steps->pluck('actions')->all());
        $this->assertSame([1, 2], $steps->pluck('sort_order')->all());
    }

    public function test_adding_a_step_requires_managing_test_cases()
    {
        [$user, $version] = $this->versionFor(Ability::ViewTestCases);

        $this->actingAs($user)
            ->post(route('test-case-steps.store', $version), $this->step())
            ->assertForbidden();

        $this->assertSame(0, $version->steps()->count());
    }

    public function test_a_step_can_be_edited()
    {
        [$user, $version] = $this->versionFor(Ability::ManageTestCases);
        $step = TestCaseStep::factory()->for($version, 'testCaseVersion')->at(1)->create();

        $this->actingAs($user)
            ->put(route('test-case-steps.update', $step), $this->step([
                'actions' => 'Rewritten',
                'execution_type' => TestCaseExecutionType::Automated->value,
            ]))
            ->assertSessionHasNoErrors();

        $step->refresh();

        $this->assertSame('Rewritten', $step->actions);
        $this->assertSame(TestCaseExecutionType::Automated, $step->execution_type);
    }

    public function test_a_step_cannot_be_repositioned_through_the_edit_endpoint()
    {
        [$user, $version] = $this->versionFor(Ability::ManageTestCases);
        $step = TestCaseStep::factory()->for($version, 'testCaseVersion')->at(1)->create();

        $this->actingAs($user)->put(
            route('test-case-steps.update', $step),
            $this->step(['sort_order' => 9]),
        );

        $this->assertSame(1, $step->refresh()->sort_order);
    }

    public function test_deleting_a_step_closes_the_numbering_gap()
    {
        [$user, $version] = $this->versionFor(Ability::ManageTestCases);
        $first = TestCaseStep::factory()->for($version, 'testCaseVersion')->at(1)->create();
        $second = TestCaseStep::factory()->for($version, 'testCaseVersion')->at(2)->create();
        $third = TestCaseStep::factory()->for($version, 'testCaseVersion')->at(3)->create();

        $this->actingAs($user)
            ->delete(route('test-case-steps.destroy', $second))
            ->assertSessionHasNoErrors();

        $steps = $version->refresh()->steps;

        $this->assertSame([$first->id, $third->id], $steps->pluck('id')->all());
        $this->assertSame([1, 2], $steps->pluck('sort_order')->all());
    }

    public function test_steps_can_be_reordered()
    {
        [$user, $version] = $this->versionFor(Ability::ManageTestCases);
        $first = TestCaseStep::factory()->for($version, 'testCaseVersion')->at(1)->create();
        $second = TestCaseStep::factory()->for($version, 'testCaseVersion')->at(2)->create();

        $this->actingAs($user)
            ->post(route('test-case-steps.reorder', $version), ['order' => [$second->id, $first->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame([$second->id, $first->id], $version->refresh()->steps->pluck('id')->all());
    }

    public function test_reordering_rejects_a_step_from_another_version()
    {
        [$user, $version] = $this->versionFor(Ability::ManageTestCases);
        $mine = TestCaseStep::factory()->for($version, 'testCaseVersion')->at(1)->create();
        $theirs = TestCaseStep::factory()->create();

        $this->actingAs($user)
            ->post(route('test-case-steps.reorder', $version), ['order' => [$mine->id, $theirs->id]])
            ->assertSessionHasErrors('order.1');
    }

    public function test_a_frozen_version_refuses_every_step_change()
    {
        [$user, $version] = $this->versionFor(Ability::ManageTestCases);
        $step = TestCaseStep::factory()->for($version, 'testCaseVersion')->at(1)->create();
        $version->is_open = false;
        $version->save();

        $this->actingAs($user)
            ->post(route('test-case-steps.store', $version), $this->step())
            ->assertSessionHasErrors('step');

        $this->actingAs($user)
            ->put(route('test-case-steps.update', $step), $this->step(['actions' => 'Sneaky']))
            ->assertSessionHasErrors('step');

        $this->actingAs($user)
            ->delete(route('test-case-steps.destroy', $step))
            ->assertSessionHasErrors('step');

        $this->assertSame(1, $version->steps()->count());
        $this->assertNotSame('Sneaky', $step->refresh()->actions);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function step(array $overrides = []): array
    {
        return [
            'actions' => 'Do the thing',
            'expected_results' => 'The thing happens',
            'execution_type' => TestCaseExecutionType::Manual->value,
            ...$overrides,
        ];
    }

    /**
     * @return array{User, TestCaseVersion}
     */
    private function versionFor(Ability ...$abilities): array
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, ...$abilities);
        $case = TestCaseModel::factory()->create(['test_project_id' => $project->id]);
        $version = TestCaseVersion::factory()->for($case, 'testCase')->version(1)->create();

        return [$user, $version];
    }
}
