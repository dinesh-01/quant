<?php

namespace Tests\Feature\CodeTrackers;

use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\CodeTrackerType;
use App\Models\AuditEvent;
use App\Models\CodeTracker;
use App\Models\TestProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia;
use Tests\Feature\TestSpecification\InteractsWithSpecificationRoles;
use Tests\TestCase;

class CodeTrackerCatalogueTest extends TestCase
{
    use InteractsWithSpecificationRoles;
    use RefreshDatabase;

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $project = TestProject::factory()->create();

        $this->get(route('code-trackers.show', $project))->assertRedirect(route('login'));
    }

    public function test_reading_the_tracker_requires_the_view_ability(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageTestCases);

        $this->actingAs($user)
            ->get(route('code-trackers.show', $project))
            ->assertForbidden();
    }

    public function test_the_shared_project_prop_reports_the_code_tracker_ability(): void
    {
        $project = TestProject::factory()->create();

        $this->actingAs($this->userWhoCan($project, Ability::ViewCodeTrackers))
            ->get(route('code-trackers.show', $project))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('currentProject.can.viewCodeTrackers', true)
                ->where('currentProject.can.viewSpecification', false)
                ->where('currentProject.id', $project->id)
            );
    }

    public function test_a_tracker_can_be_saved_for_a_project(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageCodeTrackers);

        $this->actingAs($user)
            ->put(route('code-trackers.update', $project), $this->payload())
            ->assertRedirect(route('code-trackers.show', $project))
            ->assertSessionHasNoErrors();

        $tracker = CodeTracker::query()->sole();

        $this->assertSame($project->id, $tracker->test_project_id);
        $this->assertSame('GitHub', $tracker->name);
        $this->assertSame(CodeTrackerType::Github, $tracker->type);
        $this->assertSame('https://api.github.com', $tracker->base_url);
        $this->assertSame('acme/payments', $tracker->project_key);
        $this->assertTrue($tracker->is_enabled);
    }

    public function test_saving_a_tracker_is_recorded_in_the_audit_trail(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageCodeTrackers);

        $this->actingAs($user)
            ->put(route('code-trackers.update', $project), $this->payload())
            ->assertSessionHasNoErrors();

        $event = AuditEvent::query()->sole();

        $this->assertSame(AuditAction::CodeTrackerSaved->value, $event->action);
        $this->assertSame($user->id, $event->user_id);
        $this->assertSame($project->id, $event->subject_id);
    }

    public function test_a_second_save_updates_the_same_row(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageCodeTrackers);
        CodeTracker::factory()->for($project)->create();

        $this->actingAs($user)
            ->put(route('code-trackers.update', $project), $this->payload([
                'name' => 'GitLab',
                'type' => CodeTrackerType::Gitlab->value,
                'base_url' => 'https://gitlab.example',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, CodeTracker::query()->count());
        $this->assertSame('GitLab', $project->refresh()->codeTracker->name);
        $this->assertSame(CodeTrackerType::Gitlab, $project->codeTracker->type);
    }

    public function test_saving_requires_the_manage_ability(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ViewCodeTrackers);

        $this->actingAs($user)
            ->put(route('code-trackers.update', $project), $this->payload())
            ->assertForbidden();

        $this->assertSame(0, CodeTracker::query()->count());
    }

    public function test_a_tracker_from_another_project_is_not_shown(): void
    {
        $ours = TestProject::factory()->create();
        $theirs = TestProject::factory()->create();
        $user = $this->userWhoCan($ours, Ability::ViewCodeTrackers);
        CodeTracker::factory()->for($theirs)->create(['name' => 'Theirs']);

        $this->actingAs($user)
            ->get(route('code-trackers.show', $ours))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tracker', null)
            );
    }

    public function test_a_missing_name_is_rejected(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageCodeTrackers);

        $this->actingAs($user)
            ->put(route('code-trackers.update', $project), $this->payload(['name' => '']))
            ->assertSessionHasErrors('name');
    }

    public function test_a_non_http_base_url_is_rejected(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageCodeTrackers);

        $this->actingAs($user)
            ->put(route('code-trackers.update', $project), $this->payload([
                'base_url' => 'ftp://example.com',
            ]))
            ->assertSessionHasErrors('base_url');
    }

    public function test_a_reachable_host_is_reported_as_a_successful_probe(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.github.com/repos/acme/payments' => Http::response('ok', 200),
        ]);

        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageCodeTrackers);
        CodeTracker::factory()->for($project)->create();

        $this->actingAs($user)
            ->post(route('code-trackers.connection', $project))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Http::assertSent(fn ($request): bool => $request->url() === 'https://api.github.com/repos/acme/payments');
    }

    public function test_an_unreachable_host_is_reported_as_a_failed_probe(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'https://api.github.com/repos/acme/payments' => Http::failedConnection(),
        ]);

        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageCodeTrackers);
        CodeTracker::factory()->for($project)->create();

        $this->actingAs($user)
            ->post(route('code-trackers.connection', $project))
            ->assertRedirect();
    }

    public function test_probing_requires_a_saved_tracker(): void
    {
        $project = TestProject::factory()->create();
        $user = $this->userWhoCan($project, Ability::ManageCodeTrackers);

        $this->actingAs($user)
            ->post(route('code-trackers.connection', $project))
            ->assertSessionHasErrors('base_url');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'GitHub',
            'type' => CodeTrackerType::Github->value,
            'base_url' => 'https://api.github.com',
            'project_key' => 'acme/payments',
            'view_url_template' => '{base_url}/{repository}/blob/{branch}/{path}',
            'is_enabled' => '1',
        ], $overrides);
    }
}
