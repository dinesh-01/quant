<?php

namespace Tests\Feature\TestProjects;

use App\Actions\Authorization\RoleResolver;
use App\Enums\Ability;
use App\Models\Role;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Which projects a user is shown, and whether that agrees with what they can
 * actually open.
 */
class TestProjectVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_sees_only_the_projects_their_role_covers(): void
    {
        $visible = TestProject::factory()->create(['name' => 'Visible']);
        TestProject::factory()->create(['name' => 'Unrelated']);

        $user = User::factory()->for(Role::factory()->granting())->create();
        $user->projectRoles()->attach(
            Role::factory()->granting(Ability::ViewTestCases)->create(),
            ['test_project_id' => $visible->id],
        );

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('test-projects/index')
                ->has('projects', 1)
                ->where('projects.0.name', 'Visible')
                ->where('can.manage', false),
            );
    }

    public function test_a_public_project_is_covered_by_the_global_role(): void
    {
        TestProject::factory()->create(['name' => 'Open']);
        TestProject::factory()->restricted()->create(['name' => 'Closed']);

        $user = User::factory()
            ->for(Role::factory()->granting(Ability::ViewTestCases))
            ->create();

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('projects', 1)
                ->where('projects.0.name', 'Open'),
            );
    }

    public function test_an_inactive_project_is_hidden_from_members_but_not_from_administrators(): void
    {
        TestProject::factory()->create(['name' => 'Retired', 'is_active' => false]);

        $member = User::factory()
            ->for(Role::factory()->granting(Ability::ViewTestCases))
            ->create();

        $this->actingAs($member)
            ->get(route('projects.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('projects', 0));

        $administrator = User::factory()
            ->for(Role::factory()->granting(Ability::ManageTestProjects))
            ->create();

        $this->actingAs($administrator)
            ->get(route('projects.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('projects', 1)
                ->where('projects.0.is_active', false)
                ->where('can.manage', true),
            );
    }

    /**
     * An administrator holds no role inside a restricted project, so the card
     * must not offer a specification link that would answer 403.
     */
    public function test_an_administrator_sees_a_restricted_project_without_being_able_to_open_it(): void
    {
        $project = TestProject::factory()->restricted()->create();

        $administrator = User::factory()
            ->for(Role::factory()->granting(Ability::ManageTestProjects))
            ->create();

        $this->actingAs($administrator)
            ->get(route('projects.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('projects', 1)
                ->where('projects.0.can_open', false),
            );

        $this->actingAs($administrator)
            ->get(route('specification.show', $project))
            ->assertForbidden();
    }

    public function test_a_super_admin_sees_every_project(): void
    {
        TestProject::factory()->create();
        TestProject::factory()->restricted()->create();
        TestProject::factory()->create(['is_active' => false]);

        $user = User::factory()->for(Role::factory()->superAdmin())->create();

        $this->actingAs($user)
            ->get(route('projects.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('projects', 3));
    }

    /**
     * The bulk resolver behind the index and the per-project gate answer the
     * same question by different means. If they drift, the symptom is a listed
     * project that 403s or a hidden one reachable by URL, so pin them together
     * across every combination of visibility and grant.
     */
    public function test_the_bulk_resolver_agrees_with_the_single_project_gate(): void
    {
        $public = TestProject::factory()->create();
        $restricted = TestProject::factory()->restricted()->create();
        $assigned = TestProject::factory()->restricted()->create();

        $users = [
            'granted globally' => User::factory()
                ->for(Role::factory()->granting(Ability::ViewTestCases))
                ->create(),
            'granted nothing' => User::factory()
                ->for(Role::factory()->granting())
                ->create(),
            'super admin' => User::factory()
                ->for(Role::factory()->superAdmin())
                ->create(),
            'expired' => User::factory()
                ->for(Role::factory()->granting(Ability::ViewTestCases))
                ->create(['expires_at' => now()->subDay()]),
        ];

        foreach ($users as $user) {
            $user->projectRoles()->attach(
                Role::factory()->granting(Ability::ViewTestCases)->create(),
                ['test_project_id' => $assigned->id],
            );
        }

        $resolver = app(RoleResolver::class);
        $projects = TestProject::query()->orderBy('id')->get();

        foreach ($users as $description => $user) {
            $bulk = $resolver
                ->projectsAllowing($user->fresh(), Ability::ViewTestCases, $projects)
                ->modelKeys();

            foreach ([$public, $restricted, $assigned] as $project) {
                $this->assertSame(
                    $resolver->allows($user->fresh(), Ability::ViewTestCases, $project),
                    in_array($project->getKey(), $bulk, true),
                    "Bulk and single resolution disagree for a user {$description} on project {$project->id}.",
                );
            }
        }
    }

    /**
     * The list reads the projects once and the user's project roles once, and
     * resolves the rest in memory, so adding projects must not add queries.
     *
     * Only the queries that touch the project tables are counted. A whole-log
     * count would also pick up the acting user's global role, which is loaded
     * once per user instance and then cached, making the two passes differ for
     * a reason that has nothing to do with the number of projects.
     */
    public function test_the_project_list_does_not_query_per_project(): void
    {
        $user = User::factory()
            ->for(Role::factory()->granting(Ability::ViewTestCases))
            ->create();

        foreach ([1, 12] as $projectCount) {
            TestProject::query()->delete();
            TestProject::factory()->count($projectCount)->create();

            DB::enableQueryLog();
            DB::flushQueryLog();

            $this->actingAs($user)->get(route('projects.index'))->assertOk();

            $projectQueries = array_filter(
                DB::getQueryLog(),
                fn (array $entry): bool => str_contains((string) $entry['query'], 'test_project'),
            );

            DB::disableQueryLog();

            $this->assertCount(
                2,
                $projectQueries,
                "Listing {$projectCount} projects should take one query for the projects and one for the user's project roles.",
            );
        }
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('projects.index'))->assertRedirect(route('login'));
    }
}
