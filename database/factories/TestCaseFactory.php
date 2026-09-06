<?php

namespace Database\Factories;

use App\Actions\TestSpecification\AllocateExternalId;
use App\Models\TestCase;
use App\Models\TestCaseVersion;
use App\Models\TestProject;
use App\Models\TestSuite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestCase>
 */
class TestCaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * The project is derived from the suite the case lands in, and the external
     * id comes from the real allocator so that factory-built and
     * action-built cases in the same project can never collide.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_suite_id' => TestSuite::factory(),
            'test_project_id' => fn (array $attributes): int => TestSuite::query()
                ->whereKey($attributes['test_suite_id'])
                ->value('test_project_id'),
            'external_id' => fn (array $attributes): int => (new AllocateExternalId)(
                TestProject::query()->whereKey($attributes['test_project_id'])->firstOrFail(),
            ),
            'name' => fake()->unique()->sentence(4),
            'sort_order' => 0,
        ];
    }

    /**
     * Create the case with a single version, the state most tests need.
     */
    public function withVersion(): static
    {
        return $this->has(TestCaseVersion::factory(), 'versions');
    }
}
