<?php

namespace App\Actions\TesterAssignments;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\TesterAssignmentStatus;
use App\Models\Build;
use App\Models\TesterAssignment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Copies testers from one build onto another on the same plan.
 *
 * Existing rows on the target are left alone: the copy fills empty slots so a
 * second press is a no-op rather than doubling assignments. Status resets to
 * open — a new build is a new run, even when the same people are asked.
 */
final class CopyTesterAssignments
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $user, Build $source, Build $target): int
    {
        $source->loadMissing('testPlan');
        $target->loadMissing('testPlan');

        Gate::forUser($user)->authorize(Ability::AssignTesters->value, $source->testPlan);

        if ($source->getKey() === $target->getKey()) {
            throw ValidationException::withMessages([
                'target_build' => 'Choose a different build to copy onto.',
            ]);
        }

        if ($source->test_plan_id !== $target->test_plan_id) {
            throw ValidationException::withMessages([
                'target_build' => 'Both builds must belong to the same plan.',
            ]);
        }

        return DB::transaction(function () use ($user, $source, $target): int {
            $existing = TesterAssignment::query()
                ->where('build_id', $target->getKey())
                ->get()
                ->map(fn (TesterAssignment $assignment): string => $assignment->test_plan_item_id.'-'.$assignment->user_id)
                ->all();

            $copied = 0;

            foreach ($source->testerAssignments()->get() as $assignment) {
                $slot = $assignment->test_plan_item_id.'-'.$assignment->user_id;

                if (in_array($slot, $existing, true)) {
                    continue;
                }

                $copy = new TesterAssignment;
                $copy->test_plan_item_id = $assignment->test_plan_item_id;
                $copy->build()->associate($target);
                $copy->user_id = $assignment->user_id;
                $copy->assigner()->associate($user);
                $copy->status = TesterAssignmentStatus::Open;
                $copy->deadline_at = $assignment->deadline_at;
                $copy->save();

                $existing[] = $slot;
                $copied++;
            }

            $this->audit->record(AuditAction::TesterAssignmentsCopied, $user, $source->testPlan, [
                'name' => $source->testPlan->name,
                'source_build' => $source->name,
                'target_build' => $target->name,
                'copied' => $copied,
            ]);

            return $copied;
        });
    }
}
