<?php

namespace Tests\Feature\TestSpecification;

use App\Enums\Ability;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestProject;
use App\Models\TestSuite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestSuiteEndpointsTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_a_suite_can_be_created_at_the_project_root()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::ManageTestCases);

        $response = $this->actingAs($user)->post(route('test-suites.store', $project), [
            'name' => 'Authentication',
            'description' => 'Everything about signing in',
        ]);

        $suite = TestSuite::query()->sole();

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('specification.suites.show', [$project, $suite]));

        $this->assertSame('Authentication', $suite->name);
        $this->assertNull($suite->parent_id);
        $this->assertSame($project->id, $suite->test_project_id);
    }

    public function test_a_suite_can_be_created_inside_another()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $parent = TestSuite::factory()->for($project)->create();

        $this->actingAs($user)
            ->post(route('test-suites.store', $project), ['name' => 'Nested', 'parent_id' => $parent->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($parent->id, TestSuite::query()->where('name', 'Nested')->sole()->parent_id);
    }

    public function test_two_root_suites_cannot_share_a_name()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        TestSuite::factory()->for($project)->create(['name' => 'Authentication']);

        $this->actingAs($user)
            ->post(route('test-suites.store', $project), ['name' => 'Authentication'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, TestSuite::query()->count());
    }

    public function test_two_suites_under_the_same_parent_cannot_share_a_name()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $parent = TestSuite::factory()->for($project)->create();
        TestSuite::factory()->childOf($parent)->create(['name' => 'Passwords']);

        $this->actingAs($user)
            ->post(route('test-suites.store', $project), ['name' => 'Passwords', 'parent_id' => $parent->id])
            ->assertSessionHasErrors('name');
    }

    public function test_the_same_name_is_allowed_under_different_parents()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $first = TestSuite::factory()->for($project)->create();
        $second = TestSuite::factory()->for($project)->create();
        TestSuite::factory()->childOf($first)->create(['name' => 'Passwords']);

        $this->actingAs($user)
            ->post(route('test-suites.store', $project), ['name' => 'Passwords', 'parent_id' => $second->id])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, TestSuite::query()->where('name', 'Passwords')->count());
    }

    public function test_a_root_suite_may_share_a_name_with_a_nested_one()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $parent = TestSuite::factory()->for($project)->create();
        TestSuite::factory()->childOf($parent)->create(['name' => 'Shared']);

        $this->actingAs($user)
            ->post(route('test-suites.store', $project), ['name' => 'Shared'])
            ->assertSessionHasNoErrors();
    }

    public function test_the_same_name_is_allowed_in_another_project()
    {
        $project = TestProject::factory()->create();
        $other = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        TestSuite::factory()->for($other)->create(['name' => 'Authentication']);

        $this->actingAs($user)
            ->post(route('test-suites.store', $project), ['name' => 'Authentication'])
            ->assertSessionHasNoErrors();
    }

    public function test_a_parent_from_another_project_is_rejected()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $foreign = TestSuite::factory()->create();

        $this->actingAs($user)
            ->post(route('test-suites.store', $project), ['name' => 'Nope', 'parent_id' => $foreign->id])
            ->assertSessionHasErrors('parent_id');
    }

    public function test_creating_a_suite_requires_managing_test_cases()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);

        $this->actingAs($user)
            ->post(route('test-suites.store', $project), ['name' => 'Nope'])
            ->assertForbidden();

        $this->assertSame(0, TestSuite::query()->count());
    }

    public function test_a_suite_can_be_renamed_keeping_its_own_name()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create(['name' => 'Authentication']);

        $this->actingAs($user)
            ->put(route('test-suites.update', $suite), ['name' => 'Authentication', 'description' => 'Updated'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Updated', $suite->refresh()->description);
    }

    public function test_an_emptied_description_is_stored_as_null()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create(['description' => 'Something']);

        $this->actingAs($user)
            ->put(route('test-suites.update', $suite), ['name' => $suite->name, 'description' => ''])
            ->assertSessionHasNoErrors();

        $this->assertNull($suite->refresh()->description);
    }

    public function test_renaming_onto_a_sibling_name_is_rejected()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        TestSuite::factory()->for($project)->create(['name' => 'Taken']);
        $suite = TestSuite::factory()->for($project)->create(['name' => 'Free']);

        $this->actingAs($user)
            ->put(route('test-suites.update', $suite), ['name' => 'Taken'])
            ->assertSessionHasErrors('name');
    }

    public function test_a_suite_is_deleted_with_everything_beneath_it()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $child = TestSuite::factory()->childOf($suite)->create();
        TestCaseModel::factory()->for($child, 'testSuite')->withVersion()->create();

        $this->actingAs($user)
            ->delete(route('test-suites.destroy', $suite))
            ->assertRedirect(route('specification.show', $project));

        $this->assertSame(0, TestSuite::query()->count());
        $this->assertSame(0, TestCaseModel::query()->count());
    }

    public function test_a_suite_can_be_moved_under_a_new_parent()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $target = TestSuite::factory()->for($project)->create();

        $this->actingAs($user)
            ->post(route('test-suites.move', $suite), ['parent_id' => $target->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($target->id, $suite->refresh()->parent_id);
    }

    public function test_a_suite_can_be_moved_to_the_project_root()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $parent = TestSuite::factory()->for($project)->create();
        $suite = TestSuite::factory()->childOf($parent)->create();

        $this->actingAs($user)
            ->post(route('test-suites.move', $suite), ['parent_id' => null])
            ->assertSessionHasNoErrors();

        $this->assertNull($suite->refresh()->parent_id);
    }

    public function test_moving_a_suite_into_its_own_subtree_is_rejected()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $child = TestSuite::factory()->childOf($suite)->create();

        $this->actingAs($user)
            ->post(route('test-suites.move', $suite), ['parent_id' => $child->id])
            ->assertSessionHasErrors('parent_id');

        $this->assertNull($suite->refresh()->parent_id);
    }

    public function test_a_suite_can_be_copied_into_another_project()
    {
        $source = TestProject::factory()->create();
        $destination = TestProject::factory()->create();
        $user = $this->userWhoCan($source, Ability::ViewTestCases);
        $this->assignProjectRole($user, $destination, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($source)->create(['name' => 'Authentication']);
        TestCaseModel::factory()->for($suite, 'testSuite')->withVersion()->create();

        $this->actingAs($user)
            ->post(route('test-suites.copy', $suite), ['test_project_id' => $destination->id])
            ->assertSessionHasNoErrors();

        $copy = TestSuite::query()->where('test_project_id', $destination->id)->sole();

        $this->assertSame('Authentication', $copy->name);
        $this->assertSame($destination->id, $copy->testCases->sole()->test_project_id);
    }

    public function test_a_suite_can_be_copied_within_its_project()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::ManageTestCases);
        $suite = TestSuite::factory()->for($project)->create(['name' => 'Authentication']);
        TestCaseModel::factory()->for($suite, 'testSuite')->withVersion()->create();
        $target = TestSuite::factory()->for($project)->create();

        $this->actingAs($user)
            ->post(route('test-suites.copy', $suite), ['parent_id' => $target->id])
            ->assertSessionHasNoErrors();

        $copy = TestSuite::query()->where('parent_id', $target->id)->sole();

        $this->assertSame('Authentication', $copy->name);
        $this->assertSame(2, TestCaseModel::query()->count());
    }

    public function test_sibling_suites_can_be_reordered()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $first = TestSuite::factory()->for($project)->create(['sort_order' => 1]);
        $second = TestSuite::factory()->for($project)->create(['sort_order' => 2]);

        $this->actingAs($user)
            ->post(route('test-suites.reorder', $project), [
                'parent_id' => null,
                'order' => [$second->id, $first->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $second->refresh()->sort_order);
        $this->assertSame(2, $first->refresh()->sort_order);
    }

    public function test_reordering_with_a_suite_from_another_parent_is_rejected()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $root = TestSuite::factory()->for($project)->create();
        $nested = TestSuite::factory()->childOf($root)->create();

        $this->actingAs($user)
            ->post(route('test-suites.reorder', $project), [
                'parent_id' => null,
                'order' => [$root->id, $nested->id],
            ])
            ->assertSessionHasErrors('order');
    }

    public function test_the_write_endpoints_reject_a_guest()
    {
        $project = TestProject::factory()->create();

        $this->post(route('test-suites.store', $project), ['name' => 'Nope'])
            ->assertRedirect(route('login'));
    }
}
