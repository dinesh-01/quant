<?php

namespace App\Actions\Executions;

use App\Actions\Audit\AuditLogger;
use App\Actions\CustomFields\ResolveCustomFields;
use App\Actions\CustomFields\SaveCustomFieldValues;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\ExecutionStatus;
use App\Models\Build;
use App\Models\CustomField;
use App\Models\Execution;
use App\Models\TestCaseStep;
use App\Models\TesterAssignment;
use App\Models\TestPlanItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Saves a draft run or completes it.
 *
 * One draft per (item, build, tester). Completing freezes the case version
 * number onto the row. A closed plan or build refuses new results.
 */
final class RecordExecution
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly ResolveCustomFields $fields,
        private readonly SaveCustomFieldValues $saveCustomFieldValues,
    ) {}

    /**
     * @param  array{status: ExecutionStatus, notes?: string|null, duration?: string|null, steps?: list<array{test_case_step_id: int, status: ExecutionStatus, notes?: string|null}>, custom_fields?: array<int|string, mixed>}  $attributes
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(
        User $user,
        TestPlanItem $item,
        Build $build,
        array $attributes,
        bool $complete,
    ): Execution {
        $item->loadMissing(['testPlan.testProject', 'testCaseVersion.steps']);
        $plan = $item->testPlan;

        Gate::forUser($user)->authorize(Ability::ExecuteTests->value, $plan);

        if ($build->test_plan_id !== $plan->getKey()) {
            throw ValidationException::withMessages([
                'build_id' => 'That build does not belong to this plan.',
            ]);
        }

        if (! $plan->is_open || ! $build->is_open) {
            throw ValidationException::withMessages([
                'build_id' => 'This plan or build is closed and no longer accepts results.',
            ]);
        }

        if (Gate::forUser($user)->allows(Ability::ExecuteOnlyAssignedTestCases->value, $plan)) {
            $assigned = TesterAssignment::query()
                ->where('test_plan_item_id', $item->getKey())
                ->where('build_id', $build->getKey())
                ->where('user_id', $user->getKey())
                ->exists();

            if (! $assigned) {
                throw ValidationException::withMessages([
                    'item' => 'You can only run test cases assigned to you on this build.',
                ]);
            }
        }

        if ($complete && $attributes['status'] === ExecutionStatus::NotRun) {
            throw ValidationException::withMessages([
                'status' => 'Choose a result before completing the run.',
            ]);
        }

        return DB::transaction(function () use ($user, $item, $build, $plan, $attributes, $complete): Execution {
            $execution = Execution::query()
                ->where('test_plan_item_id', $item->getKey())
                ->where('build_id', $build->getKey())
                ->where('tester_id', $user->getKey())
                ->where('is_draft', true)
                ->first();

            if ($execution === null) {
                $execution = new Execution;
                $execution->test_plan_id = $plan->getKey();
                $execution->build_id = $build->getKey();
                $execution->test_plan_item_id = $item->getKey();
                $execution->tester_id = $user->getKey();
            }

            $version = $item->testCaseVersion;
            $execution->test_case_version_id = $version->getKey();
            $execution->version = $version->version;
            $execution->status = $attributes['status'];
            $execution->notes = $attributes['notes'] ?? null;
            $execution->duration = $attributes['duration'] ?? null;
            $execution->is_draft = ! $complete;
            $execution->executed_at = $complete ? now() : null;
            $execution->save();
            $execution->setRelation('testPlan', $plan);

            $this->syncSteps($execution, $version->steps, $attributes['steps'] ?? []);

            if ($complete) {
                $this->assertRequiredCustomFields($execution, $attributes['custom_fields'] ?? []);
            }

            if (array_key_exists('custom_fields', $attributes)) {
                ($this->saveCustomFieldValues)($user, $execution, $attributes['custom_fields']);
            }

            $this->audit->record(
                $complete ? AuditAction::ExecutionCompleted : AuditAction::ExecutionSaved,
                $user,
                $plan,
                [
                    'execution_id' => $execution->id,
                    'item_id' => $item->id,
                    'build_id' => $build->id,
                    'status' => $execution->status->value,
                    'version' => $execution->version,
                    'is_draft' => $execution->is_draft,
                ],
            );

            return $execution;
        });
    }

    /**
     * @param  array<int|string, mixed>  $answers
     */
    private function assertRequiredCustomFields(Execution $execution, array $answers): void
    {
        foreach ($this->fields->forSubject($execution) as $field) {
            if (! $field instanceof CustomField || ! $field->isRequiredOnExecution()) {
                continue;
            }

            $answer = $answers[$field->id] ?? $answers[(string) $field->id] ?? null;

            if ($field->type->encode($answer) === null) {
                throw ValidationException::withMessages([
                    "custom_fields.{$field->id}" => __('This field is required to complete the run.'),
                ]);
            }
        }
    }

    /**
     * @param  iterable<int, TestCaseStep>  $caseSteps
     * @param  list<array{test_case_step_id: int, status: ExecutionStatus, notes?: string|null}>  $incoming
     */
    private function syncSteps(Execution $execution, iterable $caseSteps, array $incoming): void
    {
        $byId = [];

        foreach ($incoming as $step) {
            $byId[$step['test_case_step_id']] = $step;
        }

        foreach ($caseSteps as $caseStep) {
            $row = $execution->steps()
                ->where('test_case_step_id', $caseStep->id)
                ->first() ?? $execution->steps()->make();
            $row->test_case_step_id = $caseStep->id;
            $row->sort_order = $caseStep->sort_order;
            $row->status = $byId[$caseStep->id]['status'] ?? ExecutionStatus::NotRun;
            $row->notes = $byId[$caseStep->id]['notes'] ?? null;
            $row->save();
        }
    }
}
