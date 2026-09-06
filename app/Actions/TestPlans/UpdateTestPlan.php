<?php

namespace App\Actions\TestPlans;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TestPlan;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Edits a test plan, including opening and closing it for execution.
 *
 * Scoped to the plan rather than to its project. Ability resolution runs plan
 * role, then project role, then global role, so scoping here lets a role held
 * for this one plan grant or withhold its management — which is the point of
 * plan roles existing. Scoping to the project would make them unreachable.
 *
 * Legacy checked `mgt_testplan_create` against the project for both creating
 * and editing, so a plan role could never affect it.
 */
final class UpdateTestPlan
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, description: string|null, is_active: bool, is_open: bool, is_public: bool}  $attributes
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $user, TestPlan $plan, array $attributes): TestPlan
    {
        Gate::forUser($user)->authorize(Ability::CreateTestPlans->value, $plan);

        $plan->fill($attributes);

        $properties = $this->audit->changes($plan);

        $plan->save();

        if ($properties !== []) {
            $this->audit->record(AuditAction::TestPlanUpdated, $user, $plan, $properties);
        }

        return $plan;
    }
}
