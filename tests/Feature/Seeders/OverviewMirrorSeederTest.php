<?php

namespace Tests\Feature\Seeders;

use App\Enums\ExecutionStatus;
use App\Models\Execution;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\TestSuite;
use App\Models\User;
use App\Reports\ProjectOverviewReport;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\OverviewMirrorSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The seeded figures are what makes the Overview comparable to
 * `public/ui-mockups/index.html`, so they are asserted rather than eyeballed.
 */
class OverviewMirrorSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_produces_the_figures_the_overview_mockup_shows(): void
    {
        Storage::fake('attachments');
        $this->travelTo('2026-09-11 14:00:00');

        $this->seed(DatabaseSeeder::class);
        $this->seed(OverviewMirrorSeeder::class);

        $checkout = TestProject::query()->where('prefix', 'CO')->firstOrFail();
        $maya = User::query()->where('email', 'maya.leader@example.com')->firstOrFail();

        $this->assertSame('Maya Singh', $maya->name);
        $this->assertSame('Team Leader', $maya->role?->name);

        /** The sidebar counts: 7 suites, 9 plans, 42 issues. */
        $this->assertSame(7, $checkout->testSuites()->count());
        $this->assertSame(9, $checkout->testPlans()->count());
        $this->assertSame(
            [88, 120, 46, 64, 52, 72, 40],
            $checkout->testSuites()->orderBy('sort_order')->get()
                ->map(fn (TestSuite $suite): int => $suite->testCases()->count())
                ->all(),
        );

        $overview = app(ProjectOverviewReport::class)($checkout, $maya);

        $this->assertSame(OverviewMirrorSeeder::CASES, $overview['cases']);
        $this->assertSame(OverviewMirrorSeeder::AUTOMATED, $overview['automated']);
        $this->assertSame(OverviewMirrorSeeder::NEW_THIS_WEEK, $overview['cases_this_week']);
        $this->assertSame(3, $overview['active_runs']);
        $this->assertSame(2, $overview['runs_in_progress']);
        $this->assertSame(1, $overview['runs_blocked']);

        $this->assertSame('4.8', $overview['latest_build']['name']);
        $this->assertSame(
            ['passed' => 211, 'failed' => 28, 'blocked' => 14, 'not_run' => 45],
            $overview['latest_build']['counts'],
        );
        $this->assertSame(83, $overview['pass_rate']);
        $this->assertSame('4.7', $overview['previous_build']['name']);
        $this->assertSame(-4, $overview['previous_build']['delta']);

        $this->assertSame(
            [
                ['CO-P12', 'Checkout · Regression', '4.8', ['Sam Sharma', 'Rina Thakur', 'Devon Diaz']],
                ['CO-P14', 'Payments · Smoke', '4.8', ['Alex Rivera', 'Sam Sharma']],
                ['CO-P15', 'Mobile Checkout · Sanity', '4.8-rc', ['Rina Thakur']],
            ],
            array_map(
                fn (array $run): array => [$run['external_id'], $run['name'], $run['build'], $run['assignees']],
                $overview['runs'],
            ),
        );
    }

    public function test_it_seeds_the_cases_and_activity_the_mockup_names(): void
    {
        Storage::fake('attachments');
        $this->travelTo('2026-09-11 14:00:00');

        $this->seed(DatabaseSeeder::class);
        $this->seed(OverviewMirrorSeeder::class);

        $checkout = TestProject::query()->where('prefix', 'CO')->firstOrFail();
        $maya = User::query()->where('email', 'maya.leader@example.com')->firstOrFail();

        $overview = app(ProjectOverviewReport::class)($checkout, $maya);

        $this->assertSame(
            [
                ['CO-TC-241', 'Apply expired promo code shows error', 'failed', 'high', 'Rina Thakur', 'JIRA-4821'],
                ['CO-TC-198', 'Guest checkout with saved address', 'failed', 'high', 'Sam Sharma', 'JIRA-4820'],
                ['CO-TC-305', '3-D Secure challenge on Visa', 'blocked', 'medium', 'Alex Rivera', null],
                ['CO-TC-277', 'Tax recalculates on address change', 'failed', 'medium', 'Devon Diaz', null],
            ],
            array_map(fn (array $row): array => [
                $row['external_id'],
                $row['name'],
                $row['status'],
                $row['priority'],
                $row['tester'],
                $row['issue'],
            ], $overview['needs_attention']),
        );

        $this->assertSame('sandbox down', $overview['needs_attention'][2]['issue_note']);
        $this->assertNull($overview['needs_attention'][3]['issue_note']);

        $this->assertSame(
            [
                ['Sam', 'passed', 'CO-TC-190', null, '12 min ago'],
                ['Rina', 'failed', 'CO-TC-241', '→ JIRA-4821', '28 min ago'],
                ['Devon', 'added 6 cases to', 'Promotions', null, '1 hr ago'],
                ['Maya', 'froze version', 'v3', 'of CO-TC-100', '2 hr ago'],
                ['Alex', 'created build', 'Build 4.8-rc', null, '3 hr ago'],
            ],
            array_map(
                fn (array $row): array => [$row['actor'], $row['verb'], $row['token'], $row['tail'], $row['when']],
                $overview['activity'],
            ),
        );

        $this->assertTrue($overview['activity'][3]['is_viewer']);

        $promotions = $checkout->testSuites()->where('name', 'Promotions')->firstOrFail();
        $featured = $promotions->testCases()
            ->orderBy('sort_order')
            ->limit(7)
            ->get()
            ->map(function (TestCaseModel $case): array {
                $latest = $case->versions->last();

                return [
                    $case->fullExternalId(),
                    $case->name,
                    $latest?->version,
                    (bool) $latest?->is_open,
                    $case->keywords()->orderBy('name')->value('name'),
                ];
            })
            ->all();

        $this->assertSame(
            [
                ['CO-TC-240', 'Apply valid percentage promo code', 4, true, 'smoke'],
                ['CO-TC-241', 'Apply expired promo code shows error', 2, true, 'regression'],
                ['CO-TC-242', 'Stacking two promo codes is rejected', 1, true, 'regression'],
                ['CO-TC-243', 'Free-shipping threshold promo', 3, false, 'smoke'],
                ['CO-TC-244', 'Promo code case-insensitivity', 1, true, 'edge'],
                ['CO-TC-245', 'Remove applied promo restores total', 2, true, 'regression'],
                ['CO-TC-246', 'First-order-only promo for returning user', 1, true, 'regression'],
            ],
            $featured,
        );

        $this->assertSame(46, $promotions->testCases()->count());
        $this->assertSame(
            5,
            $promotions->testCases()->with('versions')->get()
                ->filter(fn (TestCaseModel $case): bool => $case->versions->last()?->is_open === false)
                ->count(),
        );

        $promo = TestCaseModel::query()
            ->where('test_project_id', $checkout->id)
            ->where('external_id', 240)
            ->firstOrFail();
        $latest = $promo->versions->last();
        $this->assertNotNull($latest);

        $this->assertSame('Devon Diaz', $latest->author?->name);
        $this->assertSame(
            now()->subDays(2)->toDateTimeString(),
            $latest->updated_at?->toDateTimeString(),
        );
        $this->assertSame(3, $latest->steps()->count());
        $this->assertSame(
            'checkout/promotions/promo_percent_spec.ts',
            $latest->scriptLinks()->value('path'),
        );
        $this->assertSame(
            ExecutionStatus::Passed,
            Execution::query()
                ->where('is_draft', false)
                ->whereIn('test_case_version_id', $promo->versions->modelKeys())
                ->orderByDesc('id')
                ->first()
                ?->status,
        );

        $this->assertSame(
            OverviewMirrorSeeder::ISSUES,
            TestPlan::query()
                ->where('test_project_id', $checkout->id)
                ->join('executions', 'executions.test_plan_id', '=', 'test_plans.id')
                ->join('execution_issues', 'execution_issues.execution_id', '=', 'executions.id')
                ->count(),
        );
    }
}
