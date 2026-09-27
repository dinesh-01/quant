<?php

namespace Database\Seeders;

use App\Enums\AuditAction;
use App\Enums\ExecutionStatus;
use App\Enums\TestCaseExecutionType;
use App\Enums\TestCaseImportance;
use App\Enums\TestCaseStatus;
use App\Enums\TestCaseUrgency;
use App\Enums\TesterAssignmentStatus;
use App\Enums\TestPlanStatus;
use App\Models\Build;
use App\Models\Execution;
use App\Models\Keyword;
use App\Models\Platform;
use App\Models\Role;
use App\Models\TestCase;
use App\Models\TestCaseStep;
use App\Models\TestCaseVersion;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestProject;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Reshapes Checkout into the dataset the Overview and Test Suites mockups show.
 *
 * Every figure on `public/ui-mockups/index.html` and the Promotions pane of
 * `public/ui-mockups/test-cases.html` is produced by real rows, so those
 * screens can be compared against the mockups without allowing for sample
 * data. Two of the Overview mockup's numbers cannot both be true and are
 * noted where they are seeded.
 *
 * Destructive: it deletes Checkout's surplus cases and rebuilds its active
 * plans. It only ever touches the demo project.
 */
class OverviewMirrorSeeder extends Seeder
{
    public const CASES = 482;

    public const AUTOMATED = 421;

    public const NEW_THIS_WEEK = 18;

    public const ISSUES = 42;

    /**
     * Cases the Needs attention card names, newest last: the card lists the
     * most recent failures first, so this is the order they are executed in.
     *
     * @var list<array{external_id: int, name: string, importance: TestCaseImportance, status: ExecutionStatus, tester: string, issue: string|null, notes: string|null}>
     */
    private const ATTENTION = [
        [
            'external_id' => 277,
            'name' => 'Tax recalculates on address change',
            'importance' => TestCaseImportance::Medium,
            'status' => ExecutionStatus::Failed,
            'tester' => 'devon.designer@example.com',
            'issue' => null,
            'notes' => null,
        ],
        [
            'external_id' => 305,
            'name' => '3-D Secure challenge on Visa',
            'importance' => TestCaseImportance::Medium,
            'status' => ExecutionStatus::Blocked,
            'tester' => 'alex.builder@example.com',
            'issue' => null,
            'notes' => 'sandbox down',
        ],
        [
            'external_id' => 198,
            'name' => 'Guest checkout with saved address',
            'importance' => TestCaseImportance::High,
            'status' => ExecutionStatus::Failed,
            'tester' => 'sam.senior@example.com',
            'issue' => 'JIRA-4820',
            'notes' => null,
        ],
        [
            'external_id' => 241,
            'name' => 'Apply expired promo code shows error',
            'importance' => TestCaseImportance::High,
            'status' => ExecutionStatus::Failed,
            'tester' => 'rina.tester@example.com',
            'issue' => 'JIRA-4821',
            'notes' => null,
        ],
    ];

    public function run(): void
    {
        $project = TestProject::query()->where('prefix', DemoSeeder::CHECKOUT_PREFIX)->first();

        if ($project === null) {
            $this->command?->warn('Checkout (CO) is missing. Run DatabaseSeeder first.');

            return;
        }

        /** Brings the project up to volume; this seeder then shapes it. */
        $this->callSilent(VolumeSeeder::class);

        $people = $this->people();

        $this->trimCases($project);
        $this->nameAttentionCases($project);
        $this->setAutomation($project);
        $this->ageCases($project);
        $this->reshapeSuites($project);

        $plans = $this->rebuildPlans($project);
        $this->seedRuns($project, $plans, $people);
        $this->topUpIssues($project);
        $this->seedActivity($project, $plans['regression'], $people);
        $this->shapePromotions($project, $people);

        $this->command?->info(sprintf(
            'Overview mirror: %d cases, %d automated, %d active plans.',
            $project->testCases()->count(),
            self::AUTOMATED,
            $project->testPlans()->where('status', TestPlanStatus::Active)->count(),
        ));
    }

    /**
     * The mockup's cast. Names are chosen for their initials, which is what the
     * avatars show.
     *
     * @return array<string, User>
     */
    private function people(): array
    {
        Role::query()->where('name', 'Leader')->update(['name' => 'Team Leader']);

        $names = [
            'maya.leader@example.com' => 'Maya Singh',
            'sam.senior@example.com' => 'Sam Sharma',
            'rina.tester@example.com' => 'Rina Thakur',
            'devon.designer@example.com' => 'Devon Diaz',
        ];

        foreach ($names as $email => $name) {
            User::query()->where('email', $email)->update(['name' => $name]);
        }

        if (! User::query()->where('email', 'alex.builder@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Alex Rivera',
                'email' => 'alex.builder@example.com',
                'role_id' => Role::query()->where('name', 'Senior Tester')->value('id'),
            ]);
        }

        return User::query()
            ->whereIn('email', [...array_keys($names), 'alex.builder@example.com'])
            ->get()
            ->keyBy('email')
            ->all();
    }

    /**
     * Cut the project down to the mockup's 482 cases.
     *
     * The lowest numbers are kept, which keeps the hand-built demo cases and
     * their steps, requirements and custom field values intact.
     */
    private function trimCases(TestProject $project): void
    {
        TestCase::query()
            ->where('test_project_id', $project->id)
            ->where('external_id', '>', self::CASES)
            ->delete();

        $project->forceFill(['test_case_counter' => self::CASES])->save();
    }

    /**
     * Give the cases the Needs attention card names their mockup names and
     * priorities.
     */
    private function nameAttentionCases(TestProject $project): void
    {
        foreach (self::ATTENTION as $row) {
            $case = TestCase::query()
                ->where('test_project_id', $project->id)
                ->where('external_id', $row['external_id'])
                ->first();

            if ($case === null) {
                continue;
            }

            $case->forceFill(['name' => $row['name']])->save();

            $case->versions()->orderBy('version')->first()
                ?->forceFill(['importance' => $row['importance']])
                ->save();
        }
    }

    /**
     * Exactly 421 of the 482 cases have a script linked, which is the mockup's
     * 87% automation coverage.
     */
    private function setAutomation(TestProject $project): void
    {
        $versions = TestCaseVersion::query()
            ->whereHas('testCase', fn ($query) => $query->where('test_project_id', $project->id))
            ->orderBy('test_case_id')
            ->orderBy('version')
            ->get(['id', 'test_case_id']);

        $automated = $versions->unique('test_case_id')->take(self::AUTOMATED);

        DB::table('test_case_script_links')->whereIn('test_case_version_id', $versions->modelKeys())->delete();

        $now = now();
        $rows = $automated->map(fn (TestCaseVersion $version): array => [
            'test_case_version_id' => $version->id,
            'project_key' => 'acme/checkout',
            'repository' => 'checkout-e2e',
            'path' => 'spec/checkout/case_'.$version->test_case_id.'_spec.ts',
            'branch' => 'main',
            'commit' => 'mirror'.$version->id,
            'created_at' => $now,
            'updated_at' => $now,
        ])->all();

        foreach (array_chunk($rows, 250) as $chunk) {
            DB::table('test_case_script_links')->insert($chunk);
        }

        TestCaseVersion::query()
            ->whereIn('id', $versions->modelKeys())
            ->update(['execution_type' => TestCaseExecutionType::Manual->value]);

        TestCaseVersion::query()
            ->whereIn('id', $automated->modelKeys())
            ->update(['execution_type' => TestCaseExecutionType::Automated->value]);
    }

    /**
     * 18 cases were added this week, the rest well before it.
     */
    private function ageCases(TestProject $project): void
    {
        $cases = TestCase::query()
            ->where('test_project_id', $project->id)
            ->orderByDesc('external_id')
            ->pluck('id');

        TestCase::query()
            ->whereIn('id', $cases)
            ->update(['created_at' => now()->subDays(45)]);

        TestCase::query()
            ->whereIn('id', $cases->take(self::NEW_THIS_WEEK))
            ->update(['created_at' => now()->subDays(2)]);
    }

    /**
     * The seven suites the mockup's tree shows, holding the case counts it
     * lists. They add up to 482, so no other suite is left with anything in it
     * and the rest are removed.
     *
     * They sit at the top level: in the mockup the project itself is the root of
     * the tree and every suite hangs directly off it.
     */
    private function reshapeSuites(TestProject $project): void
    {
        $sizes = [
            'Cart' => 88,
            'Payments' => 120,
            'Promotions' => 46,
            'Shipping & Tax' => 64,
            'Guest Flow' => 52,
            'Mobile' => 72,
            'Accessibility' => 40,
        ];

        $kept = [];
        $sort = 0;

        foreach ($sizes as $name => $size) {
            $suite = TestSuite::query()
                ->where('test_project_id', $project->id)
                ->where('name', $name)
                ->first();

            if ($suite === null) {
                continue;
            }

            $sort++;
            $suite->forceFill(['parent_id' => null, 'sort_order' => $sort])->save();
            $kept[$suite->id] = $size;
        }

        $cases = TestCase::query()
            ->where('test_project_id', $project->id)
            ->orderBy('external_id')
            ->pluck('id');

        $offset = 0;

        foreach ($kept as $suiteId => $size) {
            $slice = $cases->slice($offset, $size);
            $offset += $size;
            $order = 0;

            foreach ($slice as $caseId) {
                $order++;
                TestCase::query()->whereKey($caseId)->update([
                    'test_suite_id' => $suiteId,
                    'sort_order' => $order,
                ]);
            }
        }

        TestSuite::query()
            ->where('test_project_id', $project->id)
            ->whereNotIn('id', array_keys($kept))
            ->whereNull('parent_id')
            ->get()
            ->each(fn (TestSuite $suite) => $suite->delete());
    }

    /**
     * The Promotions suite as `test-cases.html` shows it: the seven named
     * cases at the top, five frozen of 46, and CO-TC-240 written out in full.
     *
     * @param  array<string, User>  $people
     */
    private function shapePromotions(TestProject $project, array $people): void
    {
        $suite = TestSuite::query()
            ->where('test_project_id', $project->id)
            ->where('name', 'Promotions')
            ->first();

        if ($suite === null) {
            return;
        }

        $featured = [
            240 => ['Apply valid percentage promo code', TestCaseImportance::High, 'smoke', 4, false],
            241 => ['Apply expired promo code shows error', TestCaseImportance::High, 'regression', 2, false],
            242 => ['Stacking two promo codes is rejected', TestCaseImportance::Medium, 'regression', 1, false],
            243 => ['Free-shipping threshold promo', TestCaseImportance::Medium, 'smoke', 3, true],
            244 => ['Promo code case-insensitivity', TestCaseImportance::Low, 'edge', 1, false],
            245 => ['Remove applied promo restores total', TestCaseImportance::Medium, 'regression', 2, false],
            246 => ['First-order-only promo for returning user', TestCaseImportance::High, 'regression', 1, false],
        ];

        /**
         * VolumeSeeder freezes every tenth case. Open every latest Promotions
         * version first so the five Frozen of 46 are only the ones this
         * method chooses (243 plus four unnamed).
         */
        $suite->load(['testCases.versions']);

        foreach ($suite->testCases as $case) {
            $case->versions->last()?->forceFill(['is_open' => true])->save();
        }

        $keywords = $this->promotionKeywords($project);
        $devon = $people['devon.designer@example.com'];
        $sort = 0;

        foreach ($featured as $number => [$name, $importance, $keyword, $version, $frozen]) {
            $case = TestCase::query()
                ->where('test_project_id', $project->id)
                ->where('external_id', $number)
                ->first();

            if ($case === null) {
                continue;
            }

            $sort++;
            $case->forceFill([
                'name' => $name,
                'test_suite_id' => $suite->id,
                'sort_order' => $sort,
            ])->save();

            $this->setCaseVersions($case, $version, $importance, $frozen, $devon);
            $case->keywords()->sync([$keywords[$keyword]->id]);
        }

        $rest = TestCase::query()
            ->where('test_suite_id', $suite->id)
            ->whereNotIn('external_id', array_keys($featured))
            ->orderBy('external_id')
            ->get();

        foreach ($rest as $index => $case) {
            $case->forceFill(['sort_order' => $sort + $index + 1])->save();
        }

        /**
         * Five frozen cases of 46. 243 is the one the table names; the other
         * four are the next cases in the suite so the Active/Frozen counts
         * match without inventing more named rows.
         */
        $rest->take(4)->each(function (TestCase $case) use ($devon): void {
            $latest = $case->versions->last();
            $latest?->forceFill([
                'is_open' => false,
                'updater_id' => $devon->id,
            ])->save();
        });

        $this->writePromoCase($project, $people);
    }

    /**
     * @return array<string, Keyword>
     */
    private function promotionKeywords(TestProject $project): array
    {
        $names = [
            'smoke' => 'Must run on every build.',
            'regression' => 'Full suite candidates.',
            'edge' => 'Boundary and unusual inputs.',
        ];

        $keywords = [];

        foreach ($names as $name => $notes) {
            $keywords[$name] = Keyword::query()
                ->forProject($project)
                ->where('name', $name)
                ->first() ?? Keyword::factory()->for($project)->named($name)->create([
                    'notes' => $notes,
                ]);
        }

        return $keywords;
    }

    private function setCaseVersions(
        TestCase $case,
        int $target,
        TestCaseImportance $importance,
        bool $frozen,
        User $author,
    ): void {
        $current = (int) $case->versions()->max('version');

        for ($number = $current + 1; $number <= $target; $number++) {
            $version = new TestCaseVersion;
            $version->forceFill([
                'test_case_id' => $case->id,
                'version' => $number,
                'status' => TestCaseStatus::Draft,
                'importance' => $importance,
                'execution_type' => TestCaseExecutionType::Manual,
                'is_open' => true,
                'author_id' => $author->id,
            ]);
            $version->save();
        }

        $versions = $case->versions()->get();

        foreach ($versions as $version) {
            $isLatest = $version->version === $target;

            $version->forceFill([
                'importance' => $importance,
                'is_open' => $isLatest && ! $frozen,
                'author_id' => $author->id,
            ])->save();
        }
    }

    /**
     * CO-TC-240 as the detail pane writes it: three steps, SAVE20, Devon, the
     * promo_percent_spec.ts script, a passed last run, updated two days ago.
     *
     * @param  array<string, User>  $people
     */
    private function writePromoCase(TestProject $project, array $people): void
    {
        $case = TestCase::query()
            ->where('test_project_id', $project->id)
            ->where('external_id', 240)
            ->first();

        if ($case === null) {
            return;
        }

        $devon = $people['devon.designer@example.com'];
        $case->unsetRelation('versions');
        $latest = $case->versions->last();

        if ($latest === null) {
            return;
        }

        TestCaseVersion::withoutTimestamps(function () use ($latest, $devon): void {
            $latest->forceFill([
                'preconditions' => '<p>User has an active cart with a $120 subtotal. Promo code <code>SAVE20</code> is active and not yet redeemed.</p>',
                'importance' => TestCaseImportance::High,
                'execution_type' => TestCaseExecutionType::Automated,
                'author_id' => $devon->id,
                'updater_id' => $devon->id,
                'updated_at' => now()->subDays(2),
            ])->save();
        });

        $latest->steps()->delete();

        $steps = [
            [
                'Open the cart and enter <code>SAVE20</code> in the promo field, click Apply.',
                'Code is accepted; a 20% discount line appears.',
            ],
            [
                'Review the order summary.',
                'Subtotal $120 → discount −$24 → total reflects $96 before tax.',
            ],
            [
                'Proceed to checkout.',
                'Discounted total carries through to the payment step.',
            ],
        ];

        foreach ($steps as $index => [$actions, $expected]) {
            TestCaseStep::factory()->for($latest, 'testCaseVersion')->at($index + 1)->create([
                'actions' => '<p>'.$actions.'</p>',
                'expected_results' => '<p>'.$expected.'</p>',
            ]);
        }

        DB::table('test_case_script_links')->whereIn('test_case_version_id', $case->versions->modelKeys())->delete();
        DB::table('test_case_script_links')->insert([
            'test_case_version_id' => $latest->id,
            'project_key' => 'acme/checkout',
            'repository' => 'checkout-e2e',
            'path' => 'checkout/promotions/promo_percent_spec.ts',
            'branch' => 'main',
            'commit' => 'promo240',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $versionIds = $case->versions()->pluck('id');
        $execution = Execution::query()
            ->whereIn('test_case_version_id', $versionIds)
            ->where('is_draft', false)
            ->orderByDesc('id')
            ->first();

        if ($execution instanceof Execution && $execution->status !== ExecutionStatus::Passed) {
            $swap = Execution::query()
                ->where('build_id', $execution->build_id)
                ->where('is_draft', false)
                ->where('status', ExecutionStatus::Passed)
                ->whereKeyNot($execution->id)
                ->orderByDesc('id')
                ->first();

            $swap?->forceFill(['status' => $execution->status])->save();
            $execution->forceFill(['status' => ExecutionStatus::Passed])->save();
        }
    }

    /**
     * The mockup's nine plans: four active, two draft, three archived.
     *
     * The three volume-seeded runs are replaced rather than edited, because
     * their items and results are rebuilt from scratch either way.
     *
     * @return array{regression: TestPlan, payments: TestPlan, mobile: TestPlan, accessibility: TestPlan}
     */
    private function rebuildPlans(TestProject $project): array
    {
        TestPlan::query()
            ->where('test_project_id', $project->id)
            ->whereIn('name', [
                'Checkout · Regression',
                'Payments · Smoke',
                'Mobile Checkout · Sanity',
                'Accessibility Audit',
                'Tax Engine v2',
            ])
            ->get()
            ->each(fn (TestPlan $plan) => $plan->delete());

        /** Release 2.4 keeps its history but leaves the active list, which the mockup caps at four. */
        TestPlan::query()
            ->where('test_project_id', $project->id)
            ->where('name', 'Release 2.4')
            ->update([
                'status' => TestPlanStatus::Archived->value,
                'is_active' => false,
                'is_open' => false,
            ]);

        $definitions = [
            'regression' => ['Checkout · Regression', 12],
            'payments' => ['Payments · Smoke', 14],
            'mobile' => ['Mobile Checkout · Sanity', 15],
            'accessibility' => ['Accessibility Audit', 16],
        ];

        $plans = [];

        foreach ($definitions as $key => [$name, $number]) {
            $plan = new TestPlan;
            $plan->forceFill([
                'test_project_id' => $project->id,
                'external_id' => $number,
                'name' => $name,
                'description' => '<p>'.$name.' for the 4.8 release.</p>',
                'is_active' => true,
                'is_open' => true,
                'is_public' => true,
                'status' => TestPlanStatus::Active,
            ]);
            $plan->save();
            $plan->setRelation('testProject', $project);

            $plans[$key] = $plan;
        }

        $project->forceFill([
            'test_plan_counter' => max(
                (int) $project->test_plan_counter,
                collect($definitions)->max(fn (array $definition): int => $definition[1]),
            ),
        ])->save();

        return $plans;
    }

    /**
     * Items, builds and results for the four active plans.
     *
     * Build order matters: Checkout · Regression's 4.8 is created last so that
     * it is the project's newest build, which is the one the Overview donut and
     * pass rate report on.
     *
     * @param  array{regression: TestPlan, payments: TestPlan, mobile: TestPlan, accessibility: TestPlan}  $plans
     * @param  array<string, User>  $people
     */
    private function seedRuns(TestProject $project, array $plans, array $people): void
    {
        $versions = $this->versionsByExternalId($project);
        $platform = Platform::query()->where('test_project_id', $project->id)->orderBy('id')->first();

        $maya = $people['maya.leader@example.com'];
        $sam = $people['sam.senior@example.com'];
        $rina = $people['rina.tester@example.com'];
        $devon = $people['devon.designer@example.com'];
        $alex = $people['alex.builder@example.com'];

        /** 298 cases: the mockup's donut counts 211 + 28 + 14 + 45. */
        $regressionNumbers = [...range(1, 297), 305];
        $regressionItems = $this->addItems($plans['regression'], $versions, $regressionNumbers, $platform, $maya);
        $paymentsItems = $this->addItems($plans['payments'], $versions, range(1, 100), $platform, $maya);
        $mobileItems = $this->addItems($plans['mobile'], $versions, range(101, 200), $platform, $maya);
        $accessibilityItems = $this->addItems($plans['accessibility'], $versions, range(201, 240), $platform, $maya);

        $regressionPrevious = $this->addBuild($plans['regression'], '4.7', $maya);
        $paymentsBuild = $this->addBuild($plans['payments'], '4.8', $maya);
        $mobileBuild = $this->addBuild($plans['mobile'], '4.8-rc', $alex);
        $accessibilityBuild = $this->addBuild($plans['accessibility'], '4.7', $maya);
        $regressionBuild = $this->addBuild($plans['regression'], '4.8', $maya);

        /** 87% on 4.7 against 83% on 4.8 is the tile's "4% vs Build 4.7". */
        $this->record($regressionPrevious, $regressionItems->values(), 220, 20, 13, [$sam, $rina, $devon]);
        $this->record($paymentsBuild, $paymentsItems->values(), 40, 5, 0, [$alex, $sam]);
        $this->record($mobileBuild, $mobileItems->values(), 22, 0, 18, [$rina]);
        $this->record($accessibilityBuild, $accessibilityItems->values(), 36, 3, 1, [$devon]);

        $attentionNumbers = array_column(self::ATTENTION, 'external_id');
        $generic = $regressionItems
            ->reject(fn (TestPlanItem $item, int $number): bool => in_array($number, $attentionNumbers, true))
            ->values();

        $this->record($regressionBuild, $generic, 211, 25, 13, [$sam, $rina, $devon]);
        $this->recordAttention($regressionBuild, $regressionItems, $people);

        $this->assign($regressionBuild, $regressionItems->values(), [$sam, $rina, $devon], $maya);
        $this->assign($paymentsBuild, $paymentsItems->values(), [$alex, $sam], $maya);
        $this->assign($mobileBuild, $mobileItems->values(), [$rina], $maya);
    }

    /**
     * Version ids for the project's cases, keyed by case number.
     *
     * @return Collection<int, int>
     */
    private function versionsByExternalId(TestProject $project): Collection
    {
        return TestCaseVersion::query()
            ->join('test_cases', 'test_cases.id', '=', 'test_case_versions.test_case_id')
            ->where('test_cases.test_project_id', $project->id)
            ->orderBy('test_case_versions.version')
            ->pluck('test_case_versions.id', 'test_cases.external_id');
    }

    /**
     * @param  Collection<int, int>  $versions
     * @param  list<int>  $numbers
     * @return Collection<int, TestPlanItem> keyed by case number
     */
    private function addItems(
        TestPlan $plan,
        Collection $versions,
        array $numbers,
        ?Platform $platform,
        User $author,
    ): Collection {
        if ($platform !== null) {
            $plan->platforms()->syncWithoutDetaching([$platform->id]);
        }

        $now = now();
        $rows = [];
        $used = [];

        foreach ($numbers as $sort => $number) {
            $versionId = $versions->get($number);

            if ($versionId === null) {
                continue;
            }

            $used[] = $number;
            $rows[] = [
                'test_plan_id' => $plan->id,
                'test_case_version_id' => $versionId,
                'platform_id' => $platform?->id,
                'sort_order' => $sort,
                'urgency' => TestCaseUrgency::Medium->value,
                'author_id' => $author->id,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 250) as $chunk) {
            TestPlanItem::query()->insert($chunk);
        }

        return $plan->items()->orderBy('id')->get()->values()->mapWithKeys(
            fn (TestPlanItem $item, int $offset): array => [$used[$offset] => $item],
        );
    }

    private function addBuild(TestPlan $plan, string $name, User $author): Build
    {
        $build = new Build;
        $build->forceFill([
            'test_plan_id' => $plan->id,
            'name' => $name,
            'notes' => '<p>Build '.$name.'.</p>',
            'is_active' => true,
            'is_open' => true,
            'author_id' => $author->id,
            'release_date' => now()->subDays(3)->toDateString(),
        ]);
        $build->save();

        return $build;
    }

    /**
     * Execute the first cases of a run: passed, then failed, then blocked. What
     * is left over is what the screens count as untested.
     *
     * @param  Collection<int, TestPlanItem>  $items
     * @param  list<User>  $testers
     */
    private function record(
        Build $build,
        Collection $items,
        int $passed,
        int $failed,
        int $blocked,
        array $testers,
    ): void {
        $statuses = [
            ...array_fill(0, $passed, ExecutionStatus::Passed),
            ...array_fill(0, $failed, ExecutionStatus::Failed),
            ...array_fill(0, $blocked, ExecutionStatus::Blocked),
        ];

        $now = now();
        $rows = [];

        foreach ($statuses as $offset => $status) {
            $item = $items->get($offset);

            if (! $item instanceof TestPlanItem) {
                break;
            }

            $rows[] = $this->executionRow(
                $build,
                $item,
                $status,
                $testers[$offset % count($testers)],
                null,
                $now->copy()->subMinutes(($offset + 5) * 3),
            );
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('executions')->insert($chunk);
        }
    }

    /**
     * The four rows the Needs attention card names, executed last so they are
     * the run's most recent results.
     *
     * @param  Collection<int, TestPlanItem>  $items  keyed by case number
     * @param  array<string, User>  $people
     */
    private function recordAttention(Build $build, Collection $items, array $people): void
    {
        foreach (self::ATTENTION as $offset => $row) {
            $item = $items->get($row['external_id']);

            if (! $item instanceof TestPlanItem) {
                continue;
            }

            $executionId = DB::table('executions')->insertGetId($this->executionRow(
                $build,
                $item,
                $row['status'],
                $people[$row['tester']],
                $row['notes'],
                now()->subMinutes(90 - ($offset * 20)),
            ));

            if ($row['issue'] === null) {
                continue;
            }

            DB::table('execution_issues')->insert([
                'execution_id' => $executionId,
                'issue_id' => $row['issue'],
                'issue_url' => 'https://acme.atlassian.net/browse/'.$row['issue'],
                'issue_status' => 'Open',
                'issue_summary' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function executionRow(
        Build $build,
        TestPlanItem $item,
        ExecutionStatus $status,
        User $tester,
        ?string $notes,
        \DateTimeInterface $at,
    ): array {
        return [
            'test_plan_id' => $build->test_plan_id,
            'build_id' => $build->id,
            'test_plan_item_id' => $item->id,
            'test_case_version_id' => $item->test_case_version_id,
            'version' => 1,
            'tester_id' => $tester->id,
            'status' => $status->value,
            'notes' => $notes,
            'duration' => 4.5,
            'is_draft' => false,
            'executed_at' => $at,
            'created_at' => $at,
            'updated_at' => $at,
        ];
    }

    /**
     * Who the Overview shows against a run. The order of the avatars follows
     * the order the assignments are written in.
     *
     * @param  Collection<int, TestPlanItem>  $items
     * @param  list<User>  $testers
     */
    private function assign(Build $build, Collection $items, array $testers, User $assigner): void
    {
        $rows = [];

        foreach ($testers as $offset => $tester) {
            $item = $items->get($offset);

            if (! $item instanceof TestPlanItem) {
                continue;
            }

            $rows[] = [
                'test_plan_item_id' => $item->id,
                'build_id' => $build->id,
                'user_id' => $tester->id,
                'assigner_id' => $assigner->id,
                'status' => TesterAssignmentStatus::Todo->value,
                'deadline_at' => now()->addDays(3),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        DB::table('tester_assignments')->insert($rows);
    }

    /**
     * The sidebar counts 42 issues, so top the project up to that many.
     */
    private function topUpIssues(TestProject $project): void
    {
        $existing = DB::table('execution_issues')
            ->join('executions', 'executions.id', '=', 'execution_issues.execution_id')
            ->join('test_plans', 'test_plans.id', '=', 'executions.test_plan_id')
            ->where('test_plans.test_project_id', $project->id)
            ->count();

        $needed = self::ISSUES - $existing;

        if ($needed <= 0) {
            return;
        }

        $summaries = [
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
            ->limit($needed)
            ->get();

        $rows = [];

        foreach ($runs as $offset => $execution) {
            [$summary, $status] = $summaries[$offset % count($summaries)];
            $key = 'JIRA-'.(4760 + $offset);

            $rows[] = [
                'execution_id' => $execution->id,
                'issue_id' => $key,
                'issue_url' => 'https://acme.atlassian.net/browse/'.$key,
                'issue_status' => $status,
                'issue_summary' => $summary,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('execution_issues')->insert($chunk);
        }
    }

    /**
     * The five lines of Recent activity, oldest written first so the newest
     * carries the highest id.
     *
     * @param  array<string, User>  $people
     */
    private function seedActivity(TestProject $project, TestPlan $regression, array $people): void
    {
        $this->clearSeededActivity($project);

        $maya = $people['maya.leader@example.com'];
        $sam = $people['sam.senior@example.com'];
        $rina = $people['rina.tester@example.com'];
        $devon = $people['devon.designer@example.com'];
        $alex = $people['alex.builder@example.com'];

        $mobileBuild = Build::query()
            ->whereHas('testPlan', fn ($query) => $query->where('name', 'Mobile Checkout · Sanity'))
            ->orderByDesc('id')
            ->first();

        if ($mobileBuild instanceof Build) {
            $this->event($alex, $mobileBuild, AuditAction::BuildCreated, [
                'name' => ['from' => null, 'to' => $mobileBuild->name],
            ], now()->subHours(3));
        }

        $frozen = TestCase::query()
            ->where('test_project_id', $project->id)
            ->where('external_id', 100)
            ->first();

        if ($frozen instanceof TestCase) {
            $this->event($maya, $frozen, AuditAction::TestCaseVersionFrozen, ['version' => 3], now()->subHours(2));
        }

        $promotions = TestSuite::query()
            ->where('test_project_id', $project->id)
            ->where('name', 'Promotions')
            ->first();

        if ($promotions instanceof TestSuite) {
            $added = TestCase::query()
                ->where('test_suite_id', $promotions->id)
                ->orderByDesc('external_id')
                ->limit(6)
                ->get();

            foreach ($added as $case) {
                $this->event($devon, $case, AuditAction::TestCaseCreated, [
                    'name' => $case->name,
                    'external_id' => $case->external_id,
                    'test_suite_id' => $promotions->id,
                ], now()->subHour());
            }
        }

        $this->executionEvent($regression, $rina, 241, now()->subMinutes(28));
        $this->executionEvent($regression, $sam, 190, now()->subMinutes(12));
    }

    /**
     * Drop what an earlier run of this seeder wrote, so re-running does not
     * stack five more lines on top of the five the mockup shows.
     *
     * Bounded to the window this seeder writes into, which on a demo database
     * holds nothing else.
     */
    private function clearSeededActivity(TestProject $project): void
    {
        DB::table('audit_events')
            ->where('created_at', '>=', now()->subHours(6))
            ->where(function ($query) use ($project): void {
                $query
                    ->where(function ($inner) use ($project): void {
                        $inner->where('subject_type', (new TestCase)->getMorphClass())
                            ->whereIn('subject_id', $project->testCases()->select('id'));
                    })
                    ->orWhere(function ($inner) use ($project): void {
                        $inner->where('subject_type', (new TestPlan)->getMorphClass())
                            ->whereIn('subject_id', $project->testPlans()->select('id'));
                    })
                    ->orWhere(function ($inner) use ($project): void {
                        $inner->where('subject_type', (new Build)->getMorphClass())
                            ->whereIn(
                                'subject_id',
                                Build::query()->select('id')->whereIn(
                                    'test_plan_id',
                                    $project->testPlans()->select('id'),
                                ),
                            );
                    });
            })
            ->delete();
    }

    /**
     * A recorded execution, resolved back to the run that produced it so the
     * feed can name the case and any issue linked to it.
     */
    private function executionEvent(TestPlan $plan, User $actor, int $externalId, \DateTimeInterface $at): void
    {
        $execution = Execution::query()
            ->where('test_plan_id', $plan->id)
            ->whereHas(
                'testCaseVersion.testCase',
                fn ($query) => $query->where('external_id', $externalId),
            )
            ->orderByDesc('id')
            ->first();

        if (! $execution instanceof Execution) {
            return;
        }

        $this->event($actor, $plan, AuditAction::ExecutionCompleted, [
            'execution_id' => $execution->id,
            'item_id' => $execution->test_plan_item_id,
            'build_id' => $execution->build_id,
            'status' => $execution->status->value,
            'version' => $execution->version,
            'is_draft' => false,
        ], $at);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function event(User $actor, object $subject, AuditAction $action, array $properties, \DateTimeInterface $at): void
    {
        DB::table('audit_events')->insert([
            'user_id' => $actor->id,
            'action' => $action->value,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
            'properties' => json_encode($properties),
            'ip_address' => '127.0.0.1',
            'created_at' => $at,
        ]);
    }
}
