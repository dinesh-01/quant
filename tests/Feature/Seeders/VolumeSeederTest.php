<?php

namespace Tests\Feature\Seeders;

use App\Enums\TestPlanStatus;
use App\Models\ExecutionIssue;
use App\Models\TestCase as TestCaseModel;
use App\Models\TestPlan;
use App\Models\TestProject;
use App\Models\TestSuite;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\VolumeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VolumeSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_fills_checkout_to_the_mockup_volume(): void
    {
        Storage::fake('attachments');

        $this->seed(DatabaseSeeder::class);
        $this->seed(VolumeSeeder::class);

        $checkout = TestProject::query()->where('prefix', 'CO')->firstOrFail();

        $this->assertSame(VolumeSeeder::TARGET_CASES, $checkout->testCases()->count());
        $this->assertSame(VolumeSeeder::TARGET_CASES, $checkout->test_case_counter);
        $this->assertGreaterThanOrEqual(VolumeSeeder::TARGET_SUITES, $checkout->testSuites()->count());
        $this->assertTrue(TestSuite::query()->where('test_project_id', $checkout->id)->where('name', 'Promotions')->exists());
        $this->assertTrue(TestCaseModel::query()->where('test_project_id', $checkout->id)->where('external_id', VolumeSeeder::TARGET_CASES)->exists());

        $this->assertSame(4, TestPlan::query()->where('test_project_id', $checkout->id)->where('status', TestPlanStatus::Active)->count());
        $this->assertSame(3, TestPlan::query()->where('test_project_id', $checkout->id)->where('status', TestPlanStatus::Draft)->count());
        $this->assertSame(2, TestPlan::query()->where('test_project_id', $checkout->id)->where('status', TestPlanStatus::Archived)->count());
        $this->assertGreaterThanOrEqual(12, ExecutionIssue::query()
            ->whereHas('execution.testPlan', fn ($query) => $query->where('test_project_id', $checkout->id))
            ->count());
    }

    public function test_a_second_run_does_not_duplicate_volume_rows(): void
    {
        Storage::fake('attachments');

        $this->seed(DatabaseSeeder::class);
        $this->seed(VolumeSeeder::class);
        $this->seed(VolumeSeeder::class);

        $checkout = TestProject::query()->where('prefix', 'CO')->firstOrFail();

        $this->assertSame(VolumeSeeder::TARGET_CASES, $checkout->testCases()->count());
        $this->assertSame(9, $checkout->testPlans()->count());
    }
}
