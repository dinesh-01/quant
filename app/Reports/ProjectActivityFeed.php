<?php

namespace App\Reports;

use App\Enums\AuditAction;
use App\Enums\ExecutionStatus;
use App\Models\AuditEvent;
use App\Models\Build;
use App\Models\ExecutionIssue;
use App\Models\TestCase;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestProject;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The Overview activity list, written as sentences rather than action names.
 *
 * "Rina failed CO-TC-241 → JIRA-4821" tells a reader what happened;
 * "Execution completed" does not. The sentence is assembled from the recorded
 * action and its properties, so it stays true to the trail — this reads the
 * audit log, it does not keep a second history of its own.
 *
 * Consecutive cases added to the same suite by the same person collapse into
 * one line, because importing a suite otherwise buries every other act.
 */
final class ProjectActivityFeed
{
    /**
     * How many raw records to consider. Grouping means the feed may need
     * several records to fill one row.
     */
    private const SCAN = 60;

    /**
     * @return list<array{
     *     id: int,
     *     actor: string|null,
     *     is_viewer: bool,
     *     verb: string,
     *     token: string|null,
     *     token_style: 'mono'|'em'|'tag'|null,
     *     tail: string|null,
     *     when: string|null,
     *     at: string|null
     * }>
     */
    public function __invoke(TestProject $project, ?User $viewer = null, int $limit = 5): array
    {
        $events = $this->events($project);

        if ($events->isEmpty()) {
            return [];
        }

        $cases = $this->casesFor($events);
        $items = $this->itemsFor($events);
        $issues = $this->issuesFor($events);
        $builds = $this->buildsFor($events);
        $suites = $this->suitesFor($events);

        $rows = [];
        $index = 0;

        while ($index < $events->count() && count($rows) < $limit) {
            $event = $events[$index];
            $action = AuditAction::tryFrom($event->action);

            if ($action === AuditAction::TestCaseCreated) {
                $span = $this->countAdditions($events, $index);
                $suiteId = (int) data_get($event->properties, 'test_suite_id');

                $rows[] = $this->row($event, $viewer, [
                    'verb' => $span === 1 ? 'added a case to' : 'added '.$span.' cases to',
                    'token' => $suites->get($suiteId)?->name,
                    'token_style' => 'em',
                ]);

                $index += $span;

                continue;
            }

            $rows[] = $this->row(
                $event,
                $viewer,
                $this->phrase($event, $action, $cases, $items, $issues, $builds),
            );

            $index++;
        }

        return $rows;
    }

    /**
     * Records for anything inside this project: the project, its plans, and its
     * cases. Execution acts are recorded against the plan, which is why plans
     * are in this list and not only cases.
     *
     * @return EloquentCollection<int, AuditEvent>
     */
    private function events(TestProject $project): EloquentCollection
    {
        $planIds = $project->testPlans()->select('id');
        $caseIds = $project->testCases()->select('id');

        return AuditEvent::query()
            ->with('user:id,name')
            ->where(function ($query) use ($project, $planIds, $caseIds): void {
                $query
                    ->where(function ($inner) use ($project): void {
                        $inner->where('subject_type', $project->getMorphClass())
                            ->where('subject_id', $project->id);
                    })
                    ->orWhere(function ($inner) use ($planIds): void {
                        $inner->where('subject_type', (new TestPlan)->getMorphClass())
                            ->whereIn('subject_id', $planIds);
                    })
                    ->orWhere(function ($inner) use ($caseIds): void {
                        $inner->where('subject_type', (new TestCase)->getMorphClass())
                            ->whereIn('subject_id', $caseIds);
                    })
                    ->orWhere(function ($inner) use ($planIds): void {
                        $inner->where('subject_type', (new Build)->getMorphClass())
                            ->whereIn(
                                'subject_id',
                                Build::query()->select('id')->whereIn('test_plan_id', $planIds),
                            );
                    });
            })
            ->orderByDesc('id')
            ->limit(self::SCAN)
            ->get();
    }

    /**
     * How many records from this position are the same person adding cases to
     * the same suite.
     *
     * @param  EloquentCollection<int, AuditEvent>  $events
     */
    private function countAdditions(EloquentCollection $events, int $from): int
    {
        $first = $events[$from];
        $suite = data_get($first->properties, 'test_suite_id');
        $span = 1;

        while ($from + $span < $events->count()) {
            $next = $events[$from + $span];

            if (
                $next->action !== AuditAction::TestCaseCreated->value
                || $next->user_id !== $first->user_id
                || data_get($next->properties, 'test_suite_id') !== $suite
            ) {
                break;
            }

            $span++;
        }

        return $span;
    }

    /**
     * @param  Collection<int, TestCase>  $cases
     * @param  Collection<int, TestPlanItem>  $items
     * @param  Collection<int, string>  $issues
     * @param  Collection<int, Build>  $builds
     * @return array{verb: string, token?: string|null, token_style?: string|null, tail?: string|null}
     */
    private function phrase(
        AuditEvent $event,
        ?AuditAction $action,
        Collection $cases,
        Collection $items,
        Collection $issues,
        Collection $builds,
    ): array {
        $fallback = ['verb' => $action?->label() ?? $event->action];

        return match ($action) {
            AuditAction::ExecutionCompleted => $this->executionPhrase($event, $items, $issues),
            AuditAction::TestCaseVersionFrozen => [
                'verb' => 'froze version',
                'token' => 'v'.data_get($event->properties, 'version'),
                'token_style' => 'mono',
                'tail' => 'of '.($cases->get((int) $event->subject_id)?->fullExternalId() ?? 'a case'),
            ],
            AuditAction::TestCaseRenamed => [
                'verb' => 'renamed',
                'token' => $cases->get((int) $event->subject_id)?->fullExternalId(),
                'token_style' => 'mono',
            ],
            AuditAction::BuildCreated => [
                'verb' => 'created build',
                'token' => $this->buildName($event, $builds),
                'token_style' => 'tag',
            ],
            default => $fallback,
        };
    }

    /**
     * @param  Collection<int, TestPlanItem>  $items
     * @param  Collection<int, string>  $issues
     * @return array{verb: string, token?: string|null, token_style?: string|null, tail?: string|null}
     */
    private function executionPhrase(AuditEvent $event, Collection $items, Collection $issues): array
    {
        $status = ExecutionStatus::tryFrom((string) data_get($event->properties, 'status'));
        $item = $items->get((int) data_get($event->properties, 'item_id'));
        $issue = $issues->get((int) data_get($event->properties, 'execution_id'));

        $verb = match ($status) {
            ExecutionStatus::Passed => 'passed',
            ExecutionStatus::Failed => 'failed',
            ExecutionStatus::Blocked => 'blocked',
            default => 'ran',
        };

        return [
            'verb' => $verb,
            'token' => $item?->testCaseVersion->testCase->fullExternalId(),
            'token_style' => 'mono',
            'tail' => $issue === null ? null : '→ '.$issue,
        ];
    }

    /**
     * @param  Collection<int, Build>  $builds
     */
    private function buildName(AuditEvent $event, Collection $builds): ?string
    {
        $name = data_get($event->properties, 'name.to')
            ?? data_get($event->properties, 'name')
            ?? $builds->get((int) $event->subject_id)?->name;

        if (! is_string($name) || $name === '') {
            return null;
        }

        return str_starts_with(mb_strtolower($name), 'build') ? $name : 'Build '.$name;
    }

    /**
     * @param  array{verb: string, token?: string|null, token_style?: string|null, tail?: string|null}  $phrase
     * @return array{id: int, actor: string|null, is_viewer: bool, verb: string, token: string|null, token_style: string|null, tail: string|null, when: string|null, at: string|null}
     */
    private function row(AuditEvent $event, ?User $viewer, array $phrase): array
    {
        return [
            'id' => $event->id,
            'actor' => $event->user === null ? null : $this->firstName($event->user->name),
            'is_viewer' => $viewer !== null && $event->user_id === $viewer->id,
            'verb' => $phrase['verb'],
            'token' => $phrase['token'] ?? null,
            'token_style' => $phrase['token_style'] ?? null,
            'tail' => $phrase['tail'] ?? null,
            'when' => $this->relative($event->created_at),
            'at' => $event->created_at?->toIso8601String(),
        ];
    }

    private function firstName(string $name): string
    {
        return explode(' ', trim($name))[0];
    }

    /**
     * @param  EloquentCollection<int, AuditEvent>  $events
     * @return Collection<int, TestCase>
     */
    private function casesFor(EloquentCollection $events): Collection
    {
        $ids = $events
            ->where('subject_type', (new TestCase)->getMorphClass())
            ->pluck('subject_id')
            ->filter()
            ->unique();

        if ($ids->isEmpty()) {
            return collect();
        }

        return TestCase::query()
            ->with('testProject:id,prefix')
            ->whereKey($ids)
            ->get()
            ->keyBy('id');
    }

    /**
     * @param  EloquentCollection<int, AuditEvent>  $events
     * @return Collection<int, TestPlanItem>
     */
    private function itemsFor(EloquentCollection $events): Collection
    {
        $ids = $events
            ->map(fn (AuditEvent $event) => data_get($event->properties, 'item_id'))
            ->filter()
            ->unique();

        if ($ids->isEmpty()) {
            return collect();
        }

        return TestPlanItem::query()
            ->with('testCaseVersion.testCase.testProject:id,prefix')
            ->whereKey($ids)
            ->get()
            ->keyBy('id');
    }

    /**
     * The first issue linked to each execution the feed mentions.
     *
     * @param  EloquentCollection<int, AuditEvent>  $events
     * @return Collection<int, string>
     */
    private function issuesFor(EloquentCollection $events): Collection
    {
        $ids = $events
            ->map(fn (AuditEvent $event) => data_get($event->properties, 'execution_id'))
            ->filter()
            ->unique();

        if ($ids->isEmpty()) {
            return collect();
        }

        return ExecutionIssue::query()
            ->whereIn('execution_id', $ids)
            ->orderBy('id')
            ->get(['execution_id', 'issue_id'])
            ->groupBy('execution_id')
            ->map(fn ($group): string => (string) $group->first()->issue_id);
    }

    /**
     * @param  EloquentCollection<int, AuditEvent>  $events
     * @return Collection<int, Build>
     */
    private function buildsFor(EloquentCollection $events): Collection
    {
        $ids = $events
            ->where('subject_type', (new Build)->getMorphClass())
            ->pluck('subject_id')
            ->filter()
            ->unique();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Build::query()->whereKey($ids)->get(['id', 'name'])->keyBy('id');
    }

    /**
     * @param  EloquentCollection<int, AuditEvent>  $events
     * @return Collection<int, TestSuite>
     */
    private function suitesFor(EloquentCollection $events): Collection
    {
        $ids = $events
            ->where('action', AuditAction::TestCaseCreated->value)
            ->map(fn (AuditEvent $event) => data_get($event->properties, 'test_suite_id'))
            ->filter()
            ->unique();

        if ($ids->isEmpty()) {
            return collect();
        }

        return TestSuite::query()->whereKey($ids)->get(['id', 'name'])->keyBy('id');
    }

    /**
     * Short relative time, in the mockup's wording: "12 min ago", "3 hr ago".
     */
    private function relative(?\DateTimeInterface $at): ?string
    {
        if ($at === null) {
            return null;
        }

        $minutes = (int) round(Carbon::parse($at)->diffInMinutes(absolute: true));

        if ($minutes < 1) {
            return 'just now';
        }

        if ($minutes < 60) {
            return $minutes.' min ago';
        }

        $hours = intdiv($minutes, 60);

        if ($hours < 24) {
            return $hours.' hr ago';
        }

        $days = intdiv($hours, 24);

        return $days === 1 ? '1 day ago' : $days.' days ago';
    }
}
