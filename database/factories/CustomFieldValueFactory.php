<?php

namespace Database\Factories;

use App\Models\CustomField;
use App\Models\CustomFieldSubject;
use App\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomFieldValue>
 */
class CustomFieldValueFactory extends Factory
{
    protected $model = CustomFieldValue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'custom_field_id' => CustomField::factory(),
            'value' => $this->faker->word(),
        ];
    }

    /**
     * Point the answer at what it is about.
     *
     * Taking the interface rather than a model is deliberate: it is the same
     * guard the write path has, so a factory cannot create a value against
     * something no field could have been defined for.
     */
    public function about(CustomFieldSubject $subject): static
    {
        return $this->state(fn (): array => [
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
        ]);
    }

    /**
     * @param  string|list<string>  $answer
     */
    public function answering(CustomField $field, string|array $answer): static
    {
        return $this->state(fn (): array => [
            'custom_field_id' => $field->id,
            'value' => $field->type->encode($answer),
        ]);
    }
}
