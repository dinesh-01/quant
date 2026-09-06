<?php

namespace App\Http\Controllers\Audit;

use App\Actions\Audit\PruneEventLog;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Audit\EventLogPruneRequest;
use App\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Reads the audit trail.
 *
 * The sentence a reader sees is composed here and in the page, from `action`
 * and `properties`, rather than having been frozen into the row when it was
 * written — that is the point of storing facts instead of legacy's rendered
 * `events.description` string, and it is why the wording can improve without
 * rewriting history.
 */
class EventLogController extends Controller
{
    /**
     * Kept small: each row can carry a change set that expands, so a long page
     * is harder to read than another click.
     */
    private const PER_PAGE = 50;

    public function index(Request $request): Response
    {
        Gate::authorize(Ability::ViewEventLog->value);

        $action = (string) $request->query('action', '');
        $actor = mb_substr(trim((string) $request->query('actor', '')), 0, 255);

        $events = AuditEvent::query()
            /**
             * `subject` is a morph, so eager loading it is what keeps this from
             * one query per row per type; `user` likewise. Both are needed by
             * every row that has them.
             */
            ->with(['user:id,name,email', 'subject'])
            ->when(
                AuditAction::tryFrom($action) instanceof AuditAction,
                fn ($query) => $query->where('action', $action),
            )
            ->when($actor !== '', fn ($query) => $query->whereHas(
                'user',
                fn ($user) => $user
                    ->whereLike('name', "%{$actor}%")
                    ->orWhereLike('email', "%{$actor}%"),
            ))
            /** Newest first, then by id so rows sharing a second stay in a stable order. */
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(fn (AuditEvent $event): array => [
                'id' => $event->id,
                'action' => $event->action,
                'label' => AuditAction::tryFrom($event->action)?->label() ?? $event->action,
                'actor' => $event->user === null ? null : [
                    'name' => $event->user->name,
                    'email' => $event->user->email,
                ],
                'subject' => $this->subject($event),
                'properties' => $event->properties,
                'ip_address' => $event->ip_address,
                'recorded_at' => $event->created_at?->toIso8601String(),
            ]);

        return Inertia::render('audit/index', [
            'events' => $events,
            'filters' => ['action' => $action, 'actor' => $actor],
            'actions' => $this->actionOptions(),
            'can' => ['prune' => Gate::allows(Ability::ManageEventLog->value)],
        ]);
    }

    public function destroy(EventLogPruneRequest $request, PruneEventLog $pruneEventLog): RedirectResponse
    {
        $discarded = $pruneEventLog($request->user(), $request->keepDays());

        return to_route('events.index')->with('status', trans_choice(
            '{0}No records were old enough to discard.|[1,*]Discarded :count records.',
            $discarded,
            ['count' => $discarded],
        ));
    }

    /**
     * What was acted on, for display.
     *
     * The subject may be gone — a deleted role, say — in which case the morph
     * resolves to null and the name recorded in `properties` at the time is
     * the only thing left that identifies it. That fallback is the reason the
     * delete actions copy the name in.
     *
     * @return array{type: string, id: int, name: string|null}|null
     */
    private function subject(AuditEvent $event): ?array
    {
        if ($event->subject_type === null || $event->subject_id === null) {
            return null;
        }

        $recorded = $event->properties['name'] ?? null;

        return [
            'type' => str(class_basename($event->subject_type))->headline()->lower()->ucfirst()->value(),
            'id' => $event->subject_id,
            'name' => $this->name($event->subject) ?? (is_string($recorded) ? $recorded : null),
        ];
    }

    /**
     * Every model the trail can point at happens to carry a `name`, so this
     * reads the attribute rather than listing the classes — a new subject type
     * then needs no change here.
     */
    private function name(?Model $subject): ?string
    {
        $name = $subject?->getAttribute('name');

        return is_string($name) ? $name : null;
    }

    /**
     * The filter's options, so a reader can narrow by act without knowing the
     * stored values. Enumerable only because the actions are an enum.
     *
     * @return list<array{value: string, label: string}>
     */
    private function actionOptions(): array
    {
        return array_map(
            fn (AuditAction $action): array => ['value' => $action->value, 'label' => $action->label()],
            AuditAction::cases(),
        );
    }
}
