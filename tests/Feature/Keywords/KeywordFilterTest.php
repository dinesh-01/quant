<?php

namespace Tests\Feature\Keywords;

use App\Enums\Ability;
use App\Models\Keyword;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestProject;
use App\Models\TestSuite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\TestSpecification\InteractsWithSpecificationRoles;
use Tests\TestCase;

/**
 * Filtering the specification tree, which is what makes a catalogue worth
 * keeping.
 */
class KeywordFilterTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_the_tree_shows_only_cases_carrying_the_keyword()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::ViewKeywords);
        $suite = TestSuite::factory()->for($project)->create();
        $smoke = Keyword::factory()->for($project)->named('smoke')->create();

        $tagged = $this->caseIn($project, $suite, 'Tagged');
        $this->caseIn($project, $suite, 'Untagged');
        $tagged->keywords()->attach($smoke);

        $this->actingAs($user)
            ->get(route('specification.show', $project).'?keywords[]='.$smoke->id)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('tree.0.cases', 1)
                ->where('tree.0.cases.0.name', 'Tagged')
                ->where('keywordFilter.ids', [$smoke->id])
                ->where('keywordFilter.match', 'any')
            );
    }

    public function test_matching_any_keyword_is_the_default()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $smoke = Keyword::factory()->for($project)->named('smoke')->create();
        $slow = Keyword::factory()->for($project)->named('slow')->create();

        $this->caseIn($project, $suite, 'Only smoke')->keywords()->attach($smoke);
        $this->caseIn($project, $suite, 'Only slow')->keywords()->attach($slow);

        $this->actingAs($user)
            ->get(route('specification.show', $project)."?keywords[]={$smoke->id}&keywords[]={$slow->id}")
            ->assertInertia(fn (AssertableInertia $page) => $page->has('tree.0.cases', 2));
    }

    public function test_matching_all_keywords_needs_every_one_of_them()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $smoke = Keyword::factory()->for($project)->named('smoke')->create();
        $slow = Keyword::factory()->for($project)->named('slow')->create();

        $both = $this->caseIn($project, $suite, 'Both');
        $both->keywords()->attach([$smoke->id, $slow->id]);
        $this->caseIn($project, $suite, 'Only smoke')->keywords()->attach($smoke);

        $this->actingAs($user)
            ->get(route('specification.show', $project)."?keywords[]={$smoke->id}&keywords[]={$slow->id}&keyword_match=all")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('tree.0.cases', 1)
                ->where('tree.0.cases.0.name', 'Both')
                ->where('keywordFilter.match', 'all')
            );
    }

    /**
     * Showing the whole tree with most of it empty would leave the reader
     * hunting for the matches, which is the thing they asked to be shown.
     */
    public function test_suites_with_no_matches_are_left_out()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $withMatch = TestSuite::factory()->for($project)->create(['name' => 'Has one']);
        TestSuite::factory()->for($project)->create(['name' => 'Has none']);
        $keyword = Keyword::factory()->for($project)->create();

        $this->caseIn($project, $withMatch, 'Tagged')->keywords()->attach($keyword);

        $this->actingAs($user)
            ->get(route('specification.show', $project).'?keywords[]='.$keyword->id)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('tree', 1)
                ->where('tree.0.name', 'Has one')
            );
    }

    /**
     * A parent holding no matches itself still has to appear, or the matching
     * case beneath it would have nothing to hang from.
     */
    public function test_a_suite_is_kept_when_a_case_beneath_it_matches()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $root = TestSuite::factory()->for($project)->create(['name' => 'Root']);
        $child = TestSuite::factory()->childOf($root)->create(['name' => 'Child']);
        $keyword = Keyword::factory()->for($project)->create();

        $this->caseIn($project, $child, 'Tagged')->keywords()->attach($keyword);

        $this->actingAs($user)
            ->get(route('specification.show', $project).'?keywords[]='.$keyword->id)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tree.0.name', 'Root')
                ->has('tree.0.cases', 0)
                ->where('tree.0.children.0.name', 'Child')
                ->has('tree.0.children.0.cases', 1)
            );
    }

    public function test_the_whole_tree_is_shown_when_nothing_is_filtered()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        $suite = TestSuite::factory()->for($project)->create();
        $this->caseIn($project, $suite, 'Untagged');

        $this->actingAs($user)
            ->get(route('specification.show', $project))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('tree.0.cases', 1)
                ->where('keywordFilter.ids', [])
            );
    }

    /**
     * An id from another project is discarded rather than applied, so it cannot
     * silently narrow the tree to nothing, and the screen can echo back exactly
     * what is being filtered on.
     */
    public function test_a_keyword_from_another_project_is_ignored()
    {
        $ours = TestProject::factory()->create();
        $theirs = TestProject::factory()->create();
        $user = $this->userWhoCan($ours, Ability::ViewTestCases);
        $suite = TestSuite::factory()->for($ours)->create();
        $this->caseIn($ours, $suite, 'Untagged');
        $foreign = Keyword::factory()->for($theirs)->create();

        $this->actingAs($user)
            ->get(route('specification.show', $ours).'?keywords[]='.$foreign->id)
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('keywordFilter.ids', [])
                ->has('tree.0.cases', 1)
            );
    }

    public function test_a_selected_case_carries_its_keywords()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::AssignKeywords);
        $suite = TestSuite::factory()->for($project)->create();
        $case = $this->caseIn($project, $suite, 'Tagged');
        $case->keywords()->attach(Keyword::factory()->for($project)->named('smoke')->create());

        $this->actingAs($user)
            ->get(route('specification.cases.show', [$project, $case]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('selected.case.keywords.0.name', 'smoke')
                ->where('can.assignKeywords', true)
            );
    }

    /**
     * The vocabulary is sent for the filter and the picker, so it goes only to
     * someone who can use one of them. A case's own keywords are part of the
     * case and always sent.
     */
    public function test_the_vocabulary_is_withheld_without_a_keyword_ability()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);
        Keyword::factory()->for($project)->create();

        $this->actingAs($user)
            ->get(route('specification.show', $project))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('keywords', 0)
                ->where('can.assignKeywords', false)
                ->where('can.viewKeywords', false)
            );
    }

    public function test_the_vocabulary_is_sent_to_someone_who_may_assign()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::AssignKeywords);
        Keyword::factory()->for($project)->named('smoke')->create();

        $this->actingAs($user)
            ->get(route('specification.show', $project))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('keywords', 1)
                ->where('keywords.0.name', 'smoke')
            );
    }

    /**
     * The catalogue is only linked from this screen for someone who can read
     * it, since the link would 403 for anyone else.
     */
    public function test_someone_who_may_read_the_catalogue_is_told_so()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::ViewKeywords);
        Keyword::factory()->for($project)->named('smoke')->create();

        $this->actingAs($user)
            ->get(route('specification.show', $project))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('can.viewKeywords', true)
                ->where('can.assignKeywords', false)
                ->where('keywords.0.name', 'smoke')
            );
    }

    private function caseIn(TestProject $project, TestSuite $suite, string $name): TestCaseModel
    {
        return TestCaseModel::factory()
            ->for($suite, 'testSuite')
            ->withVersion()
            ->create(['test_project_id' => $project->id, 'name' => $name]);
    }
}
