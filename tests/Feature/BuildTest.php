<?php

namespace Tests\Feature;

use App\Models\Build;
use App\Models\TestPlan;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BuildTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_build_belongs_to_one_plan()
    {
        $plan = TestPlan::factory()->create();
        $build = Build::factory()->for($plan)->named('1.2.0')->create();

        $this->assertTrue($build->testPlan->is($plan));
        $this->assertTrue($plan->builds->contains($build));
        $this->assertSame('1.2.0', $plan->builds->first()->name);
    }

    public function test_build_names_are_unique_within_a_plan()
    {
        $plan = TestPlan::factory()->create();
        Build::factory()->for($plan)->named('1.2.0')->create();

        $this->expectException(QueryException::class);

        Build::factory()->for($plan)->named('1.2.0')->create();
    }

    public function test_build_names_collide_across_case_inside_one_plan()
    {
        $plan = TestPlan::factory()->create();
        Build::factory()->for($plan)->named('Release')->create();

        $this->expectException(QueryException::class);

        Build::factory()->for($plan)->named('release')->create();
    }

    public function test_two_plans_may_each_have_a_build_of_the_same_name()
    {
        $first = Build::factory()->named('1.2.0')->create();
        $second = Build::factory()->named('1.2.0')->create();

        $this->assertFalse($first->testPlan->is($second->testPlan));
        $this->assertSame('1.2.0', $second->name);
    }

    public function test_a_build_starts_active_and_open()
    {
        $build = Build::factory()->create();

        $this->assertTrue($build->is_active);
        $this->assertTrue($build->is_open);
    }

    public function test_a_closed_build_stays_readable()
    {
        $build = Build::factory()->closed()->create();

        $this->assertFalse($build->is_open);
        $this->assertTrue($build->is_active);
    }

    public function test_deleting_a_plan_removes_its_builds()
    {
        $plan = TestPlan::factory()->create();
        Build::factory()->for($plan)->create();
        $survivor = Build::factory()->create();

        $plan->delete();

        $this->assertDatabaseCount('builds', 1);
        $this->assertDatabaseHas('builds', ['id' => $survivor->id]);
    }

    public function test_deleting_the_author_leaves_the_build()
    {
        $author = User::factory()->create();
        $build = Build::factory()->create(['author_id' => $author->id]);

        $author->delete();

        $this->assertTrue($build->fresh()->exists);
        $this->assertNull($build->fresh()->author_id);
    }
}
