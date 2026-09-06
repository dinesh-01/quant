<?php

namespace Tests\Feature\Executions;

use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\ExecutionStatus;
use App\Models\AuditEvent;
use App\Models\Build;
use App\Models\Execution;
use App\Models\ExecutionIssue;
use App\Models\IssueTracker;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\Planning\InteractsWithPlanningRoles;
use Tests\TestCase;

class CreateIssueFromExecutionTest extends TestCase
{
    use InteractsWithPlanningRoles;
    use RefreshDatabase;

    public function test_a_failed_run_opens_a_jira_issue_and_links_it(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://acme.atlassian.net/rest/api/3/issue' => Http::response([
                'key' => 'PAY-12',
                'id' => '10012',
            ], 201),
            'https://acme.atlassian.net/rest/api/3/issue/PAY-12*' => Http::response([
                'key' => 'PAY-12',
                'fields' => [
                    'summary' => 'PAY-1 Login is Failed',
                    'status' => ['name' => 'To Do'],
                ],
            ]),
        ]);

        [$plan, $item, $build, $user] = $this->runnable();
        IssueTracker::factory()->for($plan->testProject)->create();
        $execution = $this->completedRun($plan, $item, $build, $user, ExecutionStatus::Failed, 'Login 500');

        $this->actingAs($user)
            ->post(route('execution-issues.create', $execution))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $issue = ExecutionIssue::query()->sole();

        $this->assertSame($execution->id, $issue->execution_id);
        $this->assertSame('PAY-12', $issue->issue_id);
        $this->assertSame('https://acme.atlassian.net/browse/PAY-12', $issue->issue_url);
        $this->assertSame('To Do', $issue->issue_status);
        $this->assertNotNull($issue->status_fetched_at);

        $event = AuditEvent::query()->sole();
        $this->assertSame(AuditAction::ExecutionIssueCreated->value, $event->action);
        $this->assertSame('PAY-12', $event->properties['issue_id']);

        Http::assertSent(function ($request) use ($item): bool {
            if ($request->method() !== 'POST' || $request->url() !== 'https://acme.atlassian.net/rest/api/3/issue') {
                return false;
            }

            $fields = $request->data()['fields'];
            $case = $item->testCaseVersion->testCase;

            return $fields['project']['key'] === 'PAY'
                && $fields['issuetype']['name'] === 'Bug'
                && str_contains($fields['summary'], $case->fullExternalId())
                && str_contains($fields['summary'], 'Failed');
        });
    }

    public function test_a_blocked_run_can_create_an_issue(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://acme.atlassian.net/rest/api/3/issue' => Http::response(['key' => 'PAY-9'], 201),
            'https://acme.atlassian.net/rest/api/3/issue/PAY-9*' => Http::response([
                'key' => 'PAY-9',
                'fields' => ['summary' => 'Blocked', 'status' => ['name' => 'To Do']],
            ]),
        ]);

        [$plan, $item, $build, $user] = $this->runnable();
        IssueTracker::factory()->for($plan->testProject)->create();
        $execution = $this->completedRun($plan, $item, $build, $user, ExecutionStatus::Blocked);

        $this->actingAs($user)
            ->post(route('execution-issues.create', $execution))
            ->assertSessionHasNoErrors();

        $this->assertSame('PAY-9', ExecutionIssue::query()->sole()->issue_id);
    }

    public function test_a_passed_run_cannot_create_an_issue(): void
    {
        [$plan, $item, $build, $user] = $this->runnable();
        IssueTracker::factory()->for($plan->testProject)->create();
        $execution = $this->completedRun($plan, $item, $build, $user, ExecutionStatus::Passed);

        $this->actingAs($user)
            ->post(route('execution-issues.create', $execution))
            ->assertSessionHasErrors('execution');

        $this->assertSame(0, ExecutionIssue::query()->count());
    }

    public function test_a_draft_cannot_create_an_issue(): void
    {
        [$plan, $item, $build, $user] = $this->runnable();
        IssueTracker::factory()->for($plan->testProject)->create();
        $execution = Execution::factory()
            ->for($plan, 'testPlan')
            ->for($build)
            ->for($item, 'testPlanItem')
            ->create([
                'test_case_version_id' => $item->test_case_version_id,
                'status' => ExecutionStatus::Failed,
                'tester_id' => $user->id,
            ]);

        $this->actingAs($user)
            ->post(route('execution-issues.create', $execution))
            ->assertSessionHasErrors('execution');
    }

    public function test_creating_requires_an_enabled_tracker(): void
    {
        [$plan, $item, $build, $user] = $this->runnable();
        $execution = $this->completedRun($plan, $item, $build, $user, ExecutionStatus::Failed);

        $this->actingAs($user)
            ->post(route('execution-issues.create', $execution))
            ->assertSessionHasErrors('tracker');
    }

    public function test_creating_requires_execute_tests(): void
    {
        [$plan, $item, $build] = $this->runnable();
        $viewer = $this->userWhoCanOnPlan($plan, Ability::ViewExecutions);
        IssueTracker::factory()->for($plan->testProject)->create();
        $execution = $this->completedRun($plan, $item, $build, $viewer, ExecutionStatus::Failed);

        $this->actingAs($viewer)
            ->post(route('execution-issues.create', $execution))
            ->assertForbidden();
    }

    public function test_a_jira_rejection_is_shown_to_the_tester(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://acme.atlassian.net/rest/api/3/issue' => Http::response([
                'errorMessages' => [],
                'errors' => ['project' => 'project is required'],
            ], 400),
        ]);

        [$plan, $item, $build, $user] = $this->runnable();
        IssueTracker::factory()->for($plan->testProject)->create();
        $execution = $this->completedRun($plan, $item, $build, $user, ExecutionStatus::Failed);

        $this->actingAs($user)
            ->post(route('execution-issues.create', $execution))
            ->assertSessionHasErrors('tracker');

        $this->assertSame(0, ExecutionIssue::query()->count());
    }

    public function test_linking_an_id_still_works_and_enriches_from_jira(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://acme.atlassian.net/rest/api/3/issue/PAY-4*' => Http::response([
                'key' => 'PAY-4',
                'fields' => [
                    'summary' => 'Existing bug',
                    'status' => ['name' => 'In Progress'],
                ],
            ]),
        ]);

        [$plan, $item, $build, $user] = $this->runnable();
        IssueTracker::factory()->for($plan->testProject)->create();
        $execution = $this->completedRun($plan, $item, $build, $user, ExecutionStatus::Failed);

        $this->actingAs($user)
            ->post(route('execution-issues.store', $execution), ['issue_id' => 'PAY-4'])
            ->assertSessionHasNoErrors();

        $issue = ExecutionIssue::query()->sole();

        $this->assertSame('PAY-4', $issue->issue_id);
        $this->assertSame('In Progress', $issue->issue_status);
        $this->assertSame('https://acme.atlassian.net/browse/PAY-4', $issue->issue_url);
    }

    public function test_execution_history_refreshes_stale_issue_status(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'http://localhost:5173/*' => Http::response('', 200),
            'https://acme.atlassian.net/rest/api/3/issue/PAY-4*' => Http::response([
                'key' => 'PAY-4',
                'fields' => [
                    'summary' => 'Existing bug',
                    'status' => ['name' => 'Done'],
                ],
            ]),
        ]);

        [$plan, $item, $build, $user] = $this->runnable();
        IssueTracker::factory()->for($plan->testProject)->create();
        $execution = $this->completedRun($plan, $item, $build, $user, ExecutionStatus::Failed);
        ExecutionIssue::factory()->for($execution)->create([
            'issue_id' => 'PAY-4',
            'issue_status' => 'To Do',
            'status_fetched_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($user)
            ->get(route('executions.show', [
                'testPlan' => $plan,
                'testPlanItem' => $item,
                'build' => $build->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('issueTracker.enabled', true)
                ->where('history.0.issues.0.issue_id', 'PAY-4')
                ->where('history.0.issues.0.issue_status', 'Done')
                ->where('history.0.issues.0.issue_url', 'https://acme.atlassian.net/browse/PAY-4')
            );
    }

    public function test_fresh_issue_status_is_not_refetched(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'http://localhost:5173/*' => Http::response('', 200),
        ]);

        [$plan, $item, $build, $user] = $this->runnable();
        IssueTracker::factory()->for($plan->testProject)->create();
        $execution = $this->completedRun($plan, $item, $build, $user, ExecutionStatus::Failed);
        ExecutionIssue::factory()->for($execution)->create([
            'issue_id' => 'PAY-4',
            'issue_status' => 'To Do',
            'status_fetched_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('executions.show', [
                'testPlan' => $plan,
                'testPlanItem' => $item,
                'build' => $build->id,
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('history.0.issues.0.issue_status', 'To Do')
            );
    }

    /**
     * @return array{0: TestPlan, 1: TestPlanItem, 2: Build, 3: User}
     */
    private function runnable(): array
    {
        $project = TestProject::factory()->create(['prefix' => 'PAY']);
        $plan = TestPlan::factory()->for($project)->create(['name' => 'Sprint']);
        $item = TestPlanItem::factory()->for($plan, 'testPlan')->create();
        $build = Build::factory()->for($plan, 'testPlan')->create(['name' => '1.0']);
        $user = $this->userWhoCanOnPlan($plan, Ability::ExecuteTests, Ability::ViewExecutions);

        return [$plan, $item, $build, $user];
    }

    private function completedRun(
        TestPlan $plan,
        TestPlanItem $item,
        Build $build,
        User $user,
        ExecutionStatus $status,
        ?string $notes = null,
    ): Execution {
        return Execution::factory()
            ->for($plan, 'testPlan')
            ->for($build)
            ->for($item, 'testPlanItem')
            ->completed()
            ->create([
                'test_case_version_id' => $item->test_case_version_id,
                'status' => $status,
                'notes' => $notes,
                'tester_id' => $user->id,
                'version' => $item->testCaseVersion->version,
            ]);
    }
}
