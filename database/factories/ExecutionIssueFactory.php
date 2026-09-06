<?php

namespace Database\Factories;

use App\Models\Execution;
use App\Models\ExecutionIssue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExecutionIssue>
 */
class ExecutionIssueFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'execution_id' => Execution::factory(),
            'issue_id' => fake()->bothify('BUG-####'),
        ];
    }
}
