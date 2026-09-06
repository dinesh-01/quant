<?php

namespace Tests\Feature\TestSpecification;

use App\Actions\TestSpecification\CreateTestCaseRelation;
use App\Actions\TestSpecification\DeleteTestCaseRelation;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\TestCaseRelationType;
use App\Models\AuditEvent;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestCaseRelation;
use App\Models\TestProject;
use App\Models\TestSuite;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class TestCaseRelationTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_two_cases_in_the_same_project_can_be_related(): void
    {
        [$project, $source, $destination] = $this->pair();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);

        $relation = app(CreateTestCaseRelation::class)(
            $user,
            $source,
            $destination,
            TestCaseRelationType::Blocks,
        );

        $this->assertSame($source->id, $relation->source_id);
        $this->assertSame($destination->id, $relation->destination_id);
        $this->assertSame(TestCaseRelationType::Blocks, $relation->type);

        $event = AuditEvent::query()->sole();

        $this->assertSame(AuditAction::TestCaseRelationCreated->value, $event->action);
        $this->assertSame($source->id, $event->subject_id);
        $this->assertSame('blocks', $event->properties['type']);
        $this->assertSame($destination->name, $event->properties['destination']);
    }

    public function test_a_symmetric_relation_cannot_be_created_twice_in_either_direction(): void
    {
        [$project, $source, $destination] = $this->pair();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);

        app(CreateTestCaseRelation::class)($user, $source, $destination, TestCaseRelationType::Related);

        try {
            app(CreateTestCaseRelation::class)($user, $destination, $source, TestCaseRelationType::Related);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Those test cases are already linked that way.'],
                $exception->errors()['destination'],
            );
        }
    }

    public function test_a_case_cannot_be_related_to_itself(): void
    {
        [$project, $source] = $this->pair();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);

        try {
            app(CreateTestCaseRelation::class)($user, $source, $source, TestCaseRelationType::Related);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['A test case cannot be related to itself.'],
                $exception->errors()['destination'],
            );
        }
    }

    public function test_cross_project_relations_are_refused(): void
    {
        [$project, $source] = $this->pair();
        $other = TestCaseModel::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);

        try {
            app(CreateTestCaseRelation::class)($user, $source, $other, TestCaseRelationType::Related);
            $this->fail('Expected a validation exception.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Both test cases must belong to the same project.'],
                $exception->errors()['destination'],
            );
        }
    }

    public function test_creating_a_relation_is_forbidden_without_manage(): void
    {
        [$project, $source, $destination] = $this->pair();
        $user = $this->userWhoCan($project, Ability::ViewTestCases);

        $this->expectException(AuthorizationException::class);

        app(CreateTestCaseRelation::class)($user, $source, $destination, TestCaseRelationType::Related);
    }

    public function test_a_relation_can_be_removed(): void
    {
        [$project, $source, $destination] = $this->pair();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);
        $relation = app(CreateTestCaseRelation::class)($user, $source, $destination, TestCaseRelationType::DependsOn);

        app(DeleteTestCaseRelation::class)($user, $relation);

        $this->assertSame(0, TestCaseRelation::query()->count());
        $this->assertTrue(
            AuditEvent::query()->where('action', AuditAction::TestCaseRelationDeleted->value)->exists(),
        );
    }

    public function test_the_case_pane_lists_relations_in_both_directions(): void
    {
        [$project, $source, $destination] = $this->pair();
        $user = $this->userWhoCan($project, Ability::ViewTestCases, Ability::ManageTestCases);
        app(CreateTestCaseRelation::class)($user, $source, $destination, TestCaseRelationType::Blocks);

        $this->actingAs($user)
            ->get(route('specification.cases.show', [$project, $source]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('selected.case.relations', 1)
                ->where('selected.case.relations.0.label', 'blocks')
                ->where('selected.case.relations.0.other_id', $destination->id)
            );

        $this->actingAs($user)
            ->get(route('specification.cases.show', [$project, $destination]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('selected.case.relations.0.label', 'is blocked by')
                ->where('selected.case.relations.0.other_id', $source->id)
            );
    }

    public function test_a_relation_can_be_added_and_removed_through_http(): void
    {
        [$project, $source, $destination] = $this->pair();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);

        $this->actingAs($user)
            ->post(route('test-case-relations.store', $source), [
                'destination_id' => $destination->id,
                'type' => 'related',
            ])
            ->assertRedirect(route('specification.cases.show', [$project, $source]))
            ->assertSessionHasNoErrors();

        $relation = TestCaseRelation::query()->sole();

        $this->actingAs($user)
            ->delete(route('test-case-relations.destroy', $relation))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, TestCaseRelation::query()->count());
    }

    /**
     * @return array{0: TestProject, 1: TestCaseModel, 2: TestCaseModel}
     */
    private function pair(): array
    {
        $project = TestProject::factory()->create();
        $suite = TestSuite::factory()->for($project)->create();
        $source = TestCaseModel::factory()->for($suite, 'testSuite')->create(['name' => 'Pay']);
        $destination = TestCaseModel::factory()->for($suite, 'testSuite')->create(['name' => 'Refund']);

        return [$project, $source, $destination];
    }
}
