<?php

namespace Database\Factories;

use App\Models\RequirementCoverage;
use App\Models\RequirementVersion;
use App\Models\TestCaseVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequirementCoverage>
 */
class RequirementCoverageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'requirement_version_id' => RequirementVersion::factory(),
            'test_case_version_id' => TestCaseVersion::factory(),
            'author_id' => null,
        ];
    }
}
