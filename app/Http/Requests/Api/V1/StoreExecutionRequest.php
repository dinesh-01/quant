<?php

namespace App\Http\Requests\Api\V1;

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
        $plan = $this->routePlan();

        if ($this->filled('test_plan_item_id')) {
            $item = TestPlanItem::query()
                ->where('test_plan_id', $plan->getKey())
                ->whereKey($this->integer('test_plan_item_id'))
                ->first();

            if ($item === null) {
                throw ValidationException::withMessages([
                    'test_plan_item_id' => 'That plan item is not on this plan.',
                ]);
            }

            return $item;
        }

        return $this->itemFromExternalId($plan);
    }

    /**
     * @throws ValidationException
     */
    public function build(): Build
    {
        $plan = $this->routePlan();

        $query = Build::query()->where('test_plan_id', $plan->getKey());

        $build = $this->filled('build_id')
            ? $query->whereKey($this->integer('build_id'))->first()
            : $query->where('name', (string) $this->input('build'))->first();

        if ($build === null) {
            throw ValidationException::withMessages([
                $this->filled('build_id') ? 'build_id' : 'build' => 'That build does not belong to this plan.',
            ]);
        }

        return $build;
    }

    /**
     * @return array{status: ExecutionStatus, notes: string|null, duration: string|null, steps: list<array{test_case_step_id: int, status: ExecutionStatus, notes: string|null}>}
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
        ];
    }

    public function shouldComplete(): bool
    {
        return $this->boolean('complete');
    }

    /**
     * @throws ValidationException
     */
    private function itemFromExternalId(TestPlan $plan): TestPlanItem
    {
        $externalId = (string) $this->input('full_external_id');

        if (preg_match('/^([A-Za-z0-9]+)-(\d+)$/', $externalId, $matches) !== 1) {
            throw ValidationException::withMessages([
                'full_external_id' => 'Use the PREFIX-N identifier, for example QA-12.',
            ]);
        }

        $items = TestPlanItem::query()
            ->where('test_plan_id', $plan->getKey())
            ->whereHas('testCaseVersion.testCase', function ($query) use ($matches): void {
                $query->where('external_id', (int) $matches[2])
                    ->whereHas('testProject', function ($project) use ($matches): void {
                        $project->where('prefix', $matches[1]);
                    });
            })
            ->with('platform')
            ->get();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages([
                'full_external_id' => 'That test case is not on this plan.',
            ]);
        }

        if ($items->count() === 1) {
            return $items->firstOrFail();
        }

        $platform = $this->input('platform');

        if (! is_string($platform) || $platform === '') {
            throw ValidationException::withMessages([
                'platform' => 'This case is on more than one platform. Name the platform.',
            ]);
        }

        $matched = $items->first(
            fn (TestPlanItem $item): bool => $item->platform?->name === $platform,
        );

        if ($matched === null) {
            throw ValidationException::withMessages([
                'platform' => 'That platform is not linked to this case on this plan.',
            ]);
        }

        return $matched;
    }

    private function routePlan(): TestPlan
    {
        $plan = $this->route('testPlan');

        abort_unless($plan instanceof TestPlan, 404);

        return $plan;
    }
}
