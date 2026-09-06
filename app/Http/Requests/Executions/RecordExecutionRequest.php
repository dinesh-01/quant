<?php

namespace App\Http\Requests\Executions;

use App\Actions\CustomFields\ResolveCustomFields;
use App\Concerns\ValidatesCustomFieldValues;
use App\Enums\ExecutionStatus;
use App\Models\Build;
use App\Models\Execution;
use App\Models\TestPlanItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordExecutionRequest extends FormRequest
{
    use ValidatesCustomFieldValues;

    protected function prepareForValidation(): void
    {
        $this->merge([
            'complete' => $this->boolean('complete'),
        ]);
        $this->normaliseCustomFieldInput();
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'build_id' => ['required', 'integer', 'exists:builds,id'],
            'status' => ['required', Rule::enum(ExecutionStatus::class)],
            'notes' => ['nullable', 'string'],
            'duration' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'complete' => ['boolean'],
            'steps' => ['nullable', 'array'],
            'steps.*.test_case_step_id' => ['required', 'integer', 'exists:test_case_steps,id'],
            'steps.*.status' => ['required', Rule::enum(ExecutionStatus::class)],
            'steps.*.notes' => ['nullable', 'string'],
            ...$this->customFieldRules(
                app(ResolveCustomFields::class)->forSubject($this->executionSubject()),
                onExecution: true,
                enforceRequired: $this->boolean('complete'),
            ),
        ];
    }

    private function executionSubject(): Execution
    {
        /** @var TestPlanItem $item */
        $item = $this->route('testPlanItem');
        $item->loadMissing('testPlan.testProject');

        $execution = new Execution;
        $execution->test_plan_id = $item->test_plan_id;
        $execution->setRelation('testPlan', $item->testPlan);

        return $execution;
    }

    public function build(): Build
    {
        return Build::query()->findOrFail($this->integer('build_id'));
    }

    /**
     * @return array{status: ExecutionStatus, notes: string|null, duration: string|null, steps: list<array{test_case_step_id: int, status: ExecutionStatus, notes: string|null}>, custom_fields: array<int|string, mixed>}
     */
    public function executionAttributes(): array
    {
        $steps = [];

        foreach ((array) $this->input('steps', []) as $step) {
            if (! is_array($step)) {
                continue;
            }

            $steps[] = [
                'test_case_step_id' => (int) $step['test_case_step_id'],
                'status' => ExecutionStatus::from((string) $step['status']),
                'notes' => isset($step['notes']) && is_string($step['notes']) && $step['notes'] !== ''
                    ? $step['notes']
                    : null,
            ];
        }

        return [
            'status' => ExecutionStatus::from((string) $this->input('status')),
            'notes' => $this->filled('notes') ? (string) $this->input('notes') : null,
            'duration' => $this->filled('duration') ? (string) $this->input('duration') : null,
            'steps' => $steps,
            'custom_fields' => $this->customFieldAnswers(),
        ];
    }

    public function shouldComplete(): bool
    {
        return $this->boolean('complete');
    }
}
