<?php

namespace Tests\Feature\Seeders;

use App\Enums\ExecutionStatus;
use App\Models\CodeTracker;
use App\Models\CustomField;
use App\Models\Execution;
use App\Models\IssueTracker;
use App\Models\Keyword;
use App\Models\ReportBaseline;
use App\Models\Requirement;
use App\Models\RequirementCoverage;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_default_seed_builds_a_walkable_demo(): void
    {
        Storage::fake('attachments');

        $this->seed(DatabaseSeeder::class);

        $admin = User::query()->where('email', 'test@example.com')->first();
        $this->assertNotNull($admin);
        $this->assertTrue($admin->role?->is_super_admin);
        $this->assertNotNull(User::query()->where('email', 'rina.tester@example.com')->first());
        $this->assertFalse(User::query()->where('email', 'inactive.user@example.com')->value('is_active'));

        $checkout = TestProject::query()->where('prefix', 'CO')->first();
        $this->assertNotNull($checkout);
        $this->assertTrue($checkout->is_public);
        $this->assertSame(5, $checkout->testCases()->count());
        $this->assertSame(5, $checkout->test_case_counter);
        $this->assertTrue(TestCaseModel::query()->where('test_project_id', $checkout->id)->where('external_id', 1)->exists());
        $this->assertSame(4, Keyword::query()->where('test_project_id', $checkout->id)->count());

        $loginCase = TestCaseModel::query()
            ->where('test_project_id', $checkout->id)
            ->where('name', 'Rejects an unknown email')
            ->first();
        $this->assertNotNull($loginCase);
        $this->assertSame(2, $loginCase->versions()->count());
        $this->assertFalse($loginCase->versions()->where('version', 1)->value('is_open'));
        $this->assertTrue($loginCase->keywords()->where('name', 'smoke')->exists());

        $this->assertTrue(CustomField::query()->where('name', 'risk_level')->exists());
        $this->assertTrue(Requirement::query()->where('doc_id', 'REQ-LOGIN-001')->exists());
        $this->assertGreaterThanOrEqual(2, RequirementCoverage::query()->count());
        $this->assertTrue(Requirement::query()->where('doc_id', 'REQ-LOGIN-001')->first()?->monitors()->exists());

        $release = TestPlan::query()->where('name', 'Release 2.4')->first();
        $this->assertNotNull($release);
        $this->assertSame(7, $release->items()->count());
        $this->assertSame(2, $release->builds()->count());
        $this->assertTrue($release->milestones()->where('name', 'RC1')->exists());

        $this->assertTrue(Execution::query()->where('status', ExecutionStatus::Passed)->where('is_draft', false)->exists());
        $this->assertTrue(Execution::query()->where('status', ExecutionStatus::Failed)->where('is_draft', false)->exists());
        $this->assertTrue(Execution::query()->where('is_draft', true)->exists());
        $this->assertTrue(Execution::query()->where('status', ExecutionStatus::Failed)->first()?->issues()->where('issue_id', 'CO-1847')->exists());

        $this->assertTrue(CodeTracker::query()->where('test_project_id', $checkout->id)->where('is_enabled', true)->exists());
        $this->assertTrue(IssueTracker::query()->where('test_project_id', $checkout->id)->exists());
        $this->assertTrue(ReportBaseline::query()->where('name', 'RC0 snapshot')->exists());

        $internal = TestProject::query()->where('prefix', 'IT')->first();
        $this->assertNotNull($internal);
        $this->assertFalse($internal->is_public);
        $this->assertTrue($internal->testCases()->exists());

        $this->assertTrue(
            Storage::disk('attachments')->exists(
                $checkout->testSuites()->where('name', 'Storefront')->first()?->attachments()->value('disk_path') ?? '',
            ),
        );
    }

    public function test_a_second_seed_does_not_duplicate_the_demo_project(): void
    {
        Storage::fake('attachments');

        $this->seed(DatabaseSeeder::class);
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, TestProject::query()->where('prefix', 'CO')->count());
        $this->assertSame(1, User::query()->where('email', 'test@example.com')->count());
    }
}
