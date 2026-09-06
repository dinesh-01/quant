<?php

namespace Database\Factories;

use App\Enums\ExecutionStatus;
use App\Models\Execution;
use App\Models\ExecutionStep;
use App\Models\TestCaseStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExecutionStep>
 */
class ExecutionStepFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'execution_id' => Execution::factory(),
            'test_case_step_id' => TestCaseStep::factory(),
            'sort_order' => 1,
            'status' => ExecutionStatus::NotRun,
            'notes' => null,
        ];
    }
}
