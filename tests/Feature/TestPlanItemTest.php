<?php

namespace Tests\Feature;

use App\Enums\TestCaseUrgency;
use App\Models\Platform;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestCaseVersion;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestProject;
use App\Models\TestSuite;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestPlanItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_item_pins_a_version_onto_a_plan()
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $item = TestPlanItem::factory()->for($plan)->create();

        $this->assertTrue($item->testPlan->is($plan));
        $this->assertTrue($plan->items->contains($item));
        $this->assertTrue($item->testCaseVersion->testCase->testProject->is($project));
        $this->assertNull($item->platform_id);
        $this->assertSame(TestCaseUrgency::Medium, $item->urgency);
    }

    public function test_a_plan_cannot_pin_the_same_version_twice_without_a_platform()
    {
        $plan = TestPlan::factory()->create();
        $item = TestPlanItem::factory()->for($plan)->create();

        $this->expectException(QueryException::class);

        TestPlanItem::factory()->for($plan)->create([
            'test_case_version_id' => $item->test_case_version_id,
            'platform_id' => null,
        ]);
    }

    public function test_the_same_version_may_be_pinned_once_per_platform()
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $chrome = Platform::factory()->for($project)->named('Chrome')->create();
        $safari = Platform::factory()->for($project)->named('Safari')->create();
        $version = $this->versionIn($project);

        $first = TestPlanItem::factory()->for($plan)->onPlatform($chrome->id)->create([
            'test_case_version_id' => $version->id,
        ]);
        $second = TestPlanItem::factory()->for($plan)->onPlatform($safari->id)->create([
            'test_case_version_id' => $version->id,
        ]);

        $this->assertTrue($first->platform->is($chrome));
        $this->assertTrue($second->platform->is($safari));
        $this->assertCount(2, $plan->fresh()->items);
    }

    public function test_the_same_version_and_platform_cannot_be_pinned_twice()
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $platform = Platform::factory()->for($project)->create();
        $version = $this->versionIn($project);
        TestPlanItem::factory()->for($plan)->onPlatform($platform->id)->create([
            'test_case_version_id' => $version->id,
        ]);

        $this->expectException(QueryException::class);

        TestPlanItem::factory()->for($plan)->onPlatform($platform->id)->create([
            'test_case_version_id' => $version->id,
        ]);
    }

    public function test_two_plans_may_pin_the_same_version()
    {
        $project = TestProject::factory()->create();
        $firstPlan = TestPlan::factory()->for($project)->create();
        $secondPlan = TestPlan::factory()->for($project)->create();
        $version = $this->versionIn($project);

        TestPlanItem::factory()->for($firstPlan)->create(['test_case_version_id' => $version->id]);
        $item = TestPlanItem::factory()->for($secondPlan)->create(['test_case_version_id' => $version->id]);

        $this->assertTrue($item->testCaseVersion->is($version));
        $this->assertCount(2, $version->fresh()->planItems);
    }

    public function test_items_are_listed_in_position_order()
    {
        $plan = TestPlan::factory()->create();
        $second = TestPlanItem::factory()->for($plan)->create(['sort_order' => 2]);
        $first = TestPlanItem::factory()->for($plan)->create(['sort_order' => 1]);

        $this->assertSame(
            [$first->id, $second->id],
            $plan->items()->pluck('id')->all(),
        );
    }

    public function test_two_items_may_share_a_position_so_that_reordering_can_renumber_them()
    {
        $plan = TestPlan::factory()->create();

        TestPlanItem::factory()->for($plan)->create(['sort_order' => 1]);
        TestPlanItem::factory()->for($plan)->create(['sort_order' => 1]);

        $this->assertCount(2, $plan->items);
    }

    public function test_urgency_is_cast_to_an_enum()
    {
        $item = TestPlanItem::factory()->urgent()->create();

        $this->assertSame(TestCaseUrgency::High, $item->refresh()->urgency);
        $this->assertDatabaseHas('test_plan_items', [
            'id' => $item->id,
            'urgency' => 'high',
        ]);
    }

    public function test_deleting_a_plan_removes_its_items()
    {
        $plan = TestPlan::factory()->create();
        TestPlanItem::factory()->for($plan)->create();
        $survivor = TestPlanItem::factory()->create();

        $plan->delete();

        $this->assertDatabaseCount('test_plan_items', 1);
        $this->assertDatabaseHas('test_plan_items', ['id' => $survivor->id]);
    }

    public function test_deleting_a_version_removes_only_its_own_items()
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $version = $this->versionIn($project);
        $other = $this->versionIn($project);
        TestPlanItem::factory()->for($plan)->create(['test_case_version_id' => $version->id]);
        $survivor = TestPlanItem::factory()->for($plan)->create(['test_case_version_id' => $other->id]);

        $version->delete();

        $this->assertDatabaseCount('test_plan_items', 1);
        $this->assertDatabaseHas('test_plan_items', ['id' => $survivor->id]);
    }

    public function test_deleting_a_platform_removes_only_the_items_on_that_platform()
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $chrome = Platform::factory()->for($project)->named('Chrome')->create();
        $safari = Platform::factory()->for($project)->named('Safari')->create();
        $version = $this->versionIn($project);
        TestPlanItem::factory()->for($plan)->onPlatform($chrome->id)->create([
            'test_case_version_id' => $version->id,
        ]);
        $survivor = TestPlanItem::factory()->for($plan)->onPlatform($safari->id)->create([
            'test_case_version_id' => $version->id,
        ]);

        $chrome->delete();

        $this->assertDatabaseCount('test_plan_items', 1);
        $this->assertDatabaseHas('test_plan_items', ['id' => $survivor->id]);
    }

    private function versionIn(TestProject $project): TestCaseVersion
    {
        $suite = TestSuite::factory()->for($project)->create();
        $case = TestCaseModel::factory()->for($suite, 'testSuite')->create();

        return TestCaseVersion::factory()->for($case, 'testCase')->create();
    }
}
