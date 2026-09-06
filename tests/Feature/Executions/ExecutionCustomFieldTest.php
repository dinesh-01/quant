<?php

namespace Tests\Feature\Executions;

use App\Actions\Executions\RecordExecution;
use App\Enums\Ability;
use App\Enums\CustomFieldEntity;
use App\Enums\ExecutionStatus;
use App\Models\Build;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Feature\Planning\InteractsWithPlanningRoles;
use Tests\TestCase;

class ExecutionCustomFieldTest extends TestCase
{
    use InteractsWithPlanningRoles;
    use RefreshDatabase;

    public function test_completing_a_run_saves_an_execution_field(): void
    {
        [$project, $item, $build, $user] = $this->runnable();
        $field = CustomField::factory()
            ->forEntity(CustomFieldEntity::Execution)
            ->enabledIn($project)
            ->create();

        $execution = app(RecordExecution::class)($user, $item, $build, [
            'status' => ExecutionStatus::Passed,
            'steps' => [],
            'custom_fields' => [$field->id => 'Browser 12'],
        ], true);

        $this->assertSame('Browser 12', $execution->customFieldValues()->sole()->value);
    }

    public function test_completing_refuses_a_missing_required_on_execution_case_field(): void
    {
        [$project, $item, $build, $user] = $this->runnable();
        $field = CustomField::factory()
            ->forEntity(CustomFieldEntity::TestCase)
            ->enabledIn($project, requiredOnExecution: true)
            ->create();

        try {
            app(RecordExecution::class)($user, $item, $build, [
                'status' => ExecutionStatus::Passed,
                'steps' => [],
                'custom_fields' => [],
            ], true);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('custom_fields.'.$field->id, $exception->errors());
        }

        $this->assertSame(0, CustomFieldValue::query()->count());
    }

    public function test_a_draft_can_omit_a_required_on_execution_field(): void
    {
        [$project, $item, $build, $user] = $this->runnable();
        CustomField::factory()
            ->forEntity(CustomFieldEntity::TestCase)
            ->enabledIn($project, requiredOnExecution: true)
            ->create();

        $draft = app(RecordExecution::class)($user, $item, $build, [
            'status' => ExecutionStatus::Failed,
            'steps' => [],
            'custom_fields' => [],
        ], false);

        $this->assertTrue($draft->is_draft);
        $this->assertSame(0, CustomFieldValue::query()->count());
    }

    public function test_completing_through_http_requires_the_execution_field(): void
    {
        [$project, $item, $build, $user] = $this->runnable();
        $field = CustomField::factory()
            ->forEntity(CustomFieldEntity::Execution)
            ->enabledIn($project, requiredOnExecution: true)
            ->create();

        $this->actingAs($user)
            ->post(route('executions.store', $item), [
                'build_id' => $build->id,
                'status' => ExecutionStatus::Passed->value,
                'complete' => '1',
            ])
            ->assertSessionHasErrors('custom_fields.'.$field->id);
    }

    /**
     * @return array{0: TestProject, 1: TestPlanItem, 2: Build, 3: User}
     */
    private function runnable(): array
    {
        $project = TestProject::factory()->create();
        $plan = TestPlan::factory()->for($project)->create();
        $item = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $build = Build::factory()->for($plan, 'testPlan')->create();
        $user = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests, Ability::ViewExecutions);

        return [$project, $item, $build, $user];
    }
}
