<?php

namespace Tests\Feature\Keywords;

use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Keyword;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\TestSpecification\InteractsWithSpecificationRoles;
use Tests\TestCase;

class KeywordAssignmentTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_keywords_can_be_put_on_a_case()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::AssignKeywords);
        $case = $this->caseIn($project);
        $smoke = Keyword::factory()->for($project)->named('smoke')->create();
        $slow = Keyword::factory()->for($project)->named('slow')->create();

        $this->actingAs($user)
            ->put(route('test-cases.keywords.update', $case), ['keywords' => [$smoke->id, $slow->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame(['slow', 'smoke'], $case->keywords()->pluck('name')->all());
    }

    /**
     * The picker shows the whole vocabulary with the current set selected, so
     * submitting it means "these are the keywords now". Legacy's two entry
     * points disagreed: its case screen replaced and its bulk screen appended.
     */
    public function test_submitting_the_picker_replaces_what_was_there()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::AssignKeywords);
        $case = $this->caseIn($project);
        $kept = Keyword::factory()->for($project)->named('kept')->create();
        $dropped = Keyword::factory()->for($project)->named('dropped')->create();
        $case->keywords()->attach([$kept->id, $dropped->id]);

        $this->actingAs($user)
            ->put(route('test-cases.keywords.update', $case), ['keywords' => [$kept->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame(['kept'], $case->keywords()->pluck('name')->all());
    }

    /**
     * An empty multi-select submits nothing at all, so a missing key has to
     * mean "no keywords" or clearing the last one would be impossible.
     */
    public function test_submitting_nothing_clears_every_keyword()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::AssignKeywords);
        $case = $this->caseIn($project);
        $case->keywords()->attach(Keyword::factory()->for($project)->create());

        $this->actingAs($user)
            ->put(route('test-cases.keywords.update', $case))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $case->keywords()->count());
    }

    /**
     * The ids arrive as bare numbers, so nothing about a `7` says which project
     * it belongs to. Legacy's `addKeywords` never asked.
     */
    public function test_a_keyword_from_another_project_is_refused()
    {
        $ours = TestProject::factory()->create();
        $theirs = TestProject::factory()->create();
        $user = $this->userWhoCan($ours, Ability::AssignKeywords);
        $case = $this->caseIn($ours);
        $foreign = Keyword::factory()->for($theirs)->create();

        $this->actingAs($user)
            ->put(route('test-cases.keywords.update', $case), ['keywords' => [$foreign->id]])
            ->assertSessionHasErrors('keywords');

        $this->assertSame(0, $case->keywords()->count());
    }

    public function test_a_keyword_that_does_not_exist_is_refused()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::AssignKeywords);
        $case = $this->caseIn($project);

        $this->actingAs($user)
            ->put(route('test-cases.keywords.update', $case), ['keywords' => [9999]])
            ->assertSessionHasErrors('keywords');
    }

    public function test_a_change_records_what_went_on_and_what_came_off()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::AssignKeywords);
        $case = $this->caseIn($project);
        $added = Keyword::factory()->for($project)->named('added')->create();
        $removed = Keyword::factory()->for($project)->named('removed')->create();
        $case->keywords()->attach($removed);

        $this->actingAs($user)
            ->put(route('test-cases.keywords.update', $case), ['keywords' => [$added->id]])
            ->assertSessionHasNoErrors();

        $event = AuditEvent::query()->sole();

        $this->assertSame(AuditAction::TestCaseKeywordsChanged->value, $event->action);
        $this->assertSame(TestCaseModel::class, $event->subject_type);
        $this->assertSame($case->id, $event->subject_id);
        $this->assertSame(['added'], $event->properties['added']);
        $this->assertSame(['removed'], $event->properties['removed']);
    }

    /**
     * Re-submitting the same picker is the commonest thing this screen does,
     * and a trail full of "changed the keywords" with no change in it is worse
     * than no entry.
     */
    public function test_resubmitting_the_same_keywords_records_nothing()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::AssignKeywords);
        $case = $this->caseIn($project);
        $keyword = Keyword::factory()->for($project)->create();
        $case->keywords()->attach($keyword);

        $this->actingAs($user)
            ->put(route('test-cases.keywords.update', $case), ['keywords' => [$keyword->id]])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, AuditEvent::query()->count());
    }

    /**
     * Tagging cases and curating the vocabulary are different rights, so that
     * a tester tagging cases does not thereby get to invent keywords.
     */
    public function test_assigning_requires_the_assign_ability_not_the_manage_one()
    {
        $project = TestProject::factory()->create();
        $curator = $this->userWhoCan($project, Ability::ManageKeywords, Ability::ManageTestCases);
        $case = $this->caseIn($project);
        $keyword = Keyword::factory()->for($project)->create();

        $this->actingAs($curator)
            ->put(route('test-cases.keywords.update', $case), ['keywords' => [$keyword->id]])
            ->assertForbidden();

        $this->assertSame(0, $case->keywords()->count());
    }

    public function test_assigning_is_refused_for_a_case_in_another_project()
    {
        $ours = TestProject::factory()->create();
        $theirs = TestProject::factory()->create();
        $user = $this->userWhoCan($ours, Ability::AssignKeywords);
        $case = $this->caseIn($theirs);
        $keyword = Keyword::factory()->for($theirs)->create();

        $this->actingAs($user)
            ->put(route('test-cases.keywords.update', $case), ['keywords' => [$keyword->id]])
            ->assertForbidden();
    }

    private function caseIn(TestProject $project): TestCaseModel
    {
        return TestCaseModel::factory()
            ->withVersion()
            ->create(['test_project_id' => $project->id]);
    }
}
