<?php

namespace App\Actions\Milestones;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\Milestone;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Edits a plan milestone.
 */
final class UpdateMilestone
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, target_date: string, start_date?: string|null, high_percent?: int, medium_percent?: int, low_percent?: int}  $attributes
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $user, Milestone $milestone, array $attributes): Milestone
    {
        $milestone->loadMissing('testPlan');

        Gate::forUser($user)->authorize(Ability::ManageMilestones->value, $milestone->testPlan);

        $name = trim($attributes['name']);

        $taken = Milestone::query()
            ->where('test_plan_id', $milestone->test_plan_id)
            ->where('name', $name)
            ->whereKeyNot($milestone->getKey())
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'name' => 'That milestone name is already used on this plan.',
            ]);
        }

        $milestone->fill([
            ...$attributes,
            'name' => $name,
        ]);

        $properties = $this->audit->changes($milestone);
        $milestone->save();

        if ($properties !== []) {
            $this->audit->record(AuditAction::MilestoneUpdated, $user, $milestone->testPlan, [
                'milestone_id' => $milestone->id,
                ...$properties,
            ]);
        }

        return $milestone;
    }
}
