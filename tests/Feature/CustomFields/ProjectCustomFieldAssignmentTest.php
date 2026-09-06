<?php

namespace Tests\Feature\CustomFields;

use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\CustomFieldEntity;
use App\Models\AuditEvent;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\TestProject;
use App\Models\TestSuite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\TestSpecification\InteractsWithSpecificationRoles;
use Tests\TestCase;

/**
 * Which of the catalogue's fields one project records.
 *
 * Assignment is project-scoped while the definitions are not, so these tests
 * are about a project lead deciding what their own team fills in without being
 * able to change what a field means for anybody else.
 */
class ProjectCustomFieldAssignmentTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_the_screen_lists_what_is_enabled_and_what_is_available()
    {
        $project = TestProject::factory()->create();
        $enabled = CustomField::factory()->named('browser')->enabledIn($project)->create();
        $spare = CustomField::factory()->named('platform')->create();

        $this->actingAs($this->userWhoCan($project, Ability::AssignCustomFields))
            ->get(route('projects.custom-fields.index', $project))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('custom-fields/project')
                ->where('assigned.0.id', $enabled->id)
                ->where('assigned.0.required_on_design', false)
                ->count('assigned', 1)
                ->where('available.0.id', $spare->id)
                ->count('available', 1)
            );
    }

    public function test_a_field_can_be_enabled_and_made_mandatory()
    {
        $project = TestProject::factory()->create();
        $field = CustomField::factory()->named('browser')->create();

        $this->actingAs($this->userWhoCan($project, Ability::AssignCustomFields))
            ->put(route('projects.custom-fields.update', $project), [
                'fields' => [
                    ['id' => $field->id, 'is_active' => true, 'required_on_design' => true, 'sort_order' => 3],
                ],
            ])
            ->assertRedirect(route('projects.custom-fields.index', $project))
            ->assertSessionHasNoErrors();

        $assigned = $project->customFields()->sole();

        $this->assertTrue($assigned->isActiveIn());
        $this->assertTrue($assigned->isRequiredOnDesign());
        $this->assertSame(3, $assigned->sortOrderIn());
    }

    /**
     * Switching a field off is the reversible half of the pair: the project's
     * screens stop offering it, and everything already recorded stays. Legacy
     * had `show_on_design` and `enable_on_design` for this and used them
     * inconsistently, so a field could be hidden and still required.
     */
    public function test_switching_a_field_off_keeps_the_answers()
    {
        $project = TestProject::factory()->create();
        $field = CustomField::factory()
            ->named('browser')
            ->forEntity(CustomFieldEntity::TestSuite)
            ->enabledIn($project)
            ->create();

        $suite = TestSuite::factory()->for($project)->create();
        CustomFieldValue::factory()->about($suite)->answering($field, 'Chrome')->create();

        $this->actingAs($this->userWhoCan($project, Ability::AssignCustomFields))
            ->put(route('projects.custom-fields.update', $project), [
                'fields' => [['id' => $field->id, 'is_active' => false]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse($project->customFields()->sole()->isActiveIn());
        $this->assertSame(1, CustomFieldValue::query()->count());
    }

    /**
     * Removing it is the other half, and it is destructive: the answers belong
     * to a field that no longer applies here, so they go.
     */
    public function test_removing_a_field_deletes_this_projects_answers_only()
    {
        $field = CustomField::factory()
            ->named('browser')
            ->forEntity(CustomFieldEntity::TestSuite)
            ->create();

        $project = TestProject::factory()->create();
        $other = TestProject::factory()->create();

        $field->testProjects()->attach([$project->id, $other->id], ['is_active' => true]);

        $here = TestSuite::factory()->for($project)->create();
        $elsewhere = TestSuite::factory()->for($other)->create();

        CustomFieldValue::factory()->about($here)->answering($field, 'Chrome')->create();
        $kept = CustomFieldValue::factory()->about($elsewhere)->answering($field, 'Firefox')->create();

        $this->actingAs($this->userWhoCan($project, Ability::AssignCustomFields))
            ->put(route('projects.custom-fields.update', $project), ['fields' => []])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, $project->customFields()->count());
        $this->assertSame(1, $other->customFields()->count());

        $remaining = CustomFieldValue::query()->sole();

        $this->assertSame($kept->id, $remaining->id);
    }

    public function test_the_change_is_recorded_in_the_audit_trail()
    {
        $project = TestProject::factory()->create();
        $added = CustomField::factory()->named('browser')->create();
        $removed = CustomField::factory()->named('platform')->enabledIn($project)->create();

        $user = $this->userWhoCan($project, Ability::AssignCustomFields);

        $this->actingAs($user)
            ->put(route('projects.custom-fields.update', $project), [
                'fields' => [['id' => $added->id]],
            ])
            ->assertSessionHasNoErrors();

        $event = AuditEvent::query()
            ->where('action', AuditAction::ProjectCustomFieldsChanged->value)
            ->sole();

        $this->assertSame($user->id, $event->user_id);
        $this->assertSame([$added->id], $event->properties['added']);
        $this->assertSame([$removed->id], $event->properties['removed']);
    }

    public function test_a_role_without_the_ability_cannot_change_the_assignment()
    {
        $project = TestProject::factory()->create();
        $field = CustomField::factory()->named('browser')->create();

        $user = $this->userWhoCan($project, Ability::ViewTestCases);

        $this->actingAs($user)
            ->get(route('projects.custom-fields.index', $project))
            ->assertForbidden();

        $this->actingAs($user)
            ->put(route('projects.custom-fields.update', $project), [
                'fields' => [['id' => $field->id]],
            ])
            ->assertForbidden();

        $this->assertSame(0, $project->customFields()->count());
    }

    /**
     * The ability is held per project, so holding it in one grants nothing in
     * another.
     */
    public function test_the_ability_does_not_carry_to_another_project()
    {
        $granted = TestProject::factory()->create();
        $other = TestProject::factory()->create();
        $field = CustomField::factory()->named('browser')->create();

        $this->actingAs($this->userWhoCan($granted, Ability::AssignCustomFields))
            ->put(route('projects.custom-fields.update', $other), [
                'fields' => [['id' => $field->id]],
            ])
            ->assertForbidden();
    }

    public function test_an_unknown_field_is_refused()
    {
        $project = TestProject::factory()->create();

        $this->actingAs($this->userWhoCan($project, Ability::AssignCustomFields))
            ->put(route('projects.custom-fields.update', $project), [
                'fields' => [['id' => 9999]],
            ])
            ->assertSessionHasErrors('fields.0.id');
    }

    /**
     * The count on the screen is what a project lead weighs before removing a
     * field, so it has to be this project's answers rather than every one the
     * definition has anywhere.
     */
    public function test_the_answer_count_is_scoped_to_the_project()
    {
        $field = CustomField::factory()
            ->named('browser')
            ->forEntity(CustomFieldEntity::TestSuite)
            ->create();

        $project = TestProject::factory()->create();
        $other = TestProject::factory()->create();

        $field->testProjects()->attach([$project->id, $other->id], ['is_active' => true]);

        CustomFieldValue::factory()
            ->about(TestSuite::factory()->for($project)->create())
            ->answering($field, 'Chrome')
            ->create();

        CustomFieldValue::factory()
            ->about(TestSuite::factory()->for($other)->create())
            ->answering($field, 'Firefox')
            ->create();

        $this->actingAs($this->userWhoCan($project, Ability::AssignCustomFields))
            ->get(route('projects.custom-fields.index', $project))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('assigned.0.answers_count', 1)
            );
    }
}
