<?php

namespace Database\Factories;

use App\Enums\IssueTrackerType;
use App\Models\IssueTracker;
use App\Models\TestProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IssueTracker>
 */
class IssueTrackerFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'test_project_id' => TestProject::factory(),
            'name' => 'Jira',
            'type' => IssueTrackerType::Jira,
            'base_url' => 'https://acme.atlassian.net',
            'settings' => [
                'project_key' => 'PAY',
                'issue_type' => 'Bug',
                'email' => 'qa@example.com',
                'api_token' => 'jira-token',
                'proxy' => null,
            ],
            'is_enabled' => true,
        ];
    }

    public function disabled(): static
    {
        return $this->state(['is_enabled' => false]);
    }

    public function withoutToken(): static
    {
        return $this->state(fn (array $attributes): array => [
            'settings' => array_merge($attributes['settings'] ?? [], [
                'api_token' => '',
            ]),
        ]);
    }
}
