<?php

namespace Tests\Feature\Console;

use App\Enums\AuditAction;
use App\Enums\ExecutionStatus;
use App\Http\Requests\Audit\EventLogPruneRequest;
use App\Models\AuditEvent;
use App\Models\Execution;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * The three jobs the scheduler runs unattended: event-log retention,
 * abandoned drafts, and expired API tokens.
 */
class HousekeepingTest extends TestCase
{
    use RefreshDatabase;

    public function test_housekeeping_is_scheduled_daily(): void
    {
        $events = collect(app(Schedule::class)->events());

        $this->assertTrue($this->scheduleHas($events, 'app:prune-event-log'));
        $this->assertTrue($this->scheduleHas($events, 'app:prune-execution-drafts'));
        $this->assertTrue($this->scheduleHas($events, 'sanctum:prune-expired'));
        $this->assertTrue($this->scheduleHas($events, '--hours=24'));

        $housekeeping = $events->filter(
            fn ($event): bool => str_contains((string) $event->command, 'app:prune-event-log')
                || str_contains((string) $event->command, 'app:prune-execution-drafts')
                || str_contains((string) $event->command, 'sanctum:prune-expired'),
        );

        $this->assertCount(3, $housekeeping);

        foreach ($housekeeping as $event) {
            $this->assertSame('0 0 * * *', $event->expression);
        }
    }

    public function test_the_event_log_command_discards_old_records_without_an_actor(): void
    {
        $old = AuditEvent::factory()->create(['created_at' => now()->subDays(120)]);
        $recent = AuditEvent::factory()->create(['created_at' => now()->subDay()]);

        $this->artisan('app:prune-event-log', ['--days' => 90])->assertSuccessful();

        $this->assertDatabaseMissing('audit_events', ['id' => $old->getKey()]);
        $this->assertDatabaseHas('audit_events', ['id' => $recent->getKey()]);

        $event = AuditEvent::query()->where('action', AuditAction::EventLogPruned->value)->sole();

        $this->assertNull($event->user_id);
        $this->assertSame(1, $event->properties['discarded']);
        $this->assertSame(90, $event->properties['keep_days']);
    }

    public function test_the_event_log_command_refuses_a_window_shorter_than_the_screen(): void
    {
        $event = AuditEvent::factory()->create(['created_at' => now()->subDay()]);

        $this->artisan('app:prune-event-log', ['--days' => 1])->assertFailed();

        $this->assertDatabaseHas('audit_events', ['id' => $event->getKey()]);
        $this->assertSame(30, EventLogPruneRequest::MINIMUM_KEEP_DAYS);
    }

    public function test_abandoned_drafts_are_discarded_and_completed_runs_are_kept(): void
    {
        $abandoned = Execution::factory()->create([
            'is_draft' => true,
            'status' => ExecutionStatus::Failed,
            'created_at' => now()->subDays(40),
            'updated_at' => now()->subDays(40),
        ]);
        $active = Execution::factory()->create(['is_draft' => true]);
        $completed = Execution::factory()->completed()->create([
            'created_at' => now()->subDays(40),
            'updated_at' => now()->subDays(40),
        ]);

        $this->artisan('app:prune-execution-drafts', ['--days' => 30])->assertSuccessful();

        $this->assertDatabaseMissing('executions', ['id' => $abandoned->getKey()]);
        $this->assertDatabaseHas('executions', ['id' => $active->getKey()]);
        $this->assertDatabaseHas('executions', ['id' => $completed->getKey()]);

        $event = AuditEvent::query()->where('action', AuditAction::ExecutionDraftsPruned->value)->sole();

        $this->assertNull($event->user_id);
        $this->assertSame(1, $event->properties['discarded']);
        $this->assertSame(30, $event->properties['keep_days']);
    }

    public function test_a_recently_saved_old_draft_is_kept(): void
    {
        $draft = Execution::factory()->create([
            'is_draft' => true,
            'created_at' => now()->subDays(40),
            'updated_at' => now()->subDay(),
        ]);

        $this->artisan('app:prune-execution-drafts', ['--days' => 30])->assertSuccessful();

        $this->assertDatabaseHas('executions', ['id' => $draft->getKey()]);
    }

    public function test_expired_api_tokens_are_pruned_after_the_grace_window(): void
    {
        $user = User::factory()->create();
        $expired = $user->createToken('stale', ['*'], now()->subDays(3))->accessToken;
        $fresh = $user->createToken('live', ['*'], now()->addDay())->accessToken;

        $this->artisan('sanctum:prune-expired', ['--hours' => 24])->assertSuccessful();

        $this->assertNull(PersonalAccessToken::query()->find($expired->id));
        $this->assertNotNull(PersonalAccessToken::query()->find($fresh->id));
    }

    /**
     * @param  Collection<int, mixed>  $events
     */
    private function scheduleHas($events, string $needle): bool
    {
        return $events->contains(
            fn ($event): bool => str_contains((string) $event->command, $needle),
        );
    }
}
