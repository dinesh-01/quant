<?php

namespace Tests\Feature\Planning;

use App\Enums\Ability;
use App\Models\Role;
use App\Models\TestCase;
use App\Models\TestCaseVersion;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\TestSuite;
use App\Models\User;
use Tests\Feature\TestSpecification\InteractsWithSpecificationRoles;

/**
 * Role and fixture setup for planning action tests.
 *
 * Project-role helpers come from InteractsWithSpecificationRoles. Plan writes
 * need a plan-role helper as well, so a test can prove a plan role grants —
 * or withholds — what a project role would have decided.
 */
trait InteractsWithPlanningRoles
{
    use InteractsWithSpecificationRoles;

    protected function userWhoCanOnPlan(TestPlan $plan, Ability ...$abilities): User
    {
        $user = User::factory()->for(Role::factory()->granting())->create();

        $this->assignPlanRole($user, $plan, ...$abilities);

        return $user;
    }

    protected function assignPlanRole(User $user, TestPlan $plan, Ability ...$abilities): void
    {
        $user->planRoles()->attach(
            Role::factory()->granting(...$abilities)->create(),
            ['test_plan_id' => $plan->id],
        );
    }

    protected function versionIn(TestProject $project): TestCaseVersion
    {
        $suite = TestSuite::factory()->for($project)->create();
        $case = TestCase::factory()->for($suite, 'testSuite')->create();

        return TestCaseVersion::factory()->for($case, 'testCase')->create();
    }
}
