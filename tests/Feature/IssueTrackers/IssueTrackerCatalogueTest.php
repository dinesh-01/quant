<?php

namespace Tests\Feature\IssueTrackers;

use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\IssueTrackerType;
use App\Models\AuditEvent;
use App\Models\IssueTracker;
use App\Models\TestProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\TestSpecification\InteractsWithSpecificationRoles;
use Tests\TestCase;

class IssueTrackerCatalogueTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $project = TestProject::factory()->create();

        $this->get(route('issue-trackers.show', $project))->assertRedirect(route('login'));
    }

    public function test_reading_the_tracker_requires_the_view_ability(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);

        $this->actingAs($user)
            ->get(route('issue-trackers.show', $project))
            ->assertForbidden();
    }

    public function test_the_shared_project_prop_reports_the_issue_tracker_ability(): void
    {
        $project = TestProject::factory()->create();

        $this->actingAs($this->userWhoCan($project, Ability::ViewIssueTrackers))
            ->get(route('issue-trackers.show', $project))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('currentProject.can.viewIssueTrackers', true)
                ->where('currentProject.can.viewSpecification', false)
                ->where('currentProject.id', $project->id)
            );
    }

    public function test_a_tracker_can_be_saved_for_a_project(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageIssueTrackers);

        $this->actingAs($user)
            ->put(route('issue-trackers.update', $project), $this->payload())
            ->assertRedirect(route('issue-trackers.show', $project))
            ->assertSessionHasNoErrors();

        $tracker = IssueTracker::query()->sole();

        $this->assertSame($project->id, $tracker->test_project_id);
        $this->assertSame('Jira Cloud', $tracker->name);
        $this->assertSame(IssueTrackerType::Jira, $tracker->type);
        $this->assertSame('https://acme.atlassian.net', $tracker->base_url);
        $this->assertSame('PAY', $tracker->setting('project_key'));
        $this->assertSame('Bug', $tracker->setting('issue_type'));
        $this->assertSame('qa@example.com', $tracker->setting('email'));
        $this->assertSame('jira-secret', $tracker->setting('api_token'));
        $this->assertTrue($tracker->is_enabled);
    }

    public function test_the_api_token_is_not_sent_to_the_page(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewIssueTrackers);
        IssueTracker::factory()->for($project)->create();

        $this->actingAs($user)
            ->get(route('issue-trackers.show', $project))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tracker.has_api_token', true)
                ->missing('tracker.api_token')
                ->where('tracker.email', 'qa@example.com')
            );
    }

    public function test_saving_a_tracker_is_recorded_in_the_audit_trail_without_the_token(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageIssueTrackers);

        $this->actingAs($user)
            ->put(route('issue-trackers.update', $project), $this->payload())
            ->assertSessionHasNoErrors();

        $event = AuditEvent::query()->sole();

        $this->assertSame(AuditAction::IssueTrackerSaved->value, $event->action);
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame($project->id, $event->subject_id);
        $this->assertSame(['changed' => true], $event->properties['settings']);
        $this->assertStringNotContainsString('jira-secret', json_encode($event->properties));
    }

    public function test_a_second_save_updates_the_same_row_and_keeps_the_token(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageIssueTrackers);
        IssueTracker::factory()->for($project)->create();

        $this->actingAs($user)
            ->put(route('issue-trackers.update', $project), $this->payload([
                'name' => 'Acme Jira',
                'api_token' => '',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, IssueTracker::query()->count());
        $this->assertSame('Acme Jira', $project->refresh()->issueTracker->name);
        $this->assertSame('jira-token', $project->issueTracker->setting('api_token'));
    }

    public function test_the_first_save_requires_an_api_token(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageIssueTrackers);

        $this->actingAs($user)
            ->put(route('issue-trackers.update', $project), $this->payload([
                'api_token' => '',
            ]))
            ->assertSessionHasErrors('api_token');
    }

    public function test_saving_requires_the_manage_ability(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewIssueTrackers);

        $this->actingAs($user)
            ->put(route('issue-trackers.update', $project), $this->payload())
            ->assertForbidden();

        $this->assertSame(0, IssueTracker::query()->count());
    }

    public function test_a_tracker_from_another_project_is_not_shown(): void
    {
        $ours = TestProject::factory()->create();
        $theirs = TestProject::factory()->create();
        $user = $this->userWhoCan($ours, Ability::ViewIssueTrackers);
        IssueTracker::factory()->for($theirs)->create(['name' => 'Theirs']);

        $this->actingAs($user)
            ->get(route('issue-trackers.show', $ours))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tracker', null)
            );
    }

    public function test_a_missing_name_is_rejected(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageIssueTrackers);

        $this->actingAs($user)
            ->put(route('issue-trackers.update', $project), $this->payload(['name' => '']))
            ->assertSessionHasErrors('name');
    }

    public function test_a_non_http_base_url_is_rejected(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageIssueTrackers);

        $this->actingAs($user)
            ->put(route('issue-trackers.update', $project), $this->payload([
                'base_url' => 'ftp://example.com',
            ]))
            ->assertSessionHasErrors('base_url');
    }

    public function test_accepted_credentials_are_reported_as_a_successful_probe(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://acme.atlassian.net/rest/api/3/myself' => Http::response(['accountId' => '1'], 200),
        ]);

        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageIssueTrackers);
        IssueTracker::factory()->for($project)->create();

        $this->actingAs($user)
            ->post(route('issue-trackers.connection', $project))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Http::assertSent(fn ($request): bool => $request->url() === 'https://acme.atlassian.net/rest/api/3/myself');
    }

    public function test_rejected_credentials_are_reported_as_a_failed_probe(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://acme.atlassian.net/rest/api/3/myself' => Http::response('no', 401),
        ]);

        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageIssueTrackers);
        IssueTracker::factory()->for($project)->create();

        $this->actingAs($user)
            ->post(route('issue-trackers.connection', $project))
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_an_unreachable_host_is_reported_as_a_failed_probe(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://acme.atlassian.net/rest/api/3/myself' => Http::failedConnection(),
        ]);

        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageIssueTrackers);
        IssueTracker::factory()->for($project)->create();

        $this->actingAs($user)
            ->post(route('issue-trackers.connection', $project))
            ->assertRedirect();
    }

    public function test_probing_requires_a_saved_tracker(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageIssueTrackers);

        $this->actingAs($user)
            ->post(route('issue-trackers.connection', $project))
            ->assertSessionHasErrors('base_url');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Jira Cloud',
            'type' => IssueTrackerType::Jira->value,
            'base_url' => 'https://acme.atlassian.net',
            'project_key' => 'PAY',
            'issue_type' => 'Bug',
            'email' => 'qa@example.com',
            'api_token' => 'jira-secret',
            'proxy' => '',
            'is_enabled' => '1',
        ], $overrides);
    }
}
