<?php

namespace Database\Factories;

use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Models\CustomField;
use App\Models\TestProject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CustomField>
 */
class CustomFieldFactory extends Factory
{
    protected $model = CustomField::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        /*
         * One unique word, because `name` is unique application-wide and a
         * repeated one would fail the insert rather than the assertion the test
         * was making.
         */
        $name = $this->faker->unique()->word();

        return [
            'name' => $name,
            'label' => Str::ucfirst($name),
            'type' => CustomFieldType::String,
            'entity_type' => CustomFieldEntity::TestCase,
        ];
    }

    /**
     * A field of a given type, with options when the type needs them.
     *
     * @param  list<string>  $options
     */
    public function ofType(CustomFieldType $type, array $options = []): static
    {
        return $this->state(fn (): array => [
            'type' => $type,
            'options' => $type->hasOptions()
                ? ($options === [] ? ['one', 'two', 'three'] : $options)
                : null,
        ]);
    }

    public function forEntity(CustomFieldEntity $entity): static
    {
        return $this->state(fn (): array => ['entity_type' => $entity]);
    }

    public function named(string $name): static
    {
        return $this->state(fn (): array => [
            'name' => $name,
            'label' => Str::ucfirst(str_replace('_', ' ', $name)),
        ]);
    }

    /**
     * Enable the field in a project once it has been created, which is what
     * every test that renders or saves a value needs.
     */
    public function enabledIn(
        TestProject $project,
        bool $requiredOnDesign = false,
        int $sortOrder = 0,
        bool $requiredOnExecution = false,
    ): static {
        return $this->afterCreating(function (CustomField $field) use ($project, $requiredOnDesign, $sortOrder, $requiredOnExecution): void {
            $field->testProjects()->attach($project, [
                'is_active' => true,
                'sort_order' => $sortOrder,
                'required_on_design' => $requiredOnDesign,
                'required_on_execution' => $requiredOnExecution,
            ]);
        });
    }
}
