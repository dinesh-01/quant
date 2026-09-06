<?php

namespace Tests\Feature\TestSpecification;

use App\Enums\Ability;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestCaseStep;
use App\Models\TestCaseVersion;
use App\Models\TestProject;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SpecificationPageTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_the_page_lists_the_project_tree()
    {
        $project = TestProject::factory()->create(['prefix' => 'QA']);
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $root = TestSuite::factory()->for($project)->create(['name' => 'Authentication']);
        $child = TestSuite::factory()->childOf($root)->create(['name' => 'Passwords']);
        TestCaseModel::factory()->for($child, 'testSuite')->create(['name' => 'Reset works']);

        $this->actingAs($user)
            ->get(route('specification.show', $project))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('test-specification/index')
                ->where('project.prefix', 'QA')
                ->where('tree.0.name', 'Authentication')
                ->where('tree.0.children.0.name', 'Passwords')
                ->where('tree.0.children.0.cases.0.name', 'Reset works')
                ->where('tree.0.children.0.cases.0.full_external_id', 'QA-1')
                ->where('selected', null)
            );
    }

    public function test_viewing_the_page_requires_the_view_ability()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project);

        $this->actingAs($user)
            ->get(route('specification.show', $project))
            ->assertForbidden();
    }

    public function test_a_guest_is_sent_to_the_login_page()
    {
        $project = TestProject::factory()->create();

        $this->get(route('specification.show', $project))->assertRedirect(route('login'));
    }

    public function test_the_tree_is_always_two_queries_however_large_it_is()
    {
        $small = $this->projectWithSuites(2);
        $large = $this->projectWithSuites(25);

        $this->assertSame(2, $this->treeQueries($small));
        $this->assertSame(2, $this->treeQueries($large));
    }

    public function test_selecting_a_suite_includes_its_path()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $root = TestSuite::factory()->for($project)->create(['name' => 'Root']);
        $child = TestSuite::factory()->childOf($root)->create(['name' => 'Child']);

        $this->actingAs($user)
            ->get(route('specification.suites.show', [$project, $child]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('selected.type', 'suite')
                ->where('selected.suite.name', 'Child')
                ->where('selected.suite.path.0.name', 'Root')
                ->where('selected.suite.path.1.name', 'Child')
            );
    }

    public function test_selecting_a_case_includes_its_latest_version_and_steps()
    {
        $project = TestProject::factory()->create(['prefix' => 'QA']);
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $case = TestCaseModel::factory()->create(['test_project_id' => $project->id, 'name' => 'Login works']);
        TestCaseVersion::factory()->for($case, 'testCase')->version(1)->create(['summary' => 'Old']);
        $latest = TestCaseVersion::factory()->for($case, 'testCase')->version(2)->create(['summary' => 'Current']);
        TestCaseStep::factory()->for($latest, 'testCaseVersion')->at(1)->create(['actions' => 'Open the page']);

        $this->actingAs($user)
            ->get(route('specification.cases.show', [$project, $case]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('selected.type', 'case')
                ->where('selected.case.full_external_id', 'QA-1')
                ->where('selected.case.version.version', 2)
                ->where('selected.case.version.summary', 'Current')
                ->where('selected.case.version.steps.0.actions', 'Open the page')
                ->count('selected.case.versions', 2)
            );
    }

    public function test_an_older_version_can_be_read_by_its_number()
    {
        [$project, $user, $case] = $this->caseWithVersions(['First cut', 'Reworked', 'Final']);

        $this->actingAs($user)
            ->get(route('specification.cases.show', [$project, $case]).'?version=1')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('selected.case.version.version', 1)
                ->where('selected.case.version.summary', 'First cut')
                ->count('selected.case.versions', 3)
            );
    }

    public function test_a_version_number_that_does_not_exist_is_not_found()
    {
        [$project, $user, $case] = $this->caseWithVersions(['Only one']);

        $this->actingAs($user)
            ->get(route('specification.cases.show', [$project, $case]).'?version=7')
            ->assertNotFound();
    }

    /**
     * A stale or hand-edited link must not quietly show the newest version
     * under a URL that names a different one.
     */
    public function test_a_version_that_is_not_a_number_is_not_found()
    {
        [$project, $user, $case] = $this->caseWithVersions(['First cut', 'Reworked']);

        $this->actingAs($user)
            ->get(route('specification.cases.show', [$project, $case]).'?version=latest')
            ->assertNotFound();

        $this->actingAs($user)
            ->get(route('specification.cases.show', [$project, $case]).'?version=')
            ->assertNotFound();
    }

    public function test_the_version_list_says_which_versions_are_frozen()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $case = TestCaseModel::factory()->create(['test_project_id' => $project->id]);
        TestCaseVersion::factory()->for($case, 'testCase')->version(1)->frozen()->create();
        TestCaseVersion::factory()->for($case, 'testCase')->version(2)->create();

        $this->actingAs($user)
            ->get(route('specification.cases.show', [$project, $case]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('selected.case.versions.0.is_open', false)
                ->where('selected.case.versions.1.is_open', true)
            );
    }

    /**
     * The reorder controls read the sibling order out of the tree prop and post
     * it back, so an unordered tree would have them send a wrong order.
     */
    public function test_the_tree_arrives_in_sibling_order()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);

        TestSuite::factory()->for($project)->create(['name' => 'Third', 'sort_order' => 3]);
        TestSuite::factory()->for($project)->create(['name' => 'First', 'sort_order' => 1]);
        $second = TestSuite::factory()->for($project)->create(['name' => 'Second', 'sort_order' => 2]);

        TestCaseModel::factory()->for($second, 'testSuite')->create(['name' => 'Later', 'sort_order' => 2]);
        TestCaseModel::factory()->for($second, 'testSuite')->create(['name' => 'Sooner', 'sort_order' => 1]);

        $this->actingAs($user)
            ->get(route('specification.show', $project))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tree.0.name', 'First')
                ->where('tree.1.name', 'Second')
                ->where('tree.2.name', 'Third')
                ->where('tree.1.cases.0.name', 'Sooner')
                ->where('tree.1.cases.1.name', 'Later')
            );
    }

    public function test_reordering_suites_changes_the_order_the_tree_arrives_in()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::ManageTestCases);
        $first = TestSuite::factory()->for($project)->create(['name' => 'First', 'sort_order' => 1]);
        $second = TestSuite::factory()->for($project)->create(['name' => 'Second', 'sort_order' => 2]);

        $this->actingAs($user)
            ->post(route('test-suites.reorder', $project), [
                'parent_id' => '',
                'order' => [$second->id, $first->id],
            ])
            ->assertRedirect();

        $this->actingAs($user)
            ->get(route('specification.show', $project))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tree.0.name', 'Second')
                ->where('tree.1.name', 'First')
            );
    }

    public function test_a_case_from_another_project_is_not_reachable_through_this_project()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $foreign = TestCaseModel::factory()->withVersion()->create();

        $this->actingAs($user)
            ->get(route('specification.cases.show', [$project, $foreign]))
            ->assertNotFound();
    }

    public function test_the_page_reports_what_the_user_may_do()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::ManageTestCases);

        $this->actingAs($user)
            ->get(route('specification.show', $project))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('can.manage', true)
                ->where('can.freeze', false)
                ->where('can.deleteFrozen', false)
            );
    }

    public function test_search_matches_a_case_name()
    {
        [$project, $user] = $this->projectWithCases(['Login works', 'Logout works', 'Reset password']);

        $this->actingAs($user)
            ->getJson(route('specification.search', $project).'?term=Log')
            ->assertOk()
            ->assertJsonCount(2, 'results');
    }

    public function test_search_matches_an_external_id()
    {
        [$project, $user] = $this->projectWithCases(['First', 'Second', 'Third']);

        $response = $this->actingAs($user)
            ->getJson(route('specification.search', $project).'?term=2')
            ->assertOk();

        $this->assertSame('Second', $response->json('results.0.name'));
    }

    public function test_a_percent_sign_is_searched_for_literally()
    {
        [$project, $user] = $this->projectWithCases(['Discount of 50% applies', 'Nothing to see']);

        $this->actingAs($user)
            ->getJson(route('specification.search', $project).'?term=50%25')
            ->assertOk()
            ->assertJsonCount(1, 'results');
    }

    public function test_a_bare_percent_sign_matches_nothing_rather_than_everything()
    {
        [$project, $user] = $this->projectWithCases(['One', 'Two', 'Three']);

        $this->actingAs($user)
            ->getJson(route('specification.search', $project).'?term=%25%25')
            ->assertOk()
            ->assertJsonCount(0, 'results');
    }

    public function test_an_underscore_is_searched_for_literally()
    {
        [$project, $user] = $this->projectWithCases(['The user_id is shown', 'The username is shown']);

        $this->actingAs($user)
            ->getJson(route('specification.search', $project).'?term=user_i')
            ->assertOk()
            ->assertJsonCount(1, 'results');
    }

    public function test_search_never_reaches_another_project()
    {
        [$project, $user] = $this->projectWithCases(['Mine']);
        TestCaseModel::factory()->create(['name' => 'Mine']);

        $this->actingAs($user)
            ->getJson(route('specification.search', $project).'?term=Mine')
            ->assertOk()
            ->assertJsonCount(1, 'results');
    }

    public function test_search_requires_a_term()
    {
        [$project, $user] = $this->projectWithCases(['Anything']);

        $this->actingAs($user)
            ->getJson(route('specification.search', $project).'?term=')
            ->assertStatus(422);
    }

    public function test_search_requires_the_view_ability()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project);

        $this->actingAs($user)
            ->getJson(route('specification.search', $project).'?term=anything')
            ->assertForbidden();
    }

    /**
     * A case whose versions are numbered from 1, each carrying the given
     * summary so the version on screen can be told apart from its neighbours.
     *
     * @param  array<int, string>  $summaries
     * @return array{TestProject, User, TestCaseModel}
     */
    private function caseWithVersions(array $summaries): array
    {
        $project = TestProject::factory()->create();
        $case = TestCaseModel::factory()->create(['test_project_id' => $project->id]);

        foreach ($summaries as $index => $summary) {
            TestCaseVersion::factory()
                ->for($case, 'testCase')
                ->version($index + 1)
                ->create(['summary' => $summary]);
        }

        return [$project, $this->userWhoCan($project, Ability::ViewTestCases), $case];
    }

    /**
     * @param  array<int, string>  $names
     * @return array{TestProject, User}
     */
    private function projectWithCases(array $names): array
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();

        foreach ($names as $name) {
            TestCaseModel::factory()->for($suite, 'testSuite')->create(['name' => $name]);
        }

        return [$project, $this->userWhoCan($project, Ability::ViewTestCases)];
    }

    private function projectWithSuites(int $count): TestProject
    {
        $project = TestProject::factory()->create();

        for ($i = 0; $i < $count; $i++) {
            $suite = TestSuite::factory()->for($project)->create();
            TestCaseModel::factory()->for($suite, 'testSuite')->create();
        }

        return $project;
    }

    /**
     * How many queries the tree itself costs, ignoring session and role
     * resolution so the count is not sensitive to how the acting user's roles
     * happen to be arranged.
     */
    private function treeQueries(TestProject $project): int
    {
        $user = $this->userWhoCan($project, Ability::ViewTestCases);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->actingAs($user)->get(route('specification.show', $project))->assertOk();

        $queries = array_column(DB::getQueryLog(), 'query');

        DB::disableQueryLog();

        return count(array_filter($queries, fn (string $query): bool => str_contains($query, 'test_suites')
            || str_contains($query, 'test_cases')));
    }
}
