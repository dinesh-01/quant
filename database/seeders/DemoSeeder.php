<?php

namespace Database\Seeders;

use App\Enums\AuditAction;
use App\Enums\CodeTrackerType;
use App\Enums\CustomFieldEntity;
use App\Enums\CustomFieldType;
use App\Enums\ExecutionStatus;
use App\Enums\RequirementStatus;
use App\Enums\RequirementType;
use App\Enums\TestCaseExecutionType;
use App\Enums\TestCaseImportance;
use App\Enums\TestCaseRelationType;
use App\Enums\TestCaseStatus;
use App\Enums\TestCaseUrgency;
use App\Enums\TesterAssignmentStatus;
use App\Models\Attachment;
use App\Models\AuditEvent;
use App\Models\Build;
use App\Models\CodeTracker;
use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Execution;
use App\Models\ExecutionIssue;
use App\Models\ExecutionStep;
use App\Models\IssueTracker;
use App\Models\Keyword;
use App\Models\Milestone;
use App\Models\Platform;
use App\Models\ReportBaseline;
use App\Models\Requirement;
use App\Models\RequirementCoverage;
use App\Models\RequirementSpec;
use App\Models\RequirementVersion;
use App\Models\Role;
use App\Models\TestCase;
use App\Models\TestCaseRelation;
use App\Models\TestCaseScriptLink;
use App\Models\TestCaseStep;
use App\Models\TestCaseVersion;
use App\Models\TesterAssignment;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\TestProject;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Prefix of the public demo project. Re-running is a no-op while this row exists.
     */
    public const CHECKOUT_PREFIX = 'CO';

    /**
     * Seed a walkable dataset for every shipped area. All demo accounts use password.
     */
    public function run(): void
    {
        if (TestProject::query()->where('prefix', self::CHECKOUT_PREFIX)->exists()) {
            $this->command?->warn('Demo data already present (prefix CO). Skipping.');

            return;
        }

        $roles = $this->rolesByName();
        $users = $this->seedUsers($roles);
        $fields = $this->seedCustomFields();
        $checkout = $this->seedCheckoutProject($users, $roles, $fields);
        $this->seedInternalToolsProject($users, $roles, $fields);
        $this->seedAuditTrail($users['admin'], $checkout);

        $this->command?->info('Demo accounts (password: password)');
        $this->command?->info('  test@example.com          Admin');
        $this->command?->info('  maya.leader@example.com   Leader');
        $this->command?->info('  sam.senior@example.com    Senior Tester');
        $this->command?->info('  rina.tester@example.com   Tester (assigned-only)');
        $this->command?->info('  devon.designer@example.com Test Designer');
        $this->command?->info('  guest.viewer@example.com  Guest');
        $this->command?->info('  inactive.user@example.com Tester, deactivated');
    }

    /**
     * @return array<string, Role>
     */
    private function rolesByName(): array
    {
        return Role::query()
            ->whereIn('name', ['Admin', 'Leader', 'Senior Tester', 'Tester', 'Test Designer', 'Guest'])
            ->get()
            ->keyBy('name')
            ->all();
    }

    /**
     * @param  array<string, Role>  $roles
     * @return array<string, User>
     */
    private function seedUsers(array $roles): array
    {
        $users = [
            'admin' => $this->user('Test User', 'test@example.com', $roles['Admin']),
            'leader' => $this->user('Maya Chen', 'maya.leader@example.com', $roles['Leader']),
            'senior' => $this->user('Sam Okonkwo', 'sam.senior@example.com', $roles['Senior Tester']),
            'tester' => $this->user('Rina Patel', 'rina.tester@example.com', $roles['Tester']),
            'designer' => $this->user('Devon Walsh', 'devon.designer@example.com', $roles['Test Designer']),
            'guest' => $this->user('Guest Viewer', 'guest.viewer@example.com', $roles['Guest']),
        ];

        $this->user('Inactive Tester', 'inactive.user@example.com', $roles['Tester'], active: false);

        return $users;
    }

    private function user(string $name, string $email, Role $role, bool $active = true): User
    {
        return User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => 'password',
                'email_verified_at' => now(),
                'role_id' => $role->id,
                'is_active' => $active,
            ],
        );
    }

    /**
     * @return array{risk: CustomField, environment: CustomField, sprint: CustomField}
     */
    private function seedCustomFields(): array
    {
        return [
            'risk' => CustomField::factory()
                ->named('risk_level')
                ->ofType(CustomFieldType::Dropdown, ['Low', 'Medium', 'High'])
                ->forEntity(CustomFieldEntity::TestCase)
                ->create(),
            'environment' => CustomField::factory()
                ->named('environment')
                ->ofType(CustomFieldType::String)
                ->forEntity(CustomFieldEntity::Execution)
                ->create(),
            'sprint' => CustomField::factory()
                ->named('sprint')
                ->ofType(CustomFieldType::String)
                ->forEntity(CustomFieldEntity::TestPlan)
                ->create(),
        ];
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, Role>  $roles
     * @param  array{risk: CustomField, environment: CustomField, sprint: CustomField}  $fields
     */
    private function seedCheckoutProject(array $users, array $roles, array $fields): TestProject
    {
        $project = TestProject::factory()->create([
            'name' => 'Checkout',
            'prefix' => self::CHECKOUT_PREFIX,
            'description' => 'Public storefront checkout. The main demo project.',
            'is_active' => true,
            'is_public' => true,
        ]);

        foreach (['leader', 'senior', 'tester', 'designer'] as $key) {
            $users[$key]->projectRoles()->attach($roles[match ($key) {
                'leader' => 'Leader',
                'senior' => 'Senior Tester',
                'tester' => 'Tester',
                'designer' => 'Test Designer',
            }], ['test_project_id' => $project->id]);
        }

        $fields['risk']->testProjects()->attach($project, [
            'is_active' => true,
            'sort_order' => 0,
            'required_on_design' => false,
            'required_on_execution' => false,
        ]);
        $fields['environment']->testProjects()->attach($project, [
            'is_active' => true,
            'sort_order' => 1,
            'required_on_design' => false,
            'required_on_execution' => false,
        ]);
        $fields['sprint']->testProjects()->attach($project, [
            'is_active' => true,
            'sort_order' => 0,
            'required_on_design' => false,
            'required_on_execution' => false,
        ]);

        $keywords = $this->seedKeywords($project);
        $platforms = $this->seedPlatforms($project);
        $suites = $this->seedCheckoutSuites($project, $users['designer']);
        $cases = $this->seedCheckoutCases($suites, $users, $keywords, $platforms, $fields['risk']);
        $this->seedCheckoutRequirements($project, $users, $cases);
        $this->seedCheckoutTrackers($project);
        $this->seedCheckoutPlans($project, $users, $roles, $platforms, $cases, $fields);

        return $project;
    }

    /**
     * @return array<string, Keyword>
     */
    private function seedKeywords(TestProject $project): array
    {
        $names = [
            'smoke' => 'Must run on every build.',
            'regression' => 'Full suite candidates.',
            'security' => 'Auth and session cases.',
            'payments' => 'Card and wallet paths.',
        ];

        $keywords = [];

        foreach ($names as $name => $notes) {
            $keywords[$name] = Keyword::factory()->for($project)->named($name)->create([
                'notes' => $notes,
            ]);
        }

        return $keywords;
    }

    /**
     * @return array<string, Platform>
     */
    private function seedPlatforms(TestProject $project): array
    {
        return [
            'chrome' => Platform::factory()->for($project)->named('Chrome')->create(),
            'firefox' => Platform::factory()->for($project)->named('Firefox')->create(),
            'ios' => Platform::factory()->for($project)->named('iOS')->executionOnly()->create(),
        ];
    }

    /**
     * @return array<string, TestSuite>
     */
    private function seedCheckoutSuites(TestProject $project, User $designer): array
    {
        $root = TestSuite::factory()->for($project)->create([
            'name' => 'Storefront',
            'description' => '<p>Customer-facing checkout flows.</p>',
            'sort_order' => 0,
        ]);

        Attachment::factory()
            ->state([
                'file_name' => 'suite-notes.txt',
                'mime_type' => 'text/plain',
            ])
            ->attachedTo($root)
            ->for($designer, 'uploader')
            ->titled('Suite notes')
            ->withContent("Checkout suite notes\nDo not charge live cards.")
            ->create();

        $auth = TestSuite::factory()->childOf($root)->create([
            'name' => 'Authentication',
            'description' => '<p>Sign-in, lockout, and session cases.</p>',
            'sort_order' => 0,
        ]);

        $payments = TestSuite::factory()->childOf($root)->create([
            'name' => 'Payments',
            'description' => '<p>Card and wallet tender.</p>',
            'sort_order' => 1,
        ]);

        return [
            'root' => $root,
            'auth' => $auth,
            'payments' => $payments,
        ];
    }

    /**
     * @param  array<string, TestSuite>  $suites
     * @param  array<string, User>  $users
     * @param  array<string, Keyword>  $keywords
     * @param  array<string, Platform>  $platforms
     * @return array<string, array{case: TestCase, version: TestCaseVersion}>
     */
    private function seedCheckoutCases(
        array $suites,
        array $users,
        array $keywords,
        array $platforms,
        CustomField $risk,
    ): array {
        $unknownEmail = $this->addCase(
            suite: $suites['auth'],
            author: $users['designer'],
            name: 'Rejects an unknown email',
            summary: '<p>An email that is not registered stays on the sign-in form.</p>',
            preconditions: '<p>[ghost]Use the shared checkout fixture.[/ghost]</p>',
            importance: TestCaseImportance::High,
            status: TestCaseStatus::Final,
            steps: [
                ['Open /login.', 'The sign-in form is shown.'],
                ['Submit an address that has no account.', 'An unknown-email message is shown. No session is created.'],
            ],
            keywordNames: ['smoke', 'security'],
            risk: $risk,
            riskValue: 'High',
            platforms: [$platforms['chrome'], $platforms['firefox']],
        );

        $lockout = $this->addCase(
            suite: $suites['auth'],
            author: $users['designer'],
            name: 'Locks the account after three failures',
            summary: '<p>Three wrong passwords lock the account until an administrator unlocks it.</p>',
            preconditions: '<p>A known account exists.</p>',
            importance: TestCaseImportance::High,
            status: TestCaseStatus::Final,
            steps: [
                ['Submit the wrong password three times.', 'The account is locked.'],
                ['Submit the correct password.', 'Sign-in is still refused.'],
            ],
            keywordNames: ['security'],
            risk: $risk,
            riskValue: 'High',
            platforms: [$platforms['chrome']],
        );

        $validCard = $this->addCase(
            suite: $suites['payments'],
            author: $users['designer'],
            name: 'Charges a valid card',
            summary: '<p>A valid card completes the order and writes a payment id.</p>',
            preconditions: '<p>The basket has one in-stock item.</p>',
            importance: TestCaseImportance::High,
            status: TestCaseStatus::Final,
            steps: [
                ['Enter a valid test card and submit.', 'The receipt page shows a payment id.'],
            ],
            keywordNames: ['smoke', 'payments'],
            risk: $risk,
            riskValue: 'High',
            platforms: [$platforms['chrome'], $platforms['firefox']],
        );

        $expiredCard = $this->addCase(
            suite: $suites['payments'],
            author: $users['designer'],
            name: 'Declines an expired card',
            summary: '<p>An expired card is refused and the basket is kept.</p>',
            preconditions: '<p>The basket has one in-stock item.</p>',
            importance: TestCaseImportance::Medium,
            status: TestCaseStatus::ReadyForReview,
            steps: [
                ['Enter an expired test card and submit.', 'A decline message is shown. The basket is unchanged.'],
            ],
            keywordNames: ['payments', 'regression'],
            risk: $risk,
            riskValue: 'Medium',
            platforms: [$platforms['chrome']],
        );

        $wallet = $this->addCase(
            suite: $suites['payments'],
            author: $users['designer'],
            name: 'Applies a wallet credit',
            summary: '<p>Wallet balance covers the basket without a card form.</p>',
            preconditions: '<p>The shopper has a wallet balance greater than the basket.</p>',
            importance: TestCaseImportance::Medium,
            status: TestCaseStatus::Final,
            steps: [
                ['Choose wallet and confirm.', 'The order is paid from wallet. No card fields are shown.'],
            ],
            keywordNames: ['payments'],
            risk: $risk,
            riskValue: 'Low',
            platforms: [$platforms['chrome']],
            executionType: TestCaseExecutionType::Automated,
        );

        TestCaseScriptLink::factory()->for($wallet['version'], 'testCaseVersion')->create([
            'project_key' => 'acme/checkout',
            'repository' => 'qa-scripts',
            'path' => 'spec/checkout/wallet_spec.rb',
            'branch' => 'main',
            'commit' => 'a1b2c3d4e5f6',
        ]);

        TestCaseRelation::factory()->create([
            'source_id' => $expiredCard['case']->id,
            'destination_id' => $validCard['case']->id,
            'type' => TestCaseRelationType::DependsOn,
            'author_id' => $users['designer']->id,
        ]);

        TestCaseRelation::factory()->create([
            'source_id' => $lockout['case']->id,
            'destination_id' => $unknownEmail['case']->id,
            'type' => TestCaseRelationType::Related,
            'author_id' => $users['designer']->id,
        ]);

        $frozen = TestCaseVersion::factory()
            ->for($unknownEmail['case'], 'testCase')
            ->version(2)
            ->create([
                'status' => TestCaseStatus::Final,
                'summary' => '<p>An email that is not registered stays on the sign-in form. Copy updated for the new error string.</p>',
                'preconditions' => '<p>[ghost]Use the shared checkout fixture.[/ghost]</p>',
                'importance' => TestCaseImportance::High,
                'execution_type' => TestCaseExecutionType::Manual,
                'is_open' => true,
                'author_id' => $users['designer']->id,
            ]);

        $unknownEmail['version']->forceFill(['is_open' => false])->save();

        foreach ($unknownEmail['version']->steps as $step) {
            TestCaseStep::factory()->for($frozen, 'testCaseVersion')->at($step->sort_order)->create([
                'actions' => $step->actions,
                'expected_results' => $step->expected_results,
                'execution_type' => TestCaseExecutionType::Manual,
            ]);
        }

        $frozen->platforms()->attach($unknownEmail['version']->platforms->modelKeys());
        $unknownEmail['version'] = $frozen->fresh(['steps', 'platforms']);

        return [
            'unknownEmail' => $unknownEmail,
            'lockout' => $lockout,
            'validCard' => $validCard,
            'expiredCard' => $expiredCard,
            'wallet' => $wallet,
        ];
    }

    /**
     * @param  list<array{0: string, 1: string}>  $steps
     * @param  list<string>  $keywordNames
     * @param  list<Platform>  $platforms
     * @return array{case: TestCase, version: TestCaseVersion, keyword_ids: list<int>}
     */
    private function addCase(
        TestSuite $suite,
        User $author,
        string $name,
        string $summary,
        string $preconditions,
        TestCaseImportance $importance,
        TestCaseStatus $status,
        array $steps,
        array $keywordNames,
        CustomField $risk,
        string $riskValue,
        array $platforms,
        TestCaseExecutionType $executionType = TestCaseExecutionType::Manual,
    ): array {
        $case = TestCase::factory()->for($suite, 'testSuite')->create([
            'name' => $name,
            'sort_order' => $suite->testCases()->count(),
        ]);

        $version = TestCaseVersion::factory()->for($case, 'testCase')->create([
            'status' => $status,
            'summary' => $summary,
            'preconditions' => $preconditions,
            'importance' => $importance,
            'execution_type' => $executionType,
            'estimated_duration' => 8,
            'is_open' => true,
            'author_id' => $author->id,
        ]);

        foreach ($steps as $index => [$actions, $expected]) {
            TestCaseStep::factory()->for($version, 'testCaseVersion')->at($index + 1)->create([
                'actions' => '<p>'.$actions.'</p>',
                'expected_results' => '<p>'.$expected.'</p>',
                'execution_type' => $executionType,
            ]);
        }

        CustomFieldValue::factory()->about($version)->answering($risk, $riskValue)->create();
        $version->platforms()->attach(collect($platforms)->map->id->all());

        $keywordIds = Keyword::query()
            ->where('test_project_id', $suite->test_project_id)
            ->whereIn('name', $keywordNames)
            ->pluck('id')
            ->all();

        $case->keywords()->attach($keywordIds);

        return [
            'case' => $case->fresh(),
            'version' => $version->fresh(['steps', 'platforms']),
            'keyword_ids' => $keywordIds,
        ];
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, array{case: TestCase, version: TestCaseVersion}>  $cases
     */
    private function seedCheckoutRequirements(TestProject $project, array $users, array $cases): void
    {
        $authSpec = RequirementSpec::factory()->for($project)->create([
            'name' => 'Authentication',
            'doc_id' => 'SPEC-AUTH',
            'description' => '<p>Who may reach an account.</p>',
            'sort_order' => 0,
        ]);

        $paySpec = RequirementSpec::factory()->for($project)->create([
            'name' => 'Payments',
            'doc_id' => 'SPEC-PAY',
            'description' => '<p>How an order is tendered.</p>',
            'sort_order' => 1,
        ]);

        $login = $this->addRequirement(
            $authSpec,
            $users['designer'],
            'Unknown emails are refused',
            'REQ-LOGIN-001',
            '<p>Submitting an unregistered email must not create a session.</p>',
            RequirementStatus::Valid,
        );

        $charge = $this->addRequirement(
            $paySpec,
            $users['designer'],
            'Valid cards are charged once',
            'REQ-PAY-001',
            '<p>A successful card payment writes exactly one capture.</p>',
            RequirementStatus::Implemented,
        );

        $this->addRequirement(
            $paySpec,
            $users['designer'],
            'Wallet can settle the basket alone',
            'REQ-PAY-002',
            '<p>A sufficient wallet balance completes checkout without a card.</p>',
            RequirementStatus::Review,
        );

        RequirementCoverage::factory()->create([
            'requirement_version_id' => $login->id,
            'test_case_version_id' => $cases['unknownEmail']['version']->id,
            'author_id' => $users['designer']->id,
        ]);

        RequirementCoverage::factory()->create([
            'requirement_version_id' => $charge->id,
            'test_case_version_id' => $cases['validCard']['version']->id,
            'author_id' => $users['designer']->id,
        ]);

        $login->requirement->monitors()->create([
            'user_id' => $users['leader']->id,
        ]);
    }

    private function addRequirement(
        RequirementSpec $spec,
        User $author,
        string $name,
        string $docId,
        string $scope,
        RequirementStatus $status,
    ): RequirementVersion {
        $requirement = Requirement::factory()->for($spec, 'requirementSpec')->create([
            'name' => $name,
            'doc_id' => $docId,
            'sort_order' => $spec->requirements()->count(),
        ]);

        return RequirementVersion::factory()->for($requirement)->create([
            'scope' => $scope,
            'status' => $status,
            'type' => RequirementType::Feature,
            'expected_coverage' => 1,
            'is_open' => $status !== RequirementStatus::Valid,
            'author_id' => $author->id,
        ]);
    }

    private function seedCheckoutTrackers(TestProject $project): void
    {
        CodeTracker::factory()->for($project)->create([
            'name' => 'Checkout scripts',
            'type' => CodeTrackerType::Github,
            'base_url' => 'https://api.github.com',
            'project_key' => 'acme/checkout',
            'view_url_template' => 'https://github.com/{repository}/blob/{branch}/{path}',
            'is_enabled' => true,
        ]);

        IssueTracker::factory()->for($project)->create([
            'name' => 'Checkout Jira',
            'base_url' => 'https://acme.atlassian.net',
            'settings' => [
                'project_key' => 'CO',
                'issue_type' => 'Bug',
                'email' => 'qa@example.com',
                'api_token' => 'demo-not-a-real-token',
                'proxy' => null,
            ],
            'is_enabled' => true,
        ]);
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, Role>  $roles
     * @param  array<string, Platform>  $platforms
     * @param  array<string, array{case: TestCase, version: TestCaseVersion}>  $cases
     * @param  array{risk: CustomField, environment: CustomField, sprint: CustomField}  $fields
     */
    private function seedCheckoutPlans(
        TestProject $project,
        array $users,
        array $roles,
        array $platforms,
        array $cases,
        array $fields,
    ): void {
        $release = TestPlan::factory()->for($project)->create([
            'name' => 'Release 2.4',
            'description' => '<p>Current release train.</p>',
            'is_active' => true,
            'is_open' => true,
            'is_public' => true,
        ]);

        $regression = TestPlan::factory()->for($project)->create([
            'name' => 'Regression',
            'description' => '<p>Closed historical plan. No new runs.</p>',
            'is_active' => true,
            'is_open' => false,
            'is_public' => true,
        ]);

        $users['tester']->planRoles()->attach($roles['Tester'], ['test_plan_id' => $release->id]);

        CustomFieldValue::factory()->about($release)->answering($fields['sprint'], 'Sprint 18')->create();

        $release->platforms()->attach([$platforms['chrome']->id, $platforms['firefox']->id, $platforms['ios']->id]);
        $regression->platforms()->attach([$platforms['chrome']->id]);

        $build240 = Build::factory()->for($release, 'testPlan')->named('2.4.0')->create([
            'notes' => 'First candidate.',
            'release_date' => now()->subWeeks(3)->toDateString(),
            'author_id' => $users['leader']->id,
        ]);

        $build241 = Build::factory()->for($release, 'testPlan')->named('2.4.1')->create([
            'notes' => 'Lockout fix build.',
            'release_date' => now()->subDays(4)->toDateString(),
            'author_id' => $users['leader']->id,
        ]);

        Build::factory()->for($regression, 'testPlan')->named('2.3.9')->create([
            'notes' => 'Last 2.3 patch.',
            'is_open' => false,
            'release_date' => now()->subMonths(2)->toDateString(),
            'author_id' => $users['leader']->id,
        ]);

        Milestone::factory()->for($release, 'testPlan')->create([
            'name' => 'RC1',
            'start_date' => now()->subWeek()->toDateString(),
            'target_date' => now()->addWeek()->toDateString(),
            'high_percent' => 100,
            'medium_percent' => 80,
            'low_percent' => 50,
        ]);

        $items = [];
        $sort = 0;

        foreach ([
            ['unknownEmail', 'chrome', TestCaseUrgency::High],
            ['unknownEmail', 'firefox', TestCaseUrgency::Medium],
            ['lockout', 'chrome', TestCaseUrgency::High],
            ['validCard', 'chrome', TestCaseUrgency::High],
            ['validCard', 'firefox', TestCaseUrgency::Medium],
            ['expiredCard', 'chrome', TestCaseUrgency::Medium],
            ['wallet', 'chrome', TestCaseUrgency::Low],
        ] as [$caseKey, $platformKey, $urgency]) {
            $items[$caseKey.'_'.$platformKey] = TestPlanItem::factory()
                ->for($release, 'testPlan')
                ->for($cases[$caseKey]['version'], 'testCaseVersion')
                ->onPlatform($platforms[$platformKey]->id)
                ->create([
                    'sort_order' => $sort++,
                    'urgency' => $urgency,
                    'author_id' => $users['leader']->id,
                ]);
        }

        TesterAssignment::factory()
            ->for($items['lockout_chrome'], 'testPlanItem')
            ->for($build240, 'build')
            ->for($users['tester'], 'user')
            ->create([
                'assigner_id' => $users['leader']->id,
                'status' => TesterAssignmentStatus::Completed,
                'deadline_at' => now()->subWeek(),
            ]);

        TesterAssignment::factory()
            ->for($items['lockout_chrome'], 'testPlanItem')
            ->for($build241, 'build')
            ->for($users['tester'], 'user')
            ->urgent()
            ->create([
                'assigner_id' => $users['leader']->id,
                'deadline_at' => now()->addDays(2),
            ]);

        TesterAssignment::factory()
            ->for($items['wallet_chrome'], 'testPlanItem')
            ->for($build241, 'build')
            ->for($users['tester'], 'user')
            ->create([
                'assigner_id' => $users['senior']->id,
                'status' => TesterAssignmentStatus::Todo,
                'deadline_at' => now()->addDays(5),
            ]);

        $this->completeRun(
            plan: $release,
            build: $build240,
            item: $items['unknownEmail_chrome'],
            tester: $users['senior'],
            status: ExecutionStatus::Passed,
            executedAt: now()->subWeeks(3),
            notes: 'Unknown email copy matches the spec.',
            environment: $fields['environment'],
            environmentValue: 'staging-eu',
        );

        $this->completeRun(
            plan: $release,
            build: $build240,
            item: $items['unknownEmail_firefox'],
            tester: $users['senior'],
            status: ExecutionStatus::Passed,
            executedAt: now()->subWeeks(3)->addHours(2),
            notes: null,
            environment: $fields['environment'],
            environmentValue: 'staging-eu',
        );

        $failedLockout = $this->completeRun(
            plan: $release,
            build: $build240,
            item: $items['lockout_chrome'],
            tester: $users['tester'],
            status: ExecutionStatus::Failed,
            executedAt: now()->subWeeks(2),
            notes: 'Fourth attempt still signed in.',
            environment: $fields['environment'],
            environmentValue: 'staging-eu',
        );

        ExecutionIssue::factory()->for($failedLockout)->create([
            'issue_id' => 'CO-1847',
            'issue_url' => 'https://acme.atlassian.net/browse/CO-1847',
            'issue_status' => 'In Progress',
            'issue_summary' => 'Lockout does not persist across the fourth attempt',
            'status_fetched_at' => now()->subHour(),
        ]);

        $this->completeRun(
            plan: $release,
            build: $build240,
            item: $items['validCard_chrome'],
            tester: $users['senior'],
            status: ExecutionStatus::Passed,
            executedAt: now()->subWeeks(2)->addDay(),
            notes: null,
            environment: $fields['environment'],
            environmentValue: 'staging-eu',
        );

        $this->completeRun(
            plan: $release,
            build: $build240,
            item: $items['expiredCard_chrome'],
            tester: $users['senior'],
            status: ExecutionStatus::Blocked,
            executedAt: now()->subWeeks(2)->addDays(2),
            notes: 'Expired-card fixture was missing from the gateway sandbox.',
            environment: $fields['environment'],
            environmentValue: 'staging-eu',
        );

        $this->completeRun(
            plan: $release,
            build: $build241,
            item: $items['unknownEmail_chrome'],
            tester: $users['senior'],
            status: ExecutionStatus::Passed,
            executedAt: now()->subDays(3),
            notes: null,
            environment: $fields['environment'],
            environmentValue: 'staging-us',
        );

        $this->completeRun(
            plan: $release,
            build: $build241,
            item: $items['lockout_chrome'],
            tester: $users['tester'],
            status: ExecutionStatus::Passed,
            executedAt: now()->subDays(2),
            notes: 'Lockout now holds after the third failure.',
            environment: $fields['environment'],
            environmentValue: 'staging-us',
        );

        $this->completeRun(
            plan: $release,
            build: $build241,
            item: $items['validCard_chrome'],
            tester: $users['senior'],
            status: ExecutionStatus::Passed,
            executedAt: now()->subDays(2)->addHours(3),
            notes: null,
            environment: $fields['environment'],
            environmentValue: 'staging-us',
        );

        $this->draftRun(
            plan: $release,
            build: $build241,
            item: $items['expiredCard_chrome'],
            tester: $users['senior'],
        );

        $this->seedBaseline($release, $build240, $items, $users['leader']);
    }

    /**
     * @param  array<string, TestPlanItem>  $items
     */
    private function seedBaseline(TestPlan $plan, Build $build, array $items, User $author): void
    {
        $snapshot = [];

        foreach ($items as $item) {
            $item->loadMissing(['testCaseVersion.testCase.testProject', 'platform']);

            $snapshot[] = [
                'id' => $item->id,
                'full_external_id' => $item->testCaseVersion->testCase->fullExternalId(),
                'name' => $item->testCaseVersion->testCase->name,
                'platform' => $item->platform?->name,
                'status' => match ($item->id) {
                    $items['unknownEmail_chrome']->id, $items['unknownEmail_firefox']->id => 'passed',
                    $items['lockout_chrome']->id => 'failed',
                    $items['validCard_chrome']->id => 'not_run',
                    $items['expiredCard_chrome']->id => 'blocked',
                    default => 'not_run',
                },
            ];
        }

        $counts = [
            'passed' => 2,
            'failed' => 1,
            'blocked' => 1,
            'not_run' => count($snapshot) - 4,
        ];

        ReportBaseline::factory()->for($plan, 'testPlan')->for($author, 'user')->create([
            'build_id' => $build->id,
            'name' => 'RC0 snapshot',
            'total' => count($snapshot),
            'counts' => $counts,
            'items' => $snapshot,
        ]);
    }

    private function completeRun(
        TestPlan $plan,
        Build $build,
        TestPlanItem $item,
        User $tester,
        ExecutionStatus $status,
        mixed $executedAt,
        ?string $notes,
        CustomField $environment,
        string $environmentValue,
    ): Execution {
        $version = $item->testCaseVersion()->with('steps')->firstOrFail();

        $execution = Execution::factory()
            ->for($plan, 'testPlan')
            ->for($build, 'build')
            ->for($item, 'testPlanItem')
            ->for($version, 'testCaseVersion')
            ->for($tester, 'tester')
            ->completed()
            ->create([
                'version' => $version->version,
                'status' => $status,
                'notes' => $notes,
                'duration' => 6.5,
                'executed_at' => $executedAt,
            ]);

        foreach ($version->steps as $step) {
            ExecutionStep::factory()->for($execution)->for($step, 'testCaseStep')->create([
                'sort_order' => $step->sort_order,
                'status' => $status,
            ]);
        }

        CustomFieldValue::factory()->about($execution)->answering($environment, $environmentValue)->create();

        return $execution;
    }

    private function draftRun(TestPlan $plan, Build $build, TestPlanItem $item, User $tester): void
    {
        $version = $item->testCaseVersion()->with('steps')->firstOrFail();

        $execution = Execution::factory()
            ->for($plan, 'testPlan')
            ->for($build, 'build')
            ->for($item, 'testPlanItem')
            ->for($version, 'testCaseVersion')
            ->for($tester, 'tester')
            ->create([
                'version' => $version->version,
                'status' => ExecutionStatus::NotRun,
                'notes' => 'Waiting on the expired-card fixture.',
                'is_draft' => true,
            ]);

        foreach ($version->steps as $step) {
            ExecutionStep::factory()->for($execution)->for($step, 'testCaseStep')->create([
                'sort_order' => $step->sort_order,
                'status' => ExecutionStatus::NotRun,
            ]);
        }
    }

    /**
     * @param  array<string, User>  $users
     * @param  array<string, Role>  $roles
     * @param  array{risk: CustomField, environment: CustomField, sprint: CustomField}  $fields
     */
    private function seedInternalToolsProject(array $users, array $roles, array $fields): void
    {
        $project = TestProject::factory()->restricted()->create([
            'name' => 'Internal Tools',
            'prefix' => 'IT',
            'description' => 'Restricted. Only assigned members can see it.',
            'is_active' => true,
        ]);

        $users['leader']->projectRoles()->attach($roles['Leader'], [
            'test_project_id' => $project->id,
        ]);

        $fields['risk']->testProjects()->attach($project, [
            'is_active' => true,
            'sort_order' => 0,
            'required_on_design' => false,
            'required_on_execution' => false,
        ]);

        $suite = TestSuite::factory()->for($project)->create([
            'name' => 'Admin console',
            'sort_order' => 0,
        ]);

        $this->addCase(
            suite: $suite,
            author: $users['leader'],
            name: 'Staff can open the ops dashboard',
            summary: '<p>A staff account reaches /ops without an extra prompt.</p>',
            preconditions: '<p>The actor holds the staff role.</p>',
            importance: TestCaseImportance::Medium,
            status: TestCaseStatus::Draft,
            steps: [
                ['Sign in as staff and open /ops.', 'The dashboard renders.'],
            ],
            keywordNames: [],
            risk: $fields['risk'],
            riskValue: 'Low',
            platforms: [],
        );

        $plan = TestPlan::factory()->for($project)->restricted()->create([
            'name' => 'Ops smoke',
            'is_active' => true,
            'is_open' => true,
        ]);

        Build::factory()->for($plan, 'testPlan')->named('ops-1')->create([
            'author_id' => $users['leader']->id,
        ]);
    }

    private function seedAuditTrail(User $admin, TestProject $checkout): void
    {
        AuditEvent::factory()->for($admin)->create([
            'action' => AuditAction::TestProjectCreated,
            'subject_type' => $checkout->getMorphClass(),
            'subject_id' => $checkout->id,
            'properties' => ['name' => $checkout->name],
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subWeeks(4),
        ]);

        AuditEvent::factory()->for($admin)->create([
            'action' => AuditAction::TestPlanCreated,
            'subject_type' => TestPlan::class,
            'subject_id' => $checkout->testPlans()->where('name', 'Release 2.4')->value('id'),
            'properties' => ['name' => 'Release 2.4'],
            'ip_address' => '127.0.0.1',
            'created_at' => now()->subWeeks(3),
        ]);

        AuditEvent::factory()->fromTheConsole()->create([
            'action' => AuditAction::ExecutionDraftsPruned,
            'properties' => ['deleted' => 0],
            'created_at' => now()->subDay(),
        ]);
    }
}
