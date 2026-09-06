<?php

namespace Database\Factories;

use App\Enums\CodeTrackerType;
use App\Models\CodeTracker;
use App\Models\TestProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CodeTracker>
 */
class CodeTrackerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_project_id' => TestProject::factory(),
            'name' => 'GitHub',
            'type' => CodeTrackerType::Github,
            'base_url' => 'https://api.github.com',
            'project_key' => 'acme/payments',
            'view_url_template' => '{base_url}/{repository}/blob/{branch}/{path}',
            'is_enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(['is_enabled' => false]);
    }
}
