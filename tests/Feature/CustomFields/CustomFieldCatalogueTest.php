<?php

namespace Tests\Feature\CustomFields;

use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Models\AuditEvent;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Role;
use App\Models\TestProject;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The application-wide catalogue of definitions.
 */
class CustomFieldCatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_field_can_be_defined()
    {
        $user = $this->administrator();

        $this->actingAs($user)
            ->post(route('custom-fields.store'), [
                'name' => 'browser',
                'label' => 'Browser',
                'type' => CustomFieldType::Dropdown->value,
                'entity_type' => CustomFieldEntity::TestCase->value,
                'options' => ['Chrome', 'Firefox'],
            ])
            ->assertRedirect(route('custom-fields.index'))
            ->assertSessionHasNoErrors();

        $field = CustomField::query()->sole();

        $this->assertSame('browser', $field->name);
        $this->assertSame(CustomFieldType::Dropdown, $field->type);
        $this->assertSame(CustomFieldEntity::TestCase, $field->entity_type);
        $this->assertSame(['Chrome', 'Firefox'], $field->options());
    }

    /**
     * The catalogue is not project-scoped, so a project role — however
     * generous — grants nothing here. Editing a definition reaches every
     * project that has it enabled, which is not one project lead's decision.
     */
    public function test_a_project_role_does_not_reach_the_catalogue()
    {
        $project = TestProject::factory()->create();

        $user = User::factory()->for(Role::factory()->granting())->create();
        $user->projectRoles()->attach(
            Role::factory()->granting(Ability::ManageCustomFields, Ability::AssignCustomFields)->create(),
            ['test_project_id' => $project->id],
        );

        $this->actingAs($user)
            ->post(route('custom-fields.store'), $this->definition())
            ->assertForbidden();

        $this->assertSame(0, CustomField::query()->count());
    }

    public function test_someone_who_may_only_look_cannot_define_a_field()
    {
        $user = User::factory()
            ->for(Role::factory()->granting(Ability::ViewCustomFields), 'role')
            ->create();

        $this->actingAs($user)->get(route('custom-fields.index'))->assertOk();
        $this->actingAs($user)->get(route('custom-fields.create'))->assertForbidden();
        $this->actingAs($user)
            ->post(route('custom-fields.store'), $this->definition())
            ->assertForbidden();
    }

    public function test_names_are_unique_across_the_application()
    {
        CustomField::factory()->named('browser')->create();

        $this->actingAs($this->administrator())
            ->post(route('custom-fields.store'), $this->definition(['name' => 'browser']))
            ->assertSessionHasErrors('name');

        $this->assertSame(1, CustomField::query()->count());
    }

    /**
     * The name travels with exported values, so it is kept to characters that
     * survive being an identifier elsewhere. The label carries the readable
     * text.
     */
    public function test_a_name_is_restricted_to_identifier_characters()
    {
        $this->actingAs($this->administrator())
            ->post(route('custom-fields.store'), $this->definition(['name' => 'browser version!']))
            ->assertSessionHasErrors('name');
    }

    public function test_a_field_with_options_needs_at_least_one()
    {
        $this->actingAs($this->administrator())
            ->post(route('custom-fields.store'), [
                'name' => 'browser',
                'label' => 'Browser',
                'type' => CustomFieldType::Dropdown->value,
                'entity_type' => CustomFieldEntity::TestCase->value,
            ])
            ->assertSessionHasErrors('options');
    }

    /**
     * Legacy stored `valid_regexp` against every type and read it for none.
     * Here it is refused where it would mean nothing, so a pattern on screen is
     * a pattern that is enforced.
     */
    public function test_a_pattern_is_refused_on_a_type_that_cannot_use_one()
    {
        $this->actingAs($this->administrator())
            ->post(route('custom-fields.store'), $this->definition([
                'type' => CustomFieldType::Numeric->value,
                'pattern' => '/^[0-9]+$/',
            ]))
            ->assertSessionHasErrors('pattern');
    }

    public function test_a_pattern_that_does_not_compile_is_refused()
    {
        $this->actingAs($this->administrator())
            ->post(route('custom-fields.store'), $this->definition(['pattern' => '^[A-Z]{2}$']))
            ->assertSessionHasErrors('pattern');

        $this->assertSame(0, CustomField::query()->count());
    }

    public function test_a_default_value_has_to_be_one_of_the_options()
    {
        $this->actingAs($this->administrator())
            ->post(route('custom-fields.store'), [
                'name' => 'browser',
                'label' => 'Browser',
                'type' => CustomFieldType::Dropdown->value,
                'entity_type' => CustomFieldEntity::TestCase->value,
                'options' => ['Chrome'],
                'default_value' => 'Safari',
            ])
            ->assertSessionHasErrors('default_value');
    }

    public function test_a_field_can_be_relabelled()
    {
        $field = CustomField::factory()->named('browser')->create();

        $this->actingAs($this->administrator())
            ->put(route('custom-fields.update', $field), $this->definition([
                'name' => 'browser',
                'label' => 'Browser under test',
            ]))
            ->assertRedirect(route('custom-fields.index'))
            ->assertSessionHasNoErrors();

        $this->assertSame('Browser under test', $field->refresh()->label);
    }

    /**
     * Answers already stored were checked against the definition as it read at
     * the time, so reinterpreting them under another type would be a guess. The
     * label and the rest stay editable.
     */
    public function test_the_type_is_fixed_once_an_answer_exists()
    {
        $field = CustomField::factory()->named('browser')->create();
        $suite = TestSuite::factory()->create();

        CustomFieldValue::factory()->about($suite)->answering($field, 'Chrome')->create();

        $this->actingAs($this->administrator())
            ->put(route('custom-fields.update', $field), $this->definition([
                'name' => 'browser',
                'type' => CustomFieldType::Numeric->value,
            ]))
            ->assertSessionHasErrors('type');

        $this->assertSame(CustomFieldType::String, $field->refresh()->type);
    }

    public function test_deleting_a_field_takes_its_answers_with_it()
    {
        $field = CustomField::factory()->named('browser')->create();
        $suite = TestSuite::factory()->create();

        CustomFieldValue::factory()->about($suite)->answering($field, 'Chrome')->create();

        $this->actingAs($this->administrator())
            ->delete(route('custom-fields.destroy', $field))
            ->assertRedirect(route('custom-fields.index'));

        $this->assertSame(0, CustomField::query()->count());
        $this->assertSame(0, CustomFieldValue::query()->count());
    }

    public function test_deleting_a_field_records_what_it_cost()
    {
        $project = TestProject::factory()->create();
        $field = CustomField::factory()->named('browser')->enabledIn($project)->create();
        $suite = TestSuite::factory()->for($project)->create();

        CustomFieldValue::factory()->about($suite)->answering($field, 'Chrome')->create();

        $this->actingAs($this->administrator())
            ->delete(route('custom-fields.destroy', $field))
            ->assertSessionHasNoErrors();

        $event = AuditEvent::query()->where('action', AuditAction::CustomFieldDeleted->value)->sole();

        $this->assertSame('browser', $event->properties['name']);
        $this->assertSame(1, $event->properties['projects']);
        $this->assertSame(1, $event->properties['answers']);
    }

    private function administrator(): User
    {
        return User::factory()
            ->for(Role::factory()->granting(Ability::ViewCustomFields, Ability::ManageCustomFields), 'role')
            ->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function definition(array $overrides = []): array
    {
        return [
            'name' => 'notes_field',
            'label' => 'Notes',
            'type' => CustomFieldType::String->value,
            'entity_type' => CustomFieldEntity::TestSuite->value,
            ...$overrides,
        ];
    }
}
