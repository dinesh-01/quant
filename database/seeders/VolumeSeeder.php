<?php

namespace Database\Seeders;

use App\Enums\ExecutionStatus;
use App\Enums\TestCaseExecutionType;
use App\Enums\TestCaseImportance;
use App\Enums\TestCaseStatus;
use App\Enums\TestCaseUrgency;
use App\Enums\TestPlanStatus;
use App\Models\Build;
use App\Models\Execution;
use App\Models\ExecutionIssue;
use App\Models\Keyword;
use App\Models\Platform;
use App\Models\TestCase;
use App\Models\TestCaseScriptLink;
use App\Models\TestCaseVersion;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestProject;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class VolumeSeeder extends Seeder
{
    /**
     * The mockups show a Checkout project of 482 cases. Anything else makes the
     * screens impossible to compare against them.
     */
    public const TARGET_CASES = 482;

    public const TARGET_SUITES = 30;

    /**
     * Top up Checkout so the mockup screens have a full suite tree, case list,
     * and Active / Draft / Archived plan cards.
     */
    public function run(): void
    {
        $project = TestProject::query()->where('prefix', DemoSeeder::CHECKOUT_PREFIX)->first();

        if ($project === null) {
            $this->command?->warn('Checkout (CO) is missing. Run DatabaseSeeder first.');

            return;
        }

        $author = User::query()->where('email', 'devon.designer@example.com')->first()
            ?? User::query()->where('email', 'test@example.com')->firstOrFail();

        $alreadyFilled = $project->testCases()->count() >= self::TARGET_CASES;

        if (! $alreadyFilled) {
            $suites = $this->seedSuites($project);
            $versions = $this->seedCases($project, $suites, $author);
            $this->seedPlans($project, $author, $versions);
        }

        $this->seedIssues($project);

        $this->command?->info(sprintf(
            'Volume seed: %d suites, %d cases, 4 active / 3 draft / 2 archived plans.',
            $project->testSuites()->count(),
            $project->testCases()->count(),
        ));
    }

    /**
     * @return list<TestSuite>
     */
    private function seedSuites(TestProject $project): array
    {
        $parent = TestSuite::query()
            ->where('test_project_id', $project->id)
            ->whereNull('parent_id')
            ->orderBy('id')
            ->first();

        $names = [
            'Cart', 'Payments', 'Promotions', 'Shipping & Tax', 'Guest Flow',
            'Mobile', 'Accessibility', 'Authentication', 'Wallet', 'Addresses',
            'Inventory', 'Notifications', 'Search', 'Wishlist', 'Returns',
            'Gift Cards', 'Loyalty', 'Subscriptions', 'Express Checkout', '3-D Secure',
            'Tax Engine', 'Coupons', 'Pickup', 'Delivery', 'International',
            'Performance', 'Security', 'Analytics', 'Edge Cases', 'Receipts',
        ];

        $existing = TestSuite::query()
            ->where('test_project_id', $project->id)
            ->whereIn('name', $names)
            ->get()
            ->keyBy('name');

        $sort = (int) TestSuite::query()->where('test_project_id', $project->id)->max('sort_order');
        $suites = [];

        foreach ($names as $name) {
            if ($existing->has($name)) {
                $suites[] = $existing->get($name);

                continue;
            }

            $sort++;
            $suite = new TestSuite;
            $suite->forceFill([
                'test_project_id' => $project->id,
                'parent_id' => $parent?->id,
                'name' => $name,
                'description' => '<p>Volume-seeded suite for the '.$name.' area.</p>',
                'sort_order' => $sort,
            ]);
            $suite->save();
            $suites[] = $suite;
        }

        return $suites;
    }

    /**
     * @param  list<TestSuite>  $suites
     * @return list<int>
     */
    private function seedCases(TestProject $project, array $suites, User $author): array
    {
        $needed = self::TARGET_CASES - $project->testCases()->count();

        if ($needed <= 0) {
            return TestCaseVersion::query()
                ->whereHas('testCase', fn ($query) => $query->where('test_project_id', $project->id))
                ->where('version', 1)
                ->orderBy('id')
                ->pluck('id')
                ->all();
        }

        $firstExternalId = DB::transaction(function () use ($project, $needed): int {
            $locked = TestProject::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $start = $locked->test_case_counter + 1;
            $locked->test_case_counter = $locked->test_case_counter + $needed;
            $locked->save();

            return $start;
        });

        $now = now();
        $importances = [
            TestCaseImportance::High->value,
            TestCaseImportance::Medium->value,
            TestCaseImportance::Low->value,
        ];
        $keywords = Keyword::query()
            ->where('test_project_id', $project->id)
            ->pluck('id')
            ->all();

        $caseRows = [];
        $sortBySuite = [];

        for ($index = 0; $index < $needed; $index++) {
            $suite = $suites[$index % count($suites)];
            $sortBySuite[$suite->id] = ($sortBySuite[$suite->id] ?? $suite->testCases()->count()) + 1;
            $caseRows[] = [
                'test_project_id' => $project->id,
                'test_suite_id' => $suite->id,
                'external_id' => $firstExternalId + $index,
                'name' => $suite->name.' case '.($index + 1),
                'sort_order' => $sortBySuite[$suite->id],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($caseRows, 250) as $chunk) {
            TestCase::query()->insert($chunk);
        }

        $cases = TestCase::query()
            ->where('test_project_id', $project->id)
            ->where('external_id', '>=', $firstExternalId)
            ->orderBy('external_id')
            ->get(['id', 'external_id', 'test_suite_id']);

        $versionRows = [];
        $stepRows = [];

        foreach ($cases as $offset => $case) {
            $frozen = $offset % 10 === 0;
            $automated = $offset % 5 !== 0;
            $versionRows[] = [
                'test_case_id' => $case->id,
                'version' => 1,
                'status' => $frozen ? TestCaseStatus::Final->value : TestCaseStatus::ReadyForReview->value,
                'summary' => '<p>Verify '.$case->name.' on the current Checkout build.</p>',
                'preconditions' => '<p>Signed-in shopper with an active cart.</p>',
                'importance' => $importances[$offset % 3],
                'execution_type' => $automated
                    ? TestCaseExecutionType::Automated->value
                    : TestCaseExecutionType::Manual->value,
                'estimated_duration' => 8,
                'is_open' => ! $frozen,
                'author_id' => $author->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($versionRows, 250) as $chunk) {
            TestCaseVersion::query()->insert($chunk);
        }

        $versions = TestCaseVersion::query()
            ->whereIn('test_case_id', $cases->modelKeys())
            ->orderBy('id')
            ->get(['id', 'test_case_id', 'execution_type']);

        foreach ($versions as $version) {
            $stepRows[] = [
                'test_case_version_id' => $version->id,
                'sort_order' => 1,
                'actions' => '<p>Open the Checkout flow and perform the named action.</p>',
                'expected_results' => '<p>The UI matches the fixture and no error is shown.</p>',
                'execution_type' => $version->execution_type->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $stepRows[] = [
                'test_case_version_id' => $version->id,
                'sort_order' => 2,
                'actions' => '<p>Confirm the order summary and continue.</p>',
                'expected_results' => '<p>Totals, tax and discounts stay consistent.</p>',
                'execution_type' => $version->execution_type->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($stepRows, 250) as $chunk) {
            DB::table('test_case_steps')->insert($chunk);
        }

        if ($keywords !== []) {
            $pivots = [];

            foreach ($cases as $offset => $case) {
                $pivots[] = [
                    'keyword_id' => $keywords[$offset % count($keywords)],
                    'test_case_id' => $case->id,
                ];
            }

            foreach (array_chunk($pivots, 250) as $chunk) {
                DB::table('keyword_test_case')->insert($chunk);
            }
        }

        $scriptRows = [];

        foreach ($versions as $offset => $version) {
            if ($version->execution_type !== TestCaseExecutionType::Automated) {
                continue;
            }

            $scriptRows[] = [
                'test_case_version_id' => $version->id,
                'project_key' => 'acme/checkout',
                'repository' => 'checkout-e2e',
                'path' => 'spec/checkout/case_'.$version->test_case_id.'_spec.ts',
                'branch' => 'main',
                'commit' => 'volume'.$offset,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($scriptRows, 250) as $chunk) {
            TestCaseScriptLink::query()->insert($chunk);
        }

        return $versions->pluck('id')->all();
    }

    /**
     * @param  list<int>  $versionIds
     */
    private function seedPlans(TestProject $project, User $author, array $versionIds): void
    {
        $existing = $project->testPlans()->get()->keyBy('name');

        if ($existing->has('Release 2.4')) {
            $existing->get('Release 2.4')->forceFill([
                'status' => TestPlanStatus::Active,
                'is_active' => true,
                'is_open' => true,
            ])->save();
        }

        if ($existing->has('Regression')) {
            $existing->get('Regression')->forceFill([
                'status' => TestPlanStatus::Archived,
                'is_active' => false,
                'is_open' => false,
            ])->save();
        }

        $definitions = [
            ['Checkout · Regression', TestPlanStatus::Active, true],
            ['Payments · Smoke', TestPlanStatus::Active, true],
            ['Mobile Checkout · Sanity', TestPlanStatus::Active, true],
            ['Holiday Promo', TestPlanStatus::Draft, false],
            ['Wallet Rework', TestPlanStatus::Draft, false],
            ['Tax Engine v2', TestPlanStatus::Draft, false],
            ['4.6 GA Archive', TestPlanStatus::Archived, false],
        ];

        $created = [];

        foreach ($definitions as [$name, $status, $open]) {
            if ($project->testPlans()->where('name', $name)->exists()) {
                continue;
            }

            $plan = new TestPlan;
            $plan->forceFill([
                'test_project_id' => $project->id,
                'name' => $name,
                'description' => '<p>Volume-seeded '.$status->label().' plan.</p>',
                'is_active' => $status->isListed(),
                'is_open' => $open,
                'is_public' => true,
                'status' => $status,
            ]);
            $plan->save();
            $created[] = $plan;
        }

        $active = $project->testPlans()
            ->where('status', TestPlanStatus::Active)
            ->orderBy('id')
            ->get();

        $chrome = Platform::query()->where('test_project_id', $project->id)->orderBy('id')->first();
        $slice = array_slice($versionIds, 0, 400);
        $now = now();

        foreach ($active as $index => $plan) {
            if ($plan->items()->exists()) {
                continue;
            }

            $build = Build::factory()->for($plan, 'testPlan')->create([
                'name' => $index === 2 ? '4.8-rc' : '4.8',
                'author_id' => $author->id,
                'is_open' => $plan->is_open,
            ]);

            if ($chrome !== null) {
                $plan->platforms()->syncWithoutDetaching([$chrome->id]);
            }

            $chunk = array_slice($slice, $index * 80, 80);
            $itemRows = [];

            foreach ($chunk as $sort => $versionId) {
                $itemRows[] = [
                    'test_plan_id' => $plan->id,
                    'test_case_version_id' => $versionId,
                    'platform_id' => $chrome?->id,
                    'sort_order' => $sort,
                    'urgency' => TestCaseUrgency::Medium->value,
                    'author_id' => $author->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            if ($itemRows !== []) {
                TestPlanItem::query()->insert($itemRows);
            }

            $items = $plan->items()->orderBy('id')->get();
            $executionRows = [];
            $mix = [
                ExecutionStatus::Passed,
                ExecutionStatus::Passed,
                ExecutionStatus::Passed,
                ExecutionStatus::Failed,
                ExecutionStatus::Blocked,
                ExecutionStatus::NotRun,
            ];

            foreach ($items as $offset => $item) {
                $status = $mix[$offset % count($mix)];

                if ($status === ExecutionStatus::NotRun) {
                    continue;
                }

                $executionRows[] = [
                    'test_plan_id' => $plan->id,
                    'build_id' => $build->id,
                    'test_plan_item_id' => $item->id,
                    'test_case_version_id' => $item->test_case_version_id,
                    'version' => 1,
                    'tester_id' => $author->id,
                    'status' => $status->value,
                    'notes' => null,
                    'duration' => 4.5,
                    'is_draft' => false,
                    'executed_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($executionRows, 200) as $chunkRows) {
                DB::table('executions')->insert($chunkRows);
            }
        }
    }

    /**
     * Link enough failed / blocked runs so the Issues screen matches the mock.
     */
    private function seedIssues(TestProject $project): void
    {
        $existing = ExecutionIssue::query()
            ->whereHas(
                'execution.testPlan',
                fn ($query) => $query->where('test_project_id', $project->id),
            )
            ->count();

        if ($existing >= 20) {
            return;
        }

        $summaries = [
            ['Guest checkout with saved address fails', 'Open'],
            ['3-D Secure challenge times out on Visa', 'In Progress'],
            ['Apply expired promo code shows wrong error', 'Open'],
            ['Tax recalculates on address change', 'Triage'],
            ['Discount not applied on mobile Safari', 'Triage'],
            ['Apple Pay sheet dismisses on rotate', 'Open'],
            ['Stacked promo total off by discount order', 'Resolved'],
            ['Free-shipping threshold ignores gift wrap', 'Open'],
            ['Wallet balance does not refresh after refund', 'In Progress'],
            ['Saved address book drops apartment line', 'Open'],
            ['Express checkout skips terms checkbox', 'Triage'],
            ['Inventory hold released too early', 'Open'],
        ];

        $runs = Execution::query()
            ->where('is_draft', false)
            ->whereIn('status', [ExecutionStatus::Failed, ExecutionStatus::Blocked])
            ->whereHas('testPlan', fn ($query) => $query->where('test_project_id', $project->id))
            ->whereDoesntHave('issues')
            ->orderBy('id')
            ->limit(24)
            ->get();

        $now = now();
        $rows = [];

        foreach ($runs as $offset => $execution) {
            [$summary, $status] = $summaries[$offset % count($summaries)];
            $key = 'JIRA-'.(4800 + $offset);

            $rows[] = [
                'execution_id' => $execution->id,
                'issue_id' => $key,
                'issue_url' => 'https://acme.atlassian.net/browse/'.$key,
                'issue_status' => $status,
                'issue_summary' => $summary,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('execution_issues')->insert($chunk);
        }
    }
}
