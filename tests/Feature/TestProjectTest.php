<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_test_project_prefixes_are_unique()
    {
        TestProject::factory()->create(['prefix' => 'QUANTA']);

        $this->expectException(QueryException::class);

        TestProject::factory()->create(['prefix' => 'QUANTA']);
    }

    public function test_test_plan_names_are_unique_within_a_test_project()
    {
        $project = TestProject::factory()->create();
        TestPlan::factory()->for($project)->create(['name' => 'Release 1.0']);
        TestPlan::factory()->create(['name' => 'Release 1.0']);

        $this->expectException(QueryException::class);

        TestPlan::factory()->for($project)->create(['name' => 'Release 1.0']);
    }

    public function test_deleting_a_test_project_removes_its_plans_and_role_assignments()
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = User::factory()->create();
        $role = Role::factory()->create();
        $user->projectRoles()->attach($role, ['test_project_id' => $project->id]);
        $user->planRoles()->attach($role, ['test_plan_id' => $plan->id]);

        $project->delete();

        $this->assertDatabaseEmpty('test_plans');
        $this->assertDatabaseEmpty('test_project_user');
        $this->assertDatabaseEmpty('test_plan_user');
    }

    public function test_deleting_a_role_leaves_its_holders_without_a_global_role()
    {
        $role = Role::factory()->create();
        $user = User::factory()->for($role)->create();

        $role->delete();

        $this->assertNull($user->refresh()->role_id);
    }
}
