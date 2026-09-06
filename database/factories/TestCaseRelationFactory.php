<?php

namespace Database\Factories;

use App\Enums\TestCaseRelationType;
use App\Models\TestCase;
use App\Models\TestCaseRelation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestCaseRelation>
 */
class TestCaseRelationFactory extends Factory
{
    /**
     * The destination is created in the source's project so a factory-built
     * row cannot cross projects — the schema cannot enforce that, because
     * both foreign keys only name the cases table.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_id' => TestCase::factory(),
            'destination_id' => function (array $attributes): int {
                $source = TestCase::query()->whereKey($attributes['source_id'])->firstOrFail();

                return TestCase::factory()->create([
                    'test_project_id' => $source->test_project_id,
                    'test_suite_id' => $source->test_suite_id,
                ])->id;
            },
            'type' => TestCaseRelationType::Related,
            'author_id' => null,
        ];
    }
}
