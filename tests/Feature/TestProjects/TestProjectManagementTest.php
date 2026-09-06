<?php

namespace Tests\Feature\TestProjects;

use App\Enums\Ability;
use App\Models\Role;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestCaseStep;
use App\Models\TestCaseVersion;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Creating, editing and deleting projects.
 *
 * `manage_test_projects` is a system ability, so every check here is against
 * the user's global role and a project role must never substitute for it.
 */
class TestProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    private function administrator(): User
    {
        return User::factory()
            ->for(Role::factory()->granting(Ability::ManageTestProjects))
            ->create();
    }

    /**
     * The create form's checkboxes submit "on", not "1". Laravel's boolean
     * rule rejects "on", so without folding it the request 422s and nothing
     * is stored — the failure that showed up as an empty project list.
     */
    public function test_a_browser_checkbox_on_creates_the_project(): void
    {
        $this->actingAs($this->administrator())
            ->post(route('projects.store'), [
                'name' => 'Payment Platform',
                'prefix' => 'PAY',
                'description' => 'Payment platform',
                'is_public' => 'on',
                'is_active' => 'on',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $project = TestProject::query()->sole();

        $this->assertSame('Payment Platform', $project->name);
        $this->assertSame('PAY', $project->prefix);
        $this->assertTrue($project->is_public);
        $this->assertTrue($project->is_active);
    }

    public function test_an_administrator_creates_a_project(): void
    {
        $this->actingAs($this->administrator())
            ->post(route('projects.store'), [
                'name' => 'Payments platform',
                'prefix' => 'pay',
                'description' => 'Card and wallet flows.',
                'is_public' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $project = TestProject::query()->sole();

        $this->assertSame('Payments platform', $project->name);
        $this->assertSame('PAY', $project->prefix, 'The prefix should be folded to upper case.');
        $this->assertSame('Card and wallet flows.', $project->description);
        $this->assertTrue($project->is_public);
        $this->assertTrue($project->is_active);
        $this->assertSame(0, $project->test_case_counter);
    }

    public function test_creating_lands_on_the_new_project_specification(): void
    {
        $this->actingAs($this->administrator())
            ->post(route('projects.store'), [
                'name' => 'Payments platform',
                'prefix' => 'PAY',
            ])
            ->assertRedirect(route('specification.show', TestProject::query()->sole()));
    }

    public function test_unchecked_switches_are_stored_as_false(): void
    {
        $this->actingAs($this->administrator())
            ->post(route('projects.store'), ['name' => 'Internal', 'prefix' => 'INT'])
            ->assertRedirect();

        $project = TestProject::query()->sole();

        $this->assertFalse($project->is_public);
        $this->assertFalse($project->is_active);
    }

    public function test_an_empty_description_is_stored_as_null(): void
    {
        $this->actingAs($this->administrator())
            ->post(route('projects.store'), [
                'name' => 'Payments',
                'prefix' => 'PAY',
                'description' => '',
            ])
            ->assertRedirect();

        $this->assertNull(TestProject::query()->sole()->description);
    }

    public function test_a_duplicate_name_or_prefix_is_rejected(): void
    {
        TestProject::factory()->create(['name' => 'Payments', 'prefix' => 'PAY']);

        $this->actingAs($this->administrator())
            ->post(route('projects.store'), ['name' => 'Payments', 'prefix' => 'OTHER'])
            ->assertSessionHasErrors('name');

        $this->actingAs($this->administrator())
            ->post(route('projects.store'), ['name' => 'Other', 'prefix' => 'PAY'])
            ->assertSessionHasErrors('prefix');
    }

    /**
     * The prefix is uppercased before uniqueness is checked, so a differently
     * cased duplicate cannot slip past the unique index.
     */
    public function test_a_prefix_differing_only_in_case_is_a_duplicate(): void
    {
        TestProject::factory()->create(['prefix' => 'PAY']);

        $this->actingAs($this->administrator())
            ->post(route('projects.store'), ['name' => 'Other', 'prefix' => 'pay'])
            ->assertSessionHasErrors('prefix');
    }

    public function test_a_prefix_with_unusable_characters_is_rejected(): void
    {
        $this->actingAs($this->administrator())
            ->post(route('projects.store'), ['name' => 'Payments', 'prefix' => 'PAY 1!'])
            ->assertSessionHasErrors('prefix');
    }

    public function test_a_project_role_does_not_grant_project_administration(): void
    {
        $project = TestProject::factory()->create();

        $user = User::factory()->for(Role::factory()->granting())->create();
        $user->projectRoles()->attach(
            Role::factory()->granting(Ability::ManageTestProjects)->create(),
            ['test_project_id' => $project->id],
        );

        $this->actingAs($user)->get(route('projects.create'))->assertForbidden();

        $this->actingAs($user)
            ->post(route('projects.store'), ['name' => 'New', 'prefix' => 'NEW'])
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('projects.update', $project), ['name' => 'Renamed', 'prefix' => $project->prefix])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('projects.destroy', $project), ['confirm_name' => $project->name])
            ->assertForbidden();

        $this->assertSame(1, TestProject::query()->count());
    }

    public function test_an_administrator_edits_a_project(): void
    {
        $project = TestProject::factory()->create(['name' => 'Old', 'prefix' => 'OLD']);

        $this->actingAs($this->administrator())
            ->put(route('projects.update', $project), [
                'name' => 'New',
                'prefix' => 'NEW',
                'is_public' => '1',
                'is_active' => '1',
            ])
            ->assertRedirect(route('projects.index'));

        $project->refresh();

        $this->assertSame('New', $project->name);
        $this->assertSame('NEW', $project->prefix);
    }

    public function test_the_prefix_is_frozen_once_a_case_has_been_numbered(): void
    {
        $project = TestProject::factory()->create(['prefix' => 'PAY']);
        $project->forceFill(['test_case_counter' => 1])->save();

        $this->actingAs($this->administrator())
            ->put(route('projects.update', $project), ['name' => $project->name, 'prefix' => 'NEW'])
            ->assertSessionHasErrors('prefix');

        $this->assertSame('PAY', $project->refresh()->prefix);
    }

    /**
     * Only a *change* is refused, so an unrelated edit does not have to work
     * around the frozen prefix by omitting it.
     */
    public function test_a_numbered_project_can_still_be_edited_with_its_prefix_unchanged(): void
    {
        $project = TestProject::factory()->create(['name' => 'Old', 'prefix' => 'PAY']);
        $project->forceFill(['test_case_counter' => 4])->save();

        $this->actingAs($this->administrator())
            ->put(route('projects.update', $project), [
                'name' => 'Renamed',
                'prefix' => 'PAY',
                'is_active' => '1',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $project->refresh()->name);
    }

    public function test_the_edit_page_reports_whether_the_prefix_is_locked(): void
    {
        $project = TestProject::factory()->create();

        $this->actingAs($this->administrator())
            ->get(route('projects.edit', $project))
            ->assertInertia(fn ($page) => $page
                ->component('test-projects/edit')
                ->where('project.prefix_locked', false),
            );

        $project->forceFill(['test_case_counter' => 2])->save();

        $this->actingAs($this->administrator())
            ->get(route('projects.edit', $project))
            ->assertInertia(fn ($page) => $page->where('project.prefix_locked', true));
    }

    public function test_deleting_a_project_takes_its_whole_specification_with_it(): void
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();
        $child = TestSuite::factory()->for($project)->for($suite, 'parent')->create();
        $case = TestCaseModel::factory()->for($project)->for($child, 'testSuite')->create();
        $version = TestCaseVersion::factory()->for($case, 'testCase')->create();
        TestCaseStep::factory()->for($version, 'testCaseVersion')->create();
        TestPlan::factory()->for($project)->create();

        $this->actingAs($this->administrator())
            ->delete(route('projects.destroy', $project), ['confirm_name' => $project->name])
            ->assertRedirect(route('projects.index'));

        $this->assertDatabaseCount('test_projects', 0);
        $this->assertDatabaseCount('test_suites', 0);
        $this->assertDatabaseCount('test_cases', 0);
        $this->assertDatabaseCount('test_case_versions', 0);
        $this->assertDatabaseCount('test_case_steps', 0);
        $this->assertDatabaseCount('test_plans', 0);
    }

    public function test_deleting_requires_the_project_name_to_be_typed(): void
    {
        $project = TestProject::factory()->create(['name' => 'Payments']);

        $this->actingAs($this->administrator())
            ->delete(route('projects.destroy', $project), ['confirm_name' => 'payments'])
            ->assertSessionHasErrors('confirm_name');

        $this->actingAs($this->administrator())
            ->delete(route('projects.destroy', $project))
            ->assertSessionHasErrors('confirm_name');

        $this->assertDatabaseCount('test_projects', 1);
    }
}
