<?php

namespace Tests\Feature\Keywords;

use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\AuditEvent;
use App\Models\Keyword;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\TestSpecification\InteractsWithSpecificationRoles;
use Tests\TestCase;

class KeywordCatalogueTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_a_keyword_can_be_added_to_a_project()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageKeywords);

        $this->actingAs($user)
            ->post(route('keywords.store', $project), ['name' => 'smoke', 'notes' => 'The shortest useful run'])
            ->assertRedirect(route('keywords.index', $project))
            ->assertSessionHasNoErrors();

        $keyword = Keyword::query()->sole();

        $this->assertSame('smoke', $keyword->name);
        $this->assertSame('The shortest useful run', $keyword->notes);
        $this->assertSame($project->id, $keyword->test_project_id);
    }

    public function test_creating_a_keyword_is_recorded_in_the_audit_trail()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageKeywords);

        $this->actingAs($user)
            ->post(route('keywords.store', $project), ['name' => 'smoke'])
            ->assertSessionHasNoErrors();

        $event = AuditEvent::query()->sole();

        $this->assertSame(AuditAction::KeywordCreated->value, $event->action);
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame('smoke', $event->properties['name']['to']);
    }

    public function test_two_keywords_in_one_project_cannot_share_a_name()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageKeywords);
        Keyword::factory()->for($project)->named('smoke')->create();

        $this->actingAs($user)
            ->post(route('keywords.store', $project), ['name' => 'smoke'])
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Keyword::query()->count());
    }

    /**
     * Legacy compared names with `UPPER()` in PHP and only added the database
     * constraint in 1.9.19, so older installations collected duplicates its own
     * screens could not tell apart.
     */
    public function test_a_name_differing_only_by_case_is_a_duplicate()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageKeywords);
        Keyword::factory()->for($project)->named('smoke')->create();

        $this->actingAs($user)
            ->post(route('keywords.store', $project), ['name' => 'Smoke'])
            ->assertSessionHasErrors('name');
    }

    /**
     * The name is trimmed before the uniqueness check, not after, or ` smoke`
     * would pass a check that `smoke` fails and the list would hold both.
     */
    public function test_a_name_is_trimmed_before_it_is_checked()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageKeywords);
        Keyword::factory()->for($project)->named('smoke')->create();

        $this->actingAs($user)
            ->post(route('keywords.store', $project), ['name' => '  smoke  '])
            ->assertSessionHasErrors('name');
    }

    public function test_two_projects_may_each_have_a_keyword_of_the_same_name()
    {
        $ours = TestProject::factory()->create();
        $theirs = TestProject::factory()->create();
        $user = $this->userWhoCan($ours, Ability::ManageKeywords);
        Keyword::factory()->for($theirs)->named('smoke')->create();

        $this->actingAs($user)
            ->post(route('keywords.store', $ours), ['name' => 'smoke'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Keyword::query()->count());
    }

    /**
     * Legacy refused a name containing `"` or `,`, because it moved keyword
     * assignments around as comma-separated strings in query strings. Nothing
     * here does, so the restriction is not reproduced.
     */
    public function test_a_name_may_contain_punctuation_legacy_refused()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageKeywords);

        $this->actingAs($user)
            ->post(route('keywords.store', $project), ['name' => 'needs "real" data, ideally'])
            ->assertSessionHasNoErrors();

        $this->assertSame('needs "real" data, ideally', Keyword::query()->sole()->name);
    }

    public function test_a_name_longer_than_the_column_is_refused()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageKeywords);

        $this->actingAs($user)
            ->post(route('keywords.store', $project), ['name' => str_repeat('a', 101)])
            ->assertSessionHasErrors('name');
    }

    /**
     * The assignments point at the row, not the word, so correcting a spelling
     * fixes every case at once. That is the whole reason for a curated list.
     */
    public function test_renaming_a_keyword_keeps_its_assignments()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageKeywords);
        $keyword = Keyword::factory()->for($project)->named('regresion')->create();
        $case = $this->caseIn($project);
        $case->keywords()->attach($keyword);

        $this->actingAs($user)
            ->put(route('keywords.update', $keyword), ['name' => 'regression'])
            ->assertSessionHasNoErrors();

        $this->assertSame('regression', $keyword->refresh()->name);
        $this->assertSame(['regression'], $case->keywords()->pluck('name')->all());
    }

    public function test_saving_a_keyword_unchanged_records_nothing()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageKeywords);
        $keyword = Keyword::factory()->for($project)->named('smoke')->create(['notes' => 'why']);

        $this->actingAs($user)
            ->put(route('keywords.update', $keyword), ['name' => 'smoke', 'notes' => 'why'])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, AuditEvent::query()->count());
    }

    /**
     * A keyword in use can be deleted: the pivot's foreign key cascades, and
     * what is destroyed is a label rather than content. The trail records how
     * far it reached.
     */
    public function test_deleting_a_keyword_removes_it_from_every_case()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageKeywords);
        $keyword = Keyword::factory()->for($project)->named('smoke')->create();
        $first = $this->caseIn($project);
        $second = $this->caseIn($project);
        $first->keywords()->attach($keyword);
        $second->keywords()->attach($keyword);

        $this->actingAs($user)
            ->delete(route('keywords.destroy', $keyword))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Keyword::query()->count());
        $this->assertSame(0, $first->keywords()->count());
        $this->assertSame(0, $second->keywords()->count());

        $event = AuditEvent::query()
            ->where('action', AuditAction::KeywordDeleted->value)
            ->sole();

        $this->assertSame('smoke', $event->properties['name']);
        $this->assertSame(2, $event->properties['test_cases']);
    }

    /**
     * Deleting a case must not take its keywords with it — only the
     * assignments. Legacy's MySQL schema had no foreign keys here at all, so
     * either side could leave the other pointing at nothing.
     */
    public function test_deleting_a_case_leaves_the_keyword_in_the_catalogue()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $keyword = Keyword::factory()->for($project)->create();
        $case = $this->caseIn($project);
        $case->keywords()->attach($keyword);

        $this->actingAs($user)
            ->delete(route('test-cases.destroy', $case))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Keyword::query()->count());
        $this->assertSame(0, $keyword->testCases()->count());
    }

    public function test_deleting_a_project_takes_its_keywords_with_it()
    {
        $project = TestProject::factory()->create();
        Keyword::factory()->count(3)->for($project)->create();

        $project->delete();

        $this->assertSame(0, Keyword::query()->count());
    }

    public function test_the_list_shows_each_keywords_usage()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewKeywords);
        $used = Keyword::factory()->for($project)->named('smoke')->create();
        Keyword::factory()->for($project)->named('unused')->create();
        $this->caseIn($project)->keywords()->attach($used);

        $this->actingAs($user)
            ->get(route('keywords.index', $project))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('keywords/index')
                ->where('keywords.0.name', 'smoke')
                ->where('keywords.0.test_cases_count', 1)
                ->where('keywords.1.name', 'unused')
                ->where('keywords.1.test_cases_count', 0)
                ->where('can.manage', false)
            );
    }

    public function test_the_list_only_shows_this_projects_keywords()
    {
        $ours = TestProject::factory()->create();
        $theirs = TestProject::factory()->create();
        $user = $this->userWhoCan($ours, Ability::ViewKeywords);
        Keyword::factory()->for($ours)->named('ours')->create();
        Keyword::factory()->for($theirs)->named('theirs')->create();

        $this->actingAs($user)
            ->get(route('keywords.index', $ours))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('keywords', 1)
                ->where('keywords.0.name', 'ours')
            );
    }

    /**
     * The project group in the sidebar is built from these, so the catalogue is
     * offered only where it can be opened.
     */
    public function test_the_shared_project_prop_reports_the_keyword_ability()
    {
        $project = TestProject::factory()->create();

        $this->actingAs($this->userWhoCan($project, Ability::ViewKeywords))
            ->get(route('keywords.index', $project))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('currentProject.can.viewKeywords', true)
                ->where('currentProject.can.viewSpecification', false)
                ->where('currentProject.id', $project->id)
            );
    }

    public function test_reading_the_list_requires_the_view_ability()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::AssignKeywords);

        $this->actingAs($user)
            ->get(route('keywords.index', $project))
            ->assertForbidden();
    }

    /**
     * The split legacy intended and then broke: its edit screen accepted
     * either right, so a view-only user could post a create, an update or a
     * delete to it.
     */
    public function test_the_view_ability_alone_cannot_write()
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewKeywords);
        $keyword = Keyword::factory()->for($project)->create();

        $this->actingAs($user)
            ->post(route('keywords.store', $project), ['name' => 'smoke'])
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('keywords.update', $keyword), ['name' => 'renamed'])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('keywords.destroy', $keyword))
            ->assertForbidden();

        $this->assertSame(1, Keyword::query()->count());
    }

    /**
     * Keywords are project-scoped, so managing one project's vocabulary must
     * not reach another's.
     */
    public function test_managing_one_project_does_not_reach_another()
    {
        $ours = TestProject::factory()->create();
        $theirs = TestProject::factory()->create();
        $user = $this->userWhoCan($ours, Ability::ManageKeywords);
        $keyword = Keyword::factory()->for($theirs)->create();

        $this->actingAs($user)
            ->post(route('keywords.store', $theirs), ['name' => 'smoke'])
            ->assertForbidden();

        $this->actingAs($user)
            ->delete(route('keywords.destroy', $keyword))
            ->assertForbidden();
    }

    private function caseIn(TestProject $project): TestCaseModel
    {
        return TestCaseModel::factory()
            ->withVersion()
            ->create(['test_project_id' => $project->id]);
    }
}
