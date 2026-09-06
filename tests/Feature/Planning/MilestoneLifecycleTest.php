<?php

namespace Tests\Feature\Planning;

use App\Actions\Milestones\CreateMilestone;
use App\Actions\Milestones\DeleteMilestone;
use App\Actions\Milestones\UpdateMilestone;
use App\Enums\Ability;
use App\Models\Milestone;
use App\Models\TestPlan;
use App\Models\TestProject;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class MilestoneLifecycleTest extends TestCase
{
    use InteractsWithPlanningRoles;
    use RefreshDatabase;

    public function test_a_milestone_can_be_added_to_a_plan(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManageMilestones);

        $milestone = app(CreateMilestone::class)($user, $plan, [
            'name' => 'Beta',
            'target_date' => '2026-10-01',
            'start_date' => '2026-09-01',
            'high_percent' => 100,
            'medium_percent' => 80,
            'low_percent' => 50,
        ]);

        $this->assertSame('Beta', $milestone->name);
        $this->assertSame(100, $milestone->high_percent);
        $this->assertSame(1, $plan->milestones()->count());
    }

    public function test_two_milestones_on_one_plan_cannot_share_a_name(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManageMilestones);

        app(CreateMilestone::class)($user, $plan, [
            'name' => 'Beta',
            'target_date' => '2026-10-01',
        ]);

        try {
            app(CreateMilestone::class)($user, $plan, [
                'name' => 'Beta',
                'target_date' => '2026-11-01',
            ]);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('name', $exception->errors());
        }
    }

    public function test_a_milestone_can_be_updated_and_deleted(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManageMilestones);
        $milestone = Milestone::factory()->for($plan, 'testPlan')->create(['name' => 'Beta']);

        $updated = app(UpdateMilestone::class)($user, $milestone, [
            'name' => 'GA',
            'target_date' => '2026-12-01',
            'high_percent' => 90,
            'medium_percent' => 70,
            'low_percent' => 40,
        ]);

        $this->assertSame('GA', $updated->name);

        app(DeleteMilestone::class)($user, $milestone);

        $this->assertSame(0, Milestone::query()->count());
    }

    public function test_managing_milestones_requires_the_ability(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManageBuilds);

        $this->expectException(AuthorizationException::class);

        app(CreateMilestone::class)($user, $plan, [
            'name' => 'Beta',
            'target_date' => '2026-10-01',
        ]);
    }

    public function test_a_milestone_can_be_added_through_http(): void
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ManageMilestones);

        $this->actingAs($user)
            ->post(route('milestones.store', $plan), [
                'name' => 'GA',
                'target_date' => '2026-12-01',
                'high_percent' => 90,
                'medium_percent' => 70,
                'low_percent' => 40,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('GA', $plan->milestones()->sole()->name);
    }
}
