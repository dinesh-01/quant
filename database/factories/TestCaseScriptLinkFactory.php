<?php

namespace Database\Factories;

use App\Models\TestCaseScriptLink;
use App\Models\TestCaseVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestCaseScriptLink>
 */
class TestCaseScriptLinkFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_case_version_id' => TestCaseVersion::factory(),
            'project_key' => 'acme/payments',
            'repository' => 'qa-scripts',
            'path' => 'spec/login_spec.rb',
            'branch' => 'main',
            'commit' => null,
        ];
    }
}
