<?php

namespace App\Http\Requests\Api\V1;

use App\Actions\Executions\ResolveReportedExecution;
use App\Enums\ExecutionStatus;
use App\Models\Build;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StoreExecutionRequest extends FormRequest
{
    /**
     * @var array{item: TestPlanItem, build: Build, attributes: array{status: ExecutionStatus, notes: string|null, duration: string|null, steps: list<array{test_case_step_id: int, status: ExecutionStatus, notes: string|null}>}, complete: bool}|null
     */
    private ?array $resolved = null;

    protected function prepareForValidation(): void
    {
        if (! $this->exists('complete')) {
            $this->merge(['complete' => true]);

            return;
        }

        $this->merge([
            'complete' => $this->boolean('complete'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'test_plan_item_id' => ['required_without:full_external_id', 'nullable', 'integer'],
            'full_external_id' => ['required_without:test_plan_item_id', 'nullable', 'string', 'max:64'],
            'platform' => ['nullable', 'string', 'max:255'],
            'build_id' => ['required_without:build', 'nullable', 'integer'],
            'build' => ['required_without:build_id', 'nullable', 'string', 'max:255'],
            'status' => ['required', Rule::enum(ExecutionStatus::class)],
            'notes' => ['nullable', 'string'],
            'duration' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'complete' => ['boolean'],
            'steps' => ['nullable', 'array'],
            'steps.*.test_case_step_id' => ['required', 'integer', 'exists:test_case_steps,id'],
            'steps.*.status' => ['required', Rule::enum(ExecutionStatus::class)],
            'steps.*.notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function planItem(): TestPlanItem
    {
        return $this->resolved()['item'];
    }

    /**
     * @throws ValidationException
     */
    public function build(): Build
    {
        return $this->resolved()['build'];
    }

    /**
     * @return array{status: ExecutionStatus, notes: string|null, duration: string|null, steps: list<array{test_case_step_id: int, status: ExecutionStatus, notes: string|null}>}
     *
     * @throws ValidationException
     */
    public function executionAttributes(): array
    {
        return $this->resolved()['attributes'];
    }

    public function shouldComplete(): bool
    {
        return $this->resolved()['complete'];
    }

    /**
     * @return array{item: TestPlanItem, build: Build, attributes: array{status: ExecutionStatus, notes: string|null, duration: string|null, steps: list<array{test_case_step_id: int, status: ExecutionStatus, notes: string|null}>}, complete: bool}
     *
     * @throws ValidationException
     */
    private function resolved(): array
    {
        return $this->resolved ??= app(ResolveReportedExecution::class)($this->routePlan(), [
            'test_plan_item_id' => $this->input('test_plan_item_id'),
            'full_external_id' => $this->input('full_external_id'),
            'platform' => $this->input('platform'),
            'build_id' => $this->input('build_id'),
            'build' => $this->input('build'),
            'status' => $this->input('status'),
            'notes' => $this->input('notes'),
            'duration' => $this->input('duration'),
            'complete' => $this->input('complete'),
            'steps' => $this->input('steps', []),
        ]);
    }

    private function routePlan(): TestPlan
    {
        $plan = $this->route('testPlan');

        abort_unless($plan instanceof TestPlan, 404);

        return $plan;
    }
}
