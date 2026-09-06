<?php

namespace Tests\Feature\Audit;

use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Http\Requests\Audit\EventLogPruneRequest;
use App\Models\AuditEvent;
use App\Models\Role;
use App\Models\TestProject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class EventLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_log_lists_records_newest_first()
    {
        $reader = $this->reader();

        AuditEvent::factory()->create([
            'action' => AuditAction::UserCreated->value,
            'created_at' => now()->subDay(),
        ]);

        AuditEvent::factory()->create([
            'action' => AuditAction::RoleCreated->value,
            'created_at' => now(),
        ]);

        $this->actingAs($reader)
            ->get(route('events.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('audit/index')
                ->has('events.data', 2)
                ->where('events.data.0.action', AuditAction::RoleCreated->value)
                ->where('events.data.1.action', AuditAction::UserCreated->value),
            );
    }

    public function test_reading_the_log_requires_the_ability()
    {
        $this->actingAs(User::factory()->create())
            ->get(route('events.index'))
            ->assertForbidden();
    }

    /**
     * The label comes from the enum at read time rather than from the row, so
     * the wording can improve without rewriting history — which is the whole
     * reason the sentence is not stored.
     */
    public function test_the_label_is_composed_when_the_log_is_read()
    {
        AuditEvent::factory()->create(['action' => AuditAction::UserPasswordSet->value]);

        $this->actingAs($this->reader())
            ->get(route('events.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events.data.0.label', 'User password set'),
            );
    }

    public function test_a_record_names_the_subject_it_points_at()
    {
        $project = TestProject::factory()->create(['name' => 'Apollo']);

        AuditEvent::factory()->create([
            'action' => AuditAction::TestProjectUpdated->value,
            'subject_type' => TestProject::class,
            'subject_id' => $project->getKey(),
        ]);

        $this->actingAs($this->reader())
            ->get(route('events.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events.data.0.subject.name', 'Apollo')
                ->where('events.data.0.subject.type', 'Test project'),
            );
    }

    /**
     * The reason the delete actions copy a name into `properties`: the morph
     * cannot resolve a row that no longer exists.
     */
    public function test_a_deleted_subject_falls_back_to_the_recorded_name()
    {
        AuditEvent::factory()->create([
            'action' => AuditAction::RoleDeleted->value,
            'subject_type' => Role::class,
            'subject_id' => 99999,
            'properties' => ['name' => 'Retired role'],
        ]);

        $this->actingAs($this->reader())
            ->get(route('events.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('events.data.0.subject.name', 'Retired role'),
            );
    }

    public function test_the_log_can_be_filtered_by_act_and_by_person()
    {
        $actor = User::factory()->create(['name' => 'Dana Scully']);

        AuditEvent::factory()->for($actor)->create(['action' => AuditAction::UserCreated->value]);
        AuditEvent::factory()->for($actor)->create(['action' => AuditAction::RoleCreated->value]);
        AuditEvent::factory()->create(['action' => AuditAction::UserCreated->value]);

        $this->actingAs($this->reader())
            ->get(route('events.index', ['action' => AuditAction::UserCreated->value]))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('events.data', 2));

        $this->actingAs($this->reader())
            ->get(route('events.index', ['actor' => 'Scully']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('events.data', 2));

        $this->actingAs($this->reader())
            ->get(route('events.index', ['actor' => 'Scully', 'action' => AuditAction::RoleCreated->value]))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('events.data', 1));
    }

    /**
     * A morph resolved per row would be one query per record per type, which is
     * exactly the shape of N+1 a log page invites.
     */
    public function test_the_log_does_not_query_per_record()
    {
        $reader = $this->reader();
        $project = TestProject::factory()->create();

        AuditEvent::factory()->count(6)->create([
            'action' => AuditAction::TestProjectUpdated->value,
            'subject_type' => TestProject::class,
            'subject_id' => $project->getKey(),
        ]);

        DB::enableQueryLog();

        $this->actingAs($reader)->get(route('events.index'));

        $touching = array_filter(
            DB::getQueryLog(),
            fn (array $entry): bool => str_contains($entry['query'], 'audit_events')
                || str_contains($entry['query'], 'test_projects'),
        );

        /** One count, one page of rows, one for the actors, one for the subjects. */
        $this->assertLessThanOrEqual(4, count($touching));
    }

    public function test_pruning_discards_old_records_and_keeps_recent_ones()
    {
        $old = AuditEvent::factory()->create(['created_at' => now()->subDays(400)]);
        $recent = AuditEvent::factory()->create(['created_at' => now()->subDay()]);

        $this->actingAs($this->pruner())
            ->delete(route('events.destroy'), ['keep_days' => 365])
            ->assertRedirect(route('events.index'));

        $this->assertDatabaseMissing('audit_events', ['id' => $old->getKey()]);
        $this->assertDatabaseHas('audit_events', ['id' => $recent->getKey()]);
    }

    /**
     * Otherwise the trail would have a silent gap, which cannot be told apart
     * from tampering.
     */
    public function test_pruning_records_itself_and_survives_the_prune()
    {
        AuditEvent::factory()->create(['created_at' => now()->subDays(400)]);

        $this->actingAs($this->pruner())->delete(route('events.destroy'), ['keep_days' => 365]);

        $event = AuditEvent::query()->where('action', AuditAction::EventLogPruned->value)->sole();

        $this->assertSame(1, $event->properties['discarded']);
        $this->assertSame(365, $event->properties['keep_days']);
    }

    /**
     * The floor is what stops pruning being a way to erase the last few hours,
     * which is the one thing the trail exists to prevent.
     */
    public function test_recent_records_cannot_be_discarded()
    {
        $event = AuditEvent::factory()->create(['created_at' => now()->subDay()]);

        $this->actingAs($this->pruner())
            ->delete(route('events.destroy'), ['keep_days' => 1])
            ->assertSessionHasErrors('keep_days');

        $this->assertDatabaseHas('audit_events', ['id' => $event->getKey()]);
        $this->assertGreaterThan(1, EventLogPruneRequest::MINIMUM_KEEP_DAYS);
    }

    /**
     * Destroying evidence is a different act from reading it, so it takes its
     * own ability rather than coming free with `view_event_log`.
     */
    public function test_reading_the_log_does_not_permit_pruning_it()
    {
        $event = AuditEvent::factory()->create(['created_at' => now()->subDays(400)]);

        $this->actingAs($this->reader())
            ->delete(route('events.destroy'), ['keep_days' => 365])
            ->assertForbidden();

        $this->assertDatabaseHas('audit_events', ['id' => $event->getKey()]);
    }

    public function test_the_prune_control_is_only_offered_to_those_who_may_use_it()
    {
        $this->actingAs($this->reader())
            ->get(route('events.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('can.prune', false));

        $this->actingAs($this->pruner())
            ->get(route('events.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('can.prune', true));
    }

    public function test_the_navigation_offers_the_log_only_to_readers()
    {
        $this->actingAs($this->reader())
            ->get(route('events.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.can.viewEventLog', true));

        $this->actingAs(User::factory()->create())
            ->get(route('projects.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.can.viewEventLog', false));
    }

    private function reader(): User
    {
        return User::factory()
            ->for(Role::factory()->granting(Ability::ViewEventLog), 'role')
            ->create();
    }

    private function pruner(): User
    {
        return User::factory()
            ->for(Role::factory()->granting(Ability::ViewEventLog, Ability::ManageEventLog), 'role')
            ->create();
    }
}
