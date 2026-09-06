<?php

namespace App\Actions\Milestones;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\Milestone;
use App\Models\TestPlan;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Adds a dated completion target to a plan.
 */
final class CreateMilestone
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, target_date: string, start_date?: string|null, high_percent?: int, medium_percent?: int, low_percent?: int}  $attributes
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $user, TestPlan $plan, array $attributes): Milestone
    {
        $plan->loadMissing('testProject');

        Gate::forUser($user)->authorize(Ability::ManageMilestones->value, $plan);

        $name = trim($attributes['name']);

        $taken = Milestone::query()
            ->where('test_plan_id', $plan->getKey())
            ->where('name', $name)
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'name' => 'That milestone name is already used on this plan.',
            ]);
        }

        $milestone = new Milestone;
        $milestone->fill([
            ...$attributes,
            'name' => $name,
        ]);
        $milestone->test_plan_id = $plan->getKey();
        $milestone->save();

        $this->audit->record(AuditAction::MilestoneCreated, $user, $plan, [
            'milestone_id' => $milestone->id,
            'name' => $milestone->name,
        ]);

        return $milestone;
    }
}
