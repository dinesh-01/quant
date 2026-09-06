<?php

namespace Tests\Feature\TestSpecification;

use App\Actions\TestSpecification\AllocateExternalId;
use App\Actions\TestSpecification\CreateTestCase;
use App\Enums\Ability;
use App\Models\Role;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestProject;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExternalIdAllocationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_first_external_id_for_a_project_is_one()
    {
        $project = TestProject::factory()->create();

        $this->assertSame(1, (new AllocateExternalId)($project));
    }

    public function test_repeated_allocation_produces_a_contiguous_sequence_with_no_duplicates()
    {
        $project = TestProject::factory()->create();
        $allocate = new AllocateExternalId;

        $allocated = [];

        for ($i = 0; $i < 50; $i++) {
            $allocated[] = $allocate($project);
        }

        $this->assertSame(range(1, 50), $allocated);
        $this->assertCount(50, array_unique($allocated));
    }

    /**
     * Real contention cannot be exercised here: RefreshDatabase runs every test
     * inside one transaction on one connection, so a lock never competes with
     * itself. This asserts the lock is actually requested, which is what stops
     * two concurrent creations being handed the same number in production.
     */
    public function test_the_counter_is_read_under_a_row_lock()
    {
        $project = TestProject::factory()->create();

        DB::enableQueryLog();

        (new AllocateExternalId)($project);

        $selects = array_filter(
            array_column(DB::getQueryLog(), 'query'),
            fn (string $query): bool => str_contains($query, 'select'),
        );

        $this->assertNotEmpty($selects);

        foreach ($selects as $query) {
            $this->assertStringContainsString('for update', $query);
        }
    }

    public function test_each_project_keeps_its_own_sequence()
    {
        $first = TestProject::factory()->create();
        $second = TestProject::factory()->create();
        $allocate = new AllocateExternalId;

        $allocate($first);
        $allocate($first);

        $this->assertSame(1, $allocate($second));
        $this->assertSame(3, $allocate($first));
    }

    public function test_allocation_advances_the_stored_counter()
    {
        $project = TestProject::factory()->create();

        (new AllocateExternalId)($project);
        (new AllocateExternalId)($project);

        $this->assertSame(2, $project->refresh()->test_case_counter);
    }

    public function test_creating_a_test_case_allocates_the_next_external_id_and_a_first_version()
    {
        $suite = TestSuite::factory()->create();
        $user = $this->userWhoCan($suite->test_project_id, Ability::ManageTestCases);

        $case = app(CreateTestCase::class)($user, $suite, ['name' => 'Login works']);

        $this->assertSame(1, $case->external_id);
        $this->assertSame($suite->test_project_id, $case->test_project_id);
        $this->assertSame(1, $case->refresh()->latestVersion->version);
        $this->assertSame($user->id, $case->latestVersion->author_id);
        $this->assertTrue($case->latestVersion->is_open);
        $this->assertSame(1, $suite->testProject->refresh()->test_case_counter);
    }

    public function test_a_factory_built_case_and_an_action_built_case_never_share_an_external_id()
    {
        $suite = TestSuite::factory()->create();
        $user = $this->userWhoCan($suite->test_project_id, Ability::ManageTestCases);

        TestCaseModel::factory()->for($suite, 'testSuite')->create();
        $created = app(CreateTestCase::class)($user, $suite, ['name' => 'Second']);

        $this->assertSame(2, $created->external_id);
    }

    public function test_a_new_case_is_placed_after_the_cases_already_in_its_suite()
    {
        $suite = TestSuite::factory()->create();
        $user = $this->userWhoCan($suite->test_project_id, Ability::ManageTestCases);
        TestCaseModel::factory()->for($suite, 'testSuite')->create(['sort_order' => 7]);

        $case = app(CreateTestCase::class)($user, $suite, ['name' => 'Later']);

        $this->assertSame(8, $case->sort_order);
    }

    private function userWhoCan(int $projectId, Ability ...$abilities): User
    {
        $user = User::factory()->for(Role::factory()->granting())->create();

        $user->projectRoles()->attach(
            Role::factory()->granting(...$abilities)->create(),
            ['test_project_id' => $projectId],
        );

        return $user;
    }
}
