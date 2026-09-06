<?php

namespace App\Actions\TestPlans;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Creates a test plan inside a project.
 *
 * Scoped to the project, because there is no plan yet to resolve a plan role
 * against. Every other plan action scopes to the plan itself.
 */
final class CreateTestPlan
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, description: string|null, is_active: bool, is_open: bool, is_public: bool}  $attributes
     *
     * @throws AuthorizationException
     */
    public function __invoke(User $user, TestProject $project, array $attributes): TestPlan
    {
        Gate::forUser($user)->authorize(Ability::CreateTestPlans->value, $project);

        $plan = new TestPlan;
        $plan->fill($attributes);
        $plan->testProject()->associate($project);

        $properties = $this->audit->changes($plan);

        $plan->save();

        $this->audit->record(AuditAction::TestPlanCreated, $user, $plan, $properties);

        return $plan;
    }
}
