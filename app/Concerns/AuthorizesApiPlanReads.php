<?php

namespace App\Concerns;

use App\Enums\Ability;
use App\Models\TestPlan;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

trait AuthorizesApiPlanReads
{
    /**
     * A plan is readable for automation when the caller can view runs or
     * record them. Viewing the specification alone is not enough.
     *
     * @throws AuthorizationException
     */
    protected function authorizePlanRead(User $user, TestPlan $plan): void
    {
        $plan->loadMissing('testProject');

        if (
            Gate::forUser($user)->allows(Ability::ViewExecutions->value, $plan)
            || Gate::forUser($user)->allows(Ability::ExecuteTests->value, $plan)
        ) {
            return;
        }

        throw new AuthorizationException;
    }
}
