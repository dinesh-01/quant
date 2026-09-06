<?php

namespace Tests\Feature\Keywords;

use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\KeywordBulkMode;
use App\Models\AuditEvent;
use App\Models\Keyword;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestProject;
use App\Models\TestSuite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\TestSpecification\InteractsWithSpecificationRoles;
use Tests\TestCase;

/**
 * Legacy's keyword assignment screen, which is the only practical way to tag a
 * specification that already exists.
 */
class KeywordBulkAssignmentTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_a_keyword_can_be_applied_to_a_whole_subtree()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::AssignKeywords);
        $root = TestSuite::factory()->for($project)->create();
        $child = TestSuite::factory()->childOf($root)->create();
        $keyword = Keyword::factory()->for($project)->named('smoke')->create();

        $inRoot = $this->caseIn($project, $root);
        $inChild = $this->caseIn($project, $child);

        $this->actingAs($user)
            ->post(route('test-suites.keywords.store', $root), [
                'mode' => KeywordBulkMode::Assign->value,
                'keywords' => [$keyword->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(['smoke'], $inRoot->keywords()->pluck('name')->all());
        $this->assertSame(['smoke'], $inChild->keywords()->pluck('name')->all());
    }

    public function test_the_run_can_be_limited_to_the_suites_own_cases()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::AssignKeywords);
        $root = TestSuite::factory()->for($project)->create();
        $child = TestSuite::factory()->childOf($root)->create();
        $keyword = Keyword::factory()->for($project)->create();

        $inRoot = $this->caseIn($project, $root);
        $inChild = $this->caseIn($project, $child);

        $this->actingAs($user)
            ->post(route('test-suites.keywords.store', $root), [
                'mode' => KeywordBulkMode::Assign->value,
                'keywords' => [$keyword->id],
                'direct_children_only' => true,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, $inRoot->keywords()->count());
        $this->assertSame(0, $inChild->keywords()->count());
    }

    /**
     * Adds rather than replaces, so a run cannot discard tags the person
     * running it never saw.
     */
    public function test_applying_a_keyword_leaves_other_keywords_alone()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::AssignKeywords);
        $suite = TestSuite::factory()->for($project)->create();
        $existing = Keyword::factory()->for($project)->named('existing')->create();
        $added = Keyword::factory()->for($project)->named('added')->create();
        $case = $this->caseIn($project, $suite);
        $case->keywords()->attach($existing);

        $this->actingAs($user)
            ->post(route('test-suites.keywords.store', $suite), [
                'mode' => KeywordBulkMode::Assign->value,
                'keywords' => [$added->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(['added', 'existing'], $case->keywords()->pluck('name')->all());
    }

    public function test_a_keyword_can_be_taken_off_a_subtree()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::AssignKeywords);
        $suite = TestSuite::factory()->for($project)->create();
        $removed = Keyword::factory()->for($project)->named('removed')->create();
        $kept = Keyword::factory()->for($project)->named('kept')->create();
        $case = $this->caseIn($project, $suite);
        $case->keywords()->attach([$removed->id, $kept->id]);

        $this->actingAs($user)
            ->post(route('test-suites.keywords.store', $suite), [
                'mode' => KeywordBulkMode::Remove->value,
                'keywords' => [$removed->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(['kept'], $case->keywords()->pluck('name')->all());
    }

    public function test_every_keyword_can_be_cleared_from_a_subtree()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::AssignKeywords);
        $suite = TestSuite::factory()->for($project)->create();
        $case = $this->caseIn($project, $suite);
        $case->keywords()->attach(Keyword::factory()->count(3)->for($project)->create());

        $this->actingAs($user)
            ->post(route('test-suites.keywords.store', $suite), [
                'mode' => KeywordBulkMode::ClearAll->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $case->keywords()->count());
    }

    /**
     * A run over a subtree that matched nothing looks identical to one that
     * changed four hundred cases unless it says so.
     */
    public function test_the_run_reports_how_many_cases_changed()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::AssignKeywords);
        $suite = TestSuite::factory()->for($project)->create();
        $keyword = Keyword::factory()->for($project)->create();
        $this->caseIn($project, $suite);
        $this->caseIn($project, $suite);

        $this->actingAs($user)
            ->post(route('test-suites.keywords.store', $suite), [
                'mode' => KeywordBulkMode::Assign->value,
                'keywords' => [$keyword->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            2,
            AuditEvent::query()
                ->where('action', AuditAction::TestSuiteKeywordsApplied->value)
                ->sole()
                ->properties['test_cases'],
        );
    }

    /**
     * One button press is one act, the same judgement a subtree copy is
     * recorded under. Forty records would bury the acts a reader came for.
     */
    public function test_the_whole_run_is_one_audit_record_against_the_suite()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::AssignKeywords);
        $suite = TestSuite::factory()->for($project)->create(['name' => 'Authentication']);
        $keyword = Keyword::factory()->for($project)->named('smoke')->create();
        $this->caseIn($project, $suite);
        $this->caseIn($project, $suite);
        $this->caseIn($project, $suite);

        $this->actingAs($user)
            ->post(route('test-suites.keywords.store', $suite), [
                'mode' => KeywordBulkMode::Assign->value,
                'keywords' => [$keyword->id],
            ])
            ->assertSessionHasNoErrors();

        $event = AuditEvent::query()->sole();

        $this->assertSame(AuditAction::TestSuiteKeywordsApplied->value, $event->action);
        $this->assertSame(TestSuite::class, $event->subject_type);
        $this->assertSame($suite->id, $event->subject_id);
        $this->assertSame('assign', $event->properties['mode']);
        $this->assertSame(['smoke'], $event->properties['keywords']);
    }

    public function test_a_run_that_changes_nothing_records_nothing()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::AssignKeywords);
        $suite = TestSuite::factory()->for($project)->create();
        $keyword = Keyword::factory()->for($project)->create();
        $this->caseIn($project, $suite)->keywords()->attach($keyword);

        $this->actingAs($user)
            ->post(route('test-suites.keywords.store', $suite), [
                'mode' => KeywordBulkMode::Assign->value,
                'keywords' => [$keyword->id],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, AuditEvent::query()->count());
    }

    /**
     * The cost has to stay flat in the number of cases, because this is the one
     * keyword write expected to cover hundreds of them.
     */
    public function test_the_run_does_not_issue_a_query_per_case()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::AssignKeywords);
        $suite = TestSuite::factory()->for($project)->create();
        $keyword = Keyword::factory()->for($project)->create();

        for ($i = 0; $i < 12; $i++) {
            $this->caseIn($project, $suite);
        }

        $this->actingAs($user);

        DB::enableQueryLog();

        $this->post(route('test-suites.keywords.store', $suite), [
            'mode' => KeywordBulkMode::Assign->value,
            'keywords' => [$keyword->id],
        ])->assertSessionHasNoErrors();

        $queries = count(DB::getRawQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(12, $queries);
    }

    public function test_the_mode_is_required()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::AssignKeywords);
        $suite = TestSuite::factory()->for($project)->create();
        $keyword = Keyword::factory()->for($project)->create();

        $this->actingAs($user)
            ->post(route('test-suites.keywords.store', $suite), ['keywords' => [$keyword->id]])
            ->assertSessionHasErrors('mode');
    }

    public function test_assigning_nothing_is_refused_unless_the_mode_is_clear_all()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::AssignKeywords);
        $suite = TestSuite::factory()->for($project)->create();

        $this->actingAs($user)
            ->post(route('test-suites.keywords.store', $suite), [
                'mode' => KeywordBulkMode::Assign->value,
            ])
            ->assertSessionHasErrors('keywords');
    }

    public function test_a_keyword_from_another_project_is_refused()
    {
        $ours = TestProject::factory()->create();
        $theirs = TestProject::factory()->create();
        $user = $this->userWhoCan($ours, Ability::AssignKeywords);
        $suite = TestSuite::factory()->for($ours)->create();
        $case = $this->caseIn($ours, $suite);
        $foreign = Keyword::factory()->for($theirs)->create();

        $this->actingAs($user)
            ->post(route('test-suites.keywords.store', $suite), [
                'mode' => KeywordBulkMode::Assign->value,
                'keywords' => [$foreign->id],
            ])
            ->assertSessionHasErrors('keywords');

        $this->assertSame(0, $case->keywords()->count());
    }

    public function test_a_bulk_run_requires_the_assign_ability()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases, Ability::ManageKeywords);
        $suite = TestSuite::factory()->for($project)->create();
        $keyword = Keyword::factory()->for($project)->create();
        $case = $this->caseIn($project, $suite);

        $this->actingAs($user)
            ->post(route('test-suites.keywords.store', $suite), [
                'mode' => KeywordBulkMode::Assign->value,
                'keywords' => [$keyword->id],
            ])
            ->assertForbidden();

        $this->assertSame(0, $case->keywords()->count());
    }

    private function caseIn(TestProject $project, TestSuite $suite): TestCaseModel
    {
        return TestCaseModel::factory()
            ->for($suite, 'testSuite')
            ->withVersion()
            ->create(['test_project_id' => $project->id]);
    }
}
