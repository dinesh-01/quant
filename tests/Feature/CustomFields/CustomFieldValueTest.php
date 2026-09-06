<?php

namespace Tests\Feature\CustomFields;

use App\Enums\Ability;
use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Enums\TestCaseExecutionType;
use App\Enums\TestCaseImportance;
use App\Enums\TestCaseStatus;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Role;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestCaseVersion;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\TestSpecification\InteractsWithSpecificationRoles;
use Tests\TestCase;

/**
 * Filling fields in, and the checking legacy never did.
 *
 * Legacy validated four of its thirteen types, in the browser only, so a
 * hand-written POST stored whatever it liked and the value was silently clipped
 * to 255 characters. Every rule asserted here is derived from the definition,
 * which is why the same field behaves the same way on all three screens.
 */
class CustomFieldValueTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_an_answer_is_saved_against_a_suite()
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();
        $field = $this->fieldFor($project, CustomFieldEntity::TestSuite);

        $this->actingAs($this->userWhoCan($project, Ability::ManageTestCases))
            ->put(route('test-suites.update', $suite), [
                'name' => $suite->name,
                'custom_fields' => [$field->id => 'Chrome'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Chrome', CustomFieldValue::query()->sole()->value);
    }

    /**
     * Answers hang off the version rather than the case, so an older version
     * keeps what it said while the new one is edited — the same reason
     * attachments live there.
     */
    public function test_an_answer_is_saved_against_a_case_version()
    {
        $project = TestProject::factory()->create();
        $version = $this->version($project);
        $field = $this->fieldFor($project, CustomFieldEntity::TestCase);

        $this->actingAs($this->userWhoCan($project, Ability::ManageTestCases))
            ->put(route('test-case-versions.update', $version), [
                ...$this->versionPayload(),
                'custom_fields' => [$field->id => 'Chrome'],
            ])
            ->assertSessionHasNoErrors();

        $answer = CustomFieldValue::query()->sole();

        $this->assertSame(TestCaseVersion::class, $answer->subject_type);
        $this->assertSame($version->id, $answer->subject_id);
    }

    public function test_an_answer_is_saved_against_a_plan()
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $field = $this->fieldFor($project, CustomFieldEntity::TestPlan);

        $this->actingAs($this->userWhoCan($project, Ability::CreateTestPlans))
            ->put(route('plans.update', $plan), [
                'name' => $plan->name,
                'is_open' => '1',
                'is_public' => '1',
                'custom_fields' => [$field->id => 'Chrome'],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('plans.index', $project));

        $this->assertSame(TestPlan::class, CustomFieldValue::query()->sole()->subject_type);
    }

    /**
     * A private plan is reachable only through a role held on the plan itself,
     * so an edit that makes one private takes the project-role planner's own
     * access with it. The answers therefore have to be written before the plan
     * is, or the same request refuses its own second half.
     */
    public function test_making_a_plan_private_still_saves_its_answers()
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $field = $this->fieldFor($project, CustomFieldEntity::TestPlan);

        $this->actingAs($this->userWhoCan($project, Ability::CreateTestPlans))
            ->put(route('plans.update', $plan), [
                'name' => $plan->name,
                'is_open' => '1',
                'custom_fields' => [$field->id => 'Chrome'],
            ])
            ->assertRedirect(route('plans.index', $project));

        $this->assertFalse($plan->refresh()->is_public);
        $this->assertSame('Chrome', CustomFieldValue::query()->sole()->value);
    }

    /**
     * A role held for one plan governs that plan alone, so the answers on a
     * plan resolve against the plan and not against its project — otherwise a
     * plan role would grant every plan in the project or none of them.
     */
    public function test_a_plan_role_can_fill_in_that_plans_fields()
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $field = $this->fieldFor($project, CustomFieldEntity::TestPlan);

        $user = User::factory()->for(Role::factory()->granting())->create();
        $user->planRoles()->attach(
            Role::factory()->granting(Ability::CreateTestPlans)->create(),
            ['test_plan_id' => $plan->id],
        );

        $this->actingAs($user)
            ->put(route('plans.update', $plan), [
                'name' => $plan->name,
                'is_open' => '1',
                'custom_fields' => [$field->id => 'Chrome'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('Chrome', CustomFieldValue::query()->sole()->value);
    }

    public function test_a_numeric_field_refuses_text()
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();
        $field = $this->fieldFor($project, CustomFieldEntity::TestSuite, CustomFieldType::Numeric);

        $this->actingAs($this->userWhoCan($project, Ability::ManageTestCases))
            ->put(route('test-suites.update', $suite), [
                'name' => $suite->name,
                'custom_fields' => [$field->id => 'abc'],
            ])
            ->assertSessionHasErrors("custom_fields.{$field->id}");

        $this->assertSame(0, CustomFieldValue::query()->count());
    }

    public function test_a_dropdown_refuses_a_value_that_is_not_offered()
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();
        $field = $this->fieldFor($project, CustomFieldEntity::TestSuite, CustomFieldType::Dropdown);

        $this->actingAs($this->userWhoCan($project, Ability::ManageTestCases))
            ->put(route('test-suites.update', $suite), [
                'name' => $suite->name,
                'custom_fields' => [$field->id => 'four'],
            ])
            ->assertSessionHasErrors("custom_fields.{$field->id}");
    }

    public function test_a_pattern_is_enforced_on_save()
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();

        $field = CustomField::factory()
            ->named('ticket')
            ->forEntity(CustomFieldEntity::TestSuite)
            ->enabledIn($project)
            ->create(['pattern' => '/^[A-Z]{2}-[0-9]+$/']);

        $user = $this->userWhoCan($project, Ability::ManageTestCases);

        $this->actingAs($user)
            ->put(route('test-suites.update', $suite), [
                'name' => $suite->name,
                'custom_fields' => [$field->id => 'nope'],
            ])
            ->assertSessionHasErrors("custom_fields.{$field->id}");

        $this->actingAs($user)
            ->put(route('test-suites.update', $suite), [
                'name' => $suite->name,
                'custom_fields' => [$field->id => 'AB-12'],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('AB-12', CustomFieldValue::query()->sole()->value);
    }

    /**
     * `length_max` reached legacy's browser as a `maxlength` attribute and was
     * checked nowhere, so anything longer arrived and was clipped to the
     * column's 255 characters on the way in.
     */
    public function test_a_length_limit_is_enforced_on_save()
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();

        $field = CustomField::factory()
            ->named('short_note')
            ->forEntity(CustomFieldEntity::TestSuite)
            ->enabledIn($project)
            ->create(['maximum_length' => 5]);

        $this->actingAs($this->userWhoCan($project, Ability::ManageTestCases))
            ->put(route('test-suites.update', $suite), [
                'name' => $suite->name,
                'custom_fields' => [$field->id => 'far too long'],
            ])
            ->assertSessionHasErrors("custom_fields.{$field->id}");
    }

    public function test_a_field_the_project_made_mandatory_has_to_be_answered()
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();

        $field = CustomField::factory()
            ->named('browser')
            ->forEntity(CustomFieldEntity::TestSuite)
            ->enabledIn($project, requiredOnDesign: true)
            ->create();

        $this->actingAs($this->userWhoCan($project, Ability::ManageTestCases))
            ->put(route('test-suites.update', $suite), [
                'name' => $suite->name,
                'custom_fields' => [$field->id => ''],
            ])
            ->assertSessionHasErrors("custom_fields.{$field->id}");
    }

    /**
     * The same definition can be mandatory for one team and optional for
     * another, because requiredness is on the project's link rather than on the
     * definition.
     */
    public function test_the_same_field_is_optional_in_a_project_that_did_not_require_it()
    {
        $field = CustomField::factory()
            ->named('browser')
            ->forEntity(CustomFieldEntity::TestSuite)
            ->create();

        $strict = TestProject::factory()->create();
        $relaxed = TestProject::factory()->create();

        $field->testProjects()->attach($strict, ['is_active' => true, 'required_on_design' => true]);
        $field->testProjects()->attach($relaxed, ['is_active' => true]);

        $suite = TestSuite::factory()->for($relaxed)->create();

        $this->actingAs($this->userWhoCan($relaxed, Ability::ManageTestCases))
            ->put(route('test-suites.update', $suite), [
                'name' => $suite->name,
                'custom_fields' => [$field->id => ''],
            ])
            ->assertSessionHasNoErrors();
    }

    /**
     * Legacy's behaviour, kept on purpose: an empty answer removes the row, so
     * "nobody has said" and "somebody said nothing" are one state rather than
     * two that no screen could tell apart.
     */
    public function test_clearing_an_answer_removes_it()
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();
        $field = $this->fieldFor($project, CustomFieldEntity::TestSuite);

        CustomFieldValue::factory()->about($suite)->answering($field, 'Chrome')->create();

        $this->actingAs($this->userWhoCan($project, Ability::ManageTestCases))
            ->put(route('test-suites.update', $suite), [
                'name' => $suite->name,
                'custom_fields' => [$field->id => ''],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, CustomFieldValue::query()->count());
    }

    /**
     * Legacy joined several chosen values with `|`, which meant a value
     * containing a pipe could not be stored and a stored value could not be
     * split reliably. JSON has neither problem.
     */
    public function test_several_chosen_values_are_stored_as_a_list()
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();
        $field = $this->fieldFor($project, CustomFieldEntity::TestSuite, CustomFieldType::MultiSelect);

        $this->actingAs($this->userWhoCan($project, Ability::ManageTestCases))
            ->put(route('test-suites.update', $suite), [
                'name' => $suite->name,
                'custom_fields' => [$field->id => ['one', 'three']],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(['one', 'three'], CustomFieldValue::query()->sole()->answer());
    }

    /**
     * A form cannot post an empty array, so the screens send one empty entry to
     * say the field was on the form with nothing chosen. That has to clear the
     * answer rather than store `['']`.
     */
    public function test_unticking_every_box_clears_the_answer()
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();
        $field = $this->fieldFor($project, CustomFieldEntity::TestSuite, CustomFieldType::MultiSelect);

        CustomFieldValue::factory()->about($suite)->answering($field, ['one'])->create();

        $this->actingAs($this->userWhoCan($project, Ability::ManageTestCases))
            ->put(route('test-suites.update', $suite), [
                'name' => $suite->name,
                'custom_fields' => [$field->id => ['']],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, CustomFieldValue::query()->count());
    }

    /**
     * A field enabled for another project, or switched off in this one, is not
     * one this screen may write — and a wrong id is an error rather than an
     * answer that silently never appears.
     */
    public function test_a_field_this_project_does_not_have_is_refused()
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();

        $elsewhere = CustomField::factory()
            ->named('browser')
            ->forEntity(CustomFieldEntity::TestSuite)
            ->enabledIn(TestProject::factory()->create())
            ->create();

        $this->actingAs($this->userWhoCan($project, Ability::ManageTestCases))
            ->put(route('test-suites.update', $suite), [
                'name' => $suite->name,
                'custom_fields' => [$elsewhere->id => 'Chrome'],
            ])
            ->assertSessionHasErrors('custom_fields');

        $this->assertSame(0, CustomFieldValue::query()->count());
    }

    public function test_a_field_defined_against_another_kind_of_thing_is_refused()
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();
        $forPlans = $this->fieldFor($project, CustomFieldEntity::TestPlan);

        $this->actingAs($this->userWhoCan($project, Ability::ManageTestCases))
            ->put(route('test-suites.update', $suite), [
                'name' => $suite->name,
                'custom_fields' => [$forPlans->id => 'Chrome'],
            ])
            ->assertSessionHasErrors('custom_fields');
    }

    /**
     * Omitting the key means "unchanged", not "cleared": the payload only
     * carries what a screen rendered, and an older client that knows nothing
     * about custom fields must not wipe them.
     */
    public function test_omitting_the_fields_entirely_changes_nothing()
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();
        $field = $this->fieldFor($project, CustomFieldEntity::TestSuite);

        CustomFieldValue::factory()->about($suite)->answering($field, 'Chrome')->create();

        $this->actingAs($this->userWhoCan($project, Ability::ManageTestCases))
            ->put(route('test-suites.update', $suite), ['name' => 'Renamed'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Chrome', CustomFieldValue::query()->sole()->value);
    }

    /**
     * A frozen version refuses the whole request, answers included, rather than
     * taking the fields and rejecting the rest. That is why the answers are
     * saved after the version and not before it.
     */
    public function test_a_frozen_version_takes_no_answers()
    {
        $project = TestProject::factory()->create();
        $version = $this->version($project);

        /* Not fillable, so freezing is done here as `FreezeTestCaseVersion` does it. */
        $version->is_open = false;
        $version->save();

        $field = $this->fieldFor($project, CustomFieldEntity::TestCase);

        $this->actingAs($this->userWhoCan($project, Ability::ManageTestCases))
            ->put(route('test-case-versions.update', $version), [
                ...$this->versionPayload(),
                'custom_fields' => [$field->id => 'Chrome'],
            ])
            ->assertSessionHasErrors('version');

        $this->assertSame(0, CustomFieldValue::query()->count());
    }

    public function test_the_screen_offers_the_fields_with_the_answers_already_given()
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();
        $field = $this->fieldFor($project, CustomFieldEntity::TestSuite);

        CustomFieldValue::factory()->about($suite)->answering($field, 'Chrome')->create();

        $this->actingAs($this->userWhoCan($project, Ability::ViewTestCases))
            ->get(route('specification.suites.show', [$project, $suite]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('selected.suite.custom_fields.0.id', $field->id)
                ->where('selected.suite.custom_fields.0.answer', 'Chrome')
                ->where('selected.suite.custom_fields.0.is_required', false)
            );
    }

    /**
     * A suggested value fills the form and nothing else. Storing it on render
     * would make "left alone" and "agreed with the suggestion" the same row.
     */
    public function test_a_suggested_value_is_offered_but_not_stored()
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();

        $field = CustomField::factory()
            ->named('browser')
            ->forEntity(CustomFieldEntity::TestSuite)
            ->enabledIn($project)
            ->create(['default_value' => 'Chrome']);

        $this->actingAs($this->userWhoCan($project, Ability::ViewTestCases))
            ->get(route('specification.suites.show', [$project, $suite]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('selected.suite.custom_fields.0.answer', 'Chrome')
            );

        $this->assertSame(0, CustomFieldValue::query()->count());
    }

    /**
     * A field switched off is off the screens too, so it is neither offered nor
     * writable.
     */
    public function test_a_field_switched_off_is_not_offered()
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();

        $field = CustomField::factory()
            ->named('browser')
            ->forEntity(CustomFieldEntity::TestSuite)
            ->create();

        $field->testProjects()->attach($project, ['is_active' => false]);

        $this->actingAs($this->userWhoCan($project, Ability::ViewTestCases))
            ->get(route('specification.suites.show', [$project, $suite]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->count('selected.suite.custom_fields', 0)
            );

        $this->actingAs($this->userWhoCan($project, Ability::ManageTestCases))
            ->put(route('test-suites.update', $suite), [
                'name' => $suite->name,
                'custom_fields' => [$field->id => 'Chrome'],
            ])
            ->assertSessionHasErrors('custom_fields');
    }

    private function fieldFor(
        TestProject $project,
        CustomFieldEntity $entity,
        CustomFieldType $type = CustomFieldType::String,
    ): CustomField {
        return CustomField::factory()
            ->forEntity($entity)
            ->ofType($type)
            ->enabledIn($project)
            ->create();
    }

    private function version(TestProject $project): TestCaseVersion
    {
        $suite = TestSuite::factory()->for($project)->create();
        $case = TestCaseModel::factory()->for($project)->for($suite)->create();

        return TestCaseVersion::factory()->for($case, 'testCase')->create();
    }

    /**
     * @return array<string, string>
     */
    private function versionPayload(): array
    {
        return [
            'status' => TestCaseStatus::Draft->value,
            'importance' => TestCaseImportance::Medium->value,
            'execution_type' => TestCaseExecutionType::Manual->value,
        ];
    }
}
