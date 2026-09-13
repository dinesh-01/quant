<?php

namespace App\Actions\Executions;

use App\Enums\ExecutionStatus;
use App\Models\Build;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use Illuminate\Validation\ValidationException;

/**
 * Turns a CI payload into a plan item, build, and record attributes.
 *
 * Shared by the synchronous single-result API and the queued batch ingest so
 * PREFIX-N lookup, platform disambiguation, and build resolution stay in one
 * place. $errorPrefix scopes bulk validation keys (results.0.).
 */
final class ResolveReportedExecution
{
    /**
     * @param  array<string, mixed>  $input
     * @return array{item: TestPlanItem, build: Build, attributes: array{status: ExecutionStatus, notes: string|null, duration: string|null, steps: list<array{test_case_step_id: int, status: ExecutionStatus, notes: string|null}>}, complete: bool}
     *
     * @throws ValidationException
     */
    public function __invoke(TestPlan $plan, array $input, string $errorPrefix = ''): array
    {
        return [
            'item' => $this->planItem($plan, $input, $errorPrefix),
            'build' => $this->build($plan, $input, $errorPrefix),
            'attributes' => $this->attributes($input),
            'complete' => $this->complete($input),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    private function planItem(TestPlan $plan, array $input, string $errorPrefix): TestPlanItem
    {
        if ($this->filled($input, 'test_plan_item_id')) {
            $item = TestPlanItem::query()
                ->where('test_plan_id', $plan->getKey())
                ->whereKey((int) $input['test_plan_item_id'])
                ->first();

            if ($item === null) {
                throw ValidationException::withMessages([
                    $errorPrefix.'test_plan_item_id' => 'That plan item is not on this plan.',
                ]);
            }

            return $item;
        }

        return $this->itemFromExternalId($plan, $input, $errorPrefix);
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    private function build(TestPlan $plan, array $input, string $errorPrefix): Build
    {
        $query = Build::query()->where('test_plan_id', $plan->getKey());

        $build = $this->filled($input, 'build_id')
            ? $query->whereKey((int) $input['build_id'])->first()
            : $query->where('name', (string) ($input['build'] ?? ''))->first();

        if ($build === null) {
            throw ValidationException::withMessages([
                $errorPrefix.($this->filled($input, 'build_id') ? 'build_id' : 'build') => 'That build does not belong to this plan.',
            ]);
        }

        return $build;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{status: ExecutionStatus, notes: string|null, duration: string|null, steps: list<array{test_case_step_id: int, status: ExecutionStatus, notes: string|null}>}
     */
    private function attributes(array $input): array
    {
        $steps = [];

        foreach ((array) ($input['steps'] ?? []) as $step) {
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
            'status' => ExecutionStatus::from((string) $input['status']),
            'notes' => $this->filled($input, 'notes') ? (string) $input['notes'] : null,
            'duration' => $this->filled($input, 'duration') ? (string) $input['duration'] : null,
            'steps' => $steps,
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function complete(array $input): bool
    {
        if (! array_key_exists('complete', $input)) {
            return true;
        }

        return filter_var($input['complete'], FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    private function itemFromExternalId(TestPlan $plan, array $input, string $errorPrefix): TestPlanItem
    {
        $externalId = (string) ($input['full_external_id'] ?? '');

        if (preg_match('/^([A-Za-z0-9]+)-(\d+)$/', $externalId, $matches) !== 1) {
            throw ValidationException::withMessages([
                $errorPrefix.'full_external_id' => 'Use the PREFIX-N identifier, for example QA-12.',
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
                $errorPrefix.'full_external_id' => 'That test case is not on this plan.',
            ]);
        }

        if ($items->count() === 1) {
            return $items->firstOrFail();
        }

        $platform = $input['platform'] ?? null;

        if (! is_string($platform) || $platform === '') {
            throw ValidationException::withMessages([
                $errorPrefix.'platform' => 'This case is on more than one platform. Name the platform.',
            ]);
        }

        $matched = $items->first(
            fn (TestPlanItem $item): bool => $item->platform?->name === $platform,
        );

        if ($matched === null) {
            throw ValidationException::withMessages([
                $errorPrefix.'platform' => 'That platform is not linked to this case on this plan.',
            ]);
        }

        return $matched;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function filled(array $input, string $key): bool
    {
        if (! array_key_exists($key, $input) || $input[$key] === null) {
            return false;
        }

        return $input[$key] !== '';
    }
}
