<?php

namespace App\Http\Controllers\Reports;

use App\Actions\Reports\SaveReportBaseline;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StoreReportBaselineRequest;
use App\Models\Build;
use App\Models\ReportBaseline;
use App\Models\TestPlan;
use App\Reports\ComparePlanBaseline;
use App\Reports\ExecutionStatusOverTime;
use App\Reports\MilestoneProgressReport;
use App\Reports\PlanStatusReport;
use App\Reports\RequirementCoverageReport;
use App\Reports\TesterProgressReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Status, testers, milestones and coverage for one plan.
 */
class PlanReportsController extends Controller
{
    public function show(
        Request $request,
        TestPlan $testPlan,
        PlanStatusReport $planStatusReport,
        TesterProgressReport $testerProgressReport,
        MilestoneProgressReport $milestoneProgressReport,
        RequirementCoverageReport $requirementCoverageReport,
        ExecutionStatusOverTime $executionStatusOverTime,
        ComparePlanBaseline $comparePlanBaseline,
    ): Response {
        $user = $this->actingUser($request);

        Gate::forUser($user)->authorize(Ability::ViewPlanMetrics->value, $testPlan);

        $testPlan->loadMissing('testProject');
        $build = $this->selectedBuild($request, $testPlan);
        $status = $planStatusReport($testPlan, $build);

        return Inertia::render('reports/plan', [
            'project' => [
                'id' => $testPlan->testProject->id,
                'name' => $testPlan->testProject->name,
            ],
            'plan' => [
                'id' => $testPlan->id,
                'name' => $testPlan->name,
            ],
            'builds' => $testPlan->builds()
                ->orderByDesc('id')
                ->get()
                ->map(fn (Build $each): array => [
                    'id' => $each->id,
                    'name' => $each->name,
                ])
                ->all(),
            'selectedBuildId' => $build?->id,
            'status' => $status,
            'timeline' => $executionStatusOverTime($testPlan, $build),
            'baselines' => $this->baselineProps($testPlan),
            'comparison' => $this->comparison($request, $testPlan, $status, $comparePlanBaseline),
            'testers' => $build === null ? [] : $testerProgressReport($build),
            'milestones' => $milestoneProgressReport($testPlan, $build),
            'coverage' => $requirementCoverageReport($testPlan, $build),
        ]);
    }

    public function storeBaseline(
        StoreReportBaselineRequest $request,
        TestPlan $testPlan,
        SaveReportBaseline $saveReportBaseline,
    ): RedirectResponse {
        $build = $this->selectedBuild($request, $testPlan);

        $saveReportBaseline(
            $this->actingUser($request),
            $testPlan,
            $build,
            $request->baselineName(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Baseline saved.')]);

        return to_route('reports.plan', [
            'testPlan' => $testPlan,
            ...($build === null ? [] : ['build' => $build->id]),
        ]);
    }

    public function statusCsv(
        Request $request,
        TestPlan $testPlan,
        PlanStatusReport $planStatusReport,
    ): StreamedResponse {
        Gate::forUser($this->actingUser($request))
            ->authorize(Ability::ViewPlanMetrics->value, $testPlan);

        $build = $this->selectedBuild($request, $testPlan);
        $status = $planStatusReport($testPlan, $build);

        return response()->streamDownload(function () use ($status): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['external_id', 'name', 'platform', 'status']);

            foreach ($status['items'] as $item) {
                fputcsv($handle, [
                    $item['full_external_id'],
                    $item['name'],
                    $item['platform'] ?? '',
                    $item['status'],
                ]);
            }

            fclose($handle);
        }, $this->exportFilename($testPlan, $build, 'csv'), [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function statusXlsx(
        Request $request,
        TestPlan $testPlan,
        PlanStatusReport $planStatusReport,
    ): StreamedResponse {
        Gate::forUser($this->actingUser($request))
            ->authorize(Ability::ViewPlanMetrics->value, $testPlan);

        $build = $this->selectedBuild($request, $testPlan);
        $status = $planStatusReport($testPlan, $build);
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $rows = [['external_id', 'name', 'platform', 'status']];

        foreach ($status['items'] as $item) {
            $rows[] = [
                $item['full_external_id'],
                $item['name'],
                $item['platform'] ?? '',
                $item['status'],
            ];
        }

        $sheet->fromArray($rows);

        return response()->streamDownload(function () use ($spreadsheet): void {
            (new Xlsx($spreadsheet))->save('php://output');
        }, $this->exportFilename($testPlan, $build, 'xlsx'), [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * @return list<array{id: int, name: string, build_id: int|null, build_name: string|null, created_at: string, total: int, counts: array{passed: int, failed: int, blocked: int, not_run: int}}>
     */
    private function baselineProps(TestPlan $plan): array
    {
        return $plan->reportBaselines()
            ->with('build:id,name')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ReportBaseline $baseline): array => [
                'id' => $baseline->id,
                'name' => $baseline->name,
                'build_id' => $baseline->build_id,
                'build_name' => $baseline->build?->name,
                'created_at' => $baseline->created_at?->toDateTimeString() ?? '',
                'total' => $baseline->total,
                'counts' => $baseline->counts,
            ])
            ->all();
    }

    /**
     * @param  array{counts: array{passed: int, failed: int, blocked: int, not_run: int}, items: list<array{id: int, full_external_id: string, name: string, platform: string|null, status: string}>}  $status
     * @return array{
     *     baseline: array{id: int, name: string, created_at: string},
     *     live_counts: array{passed: int, failed: int, blocked: int, not_run: int},
     *     baseline_counts: array{passed: int, failed: int, blocked: int, not_run: int},
     *     changed: list<array{full_external_id: string, name: string, platform: string|null, live: string|null, baseline: string|null}>
     * }|null
     */
    private function comparison(
        Request $request,
        TestPlan $plan,
        array $status,
        ComparePlanBaseline $comparePlanBaseline,
    ): ?array {
        if (! $request->filled('baseline')) {
            return null;
        }

        $baseline = ReportBaseline::query()
            ->where('test_plan_id', $plan->id)
            ->whereKey($request->integer('baseline'))
            ->first();

        abort_unless($baseline instanceof ReportBaseline, 404);

        return $comparePlanBaseline($status, $baseline);
    }

    private function selectedBuild(Request $request, TestPlan $plan): ?Build
    {
        $builds = $plan->builds()->orderByDesc('id')->get();

        if ($request->filled('build')) {
            $build = $builds->firstWhere('id', $request->integer('build'));

            abort_unless($build instanceof Build, 404);

            return $build;
        }

        return $builds->first();
    }

    private function exportFilename(TestPlan $plan, ?Build $build, string $extension): string
    {
        $slug = str_replace(' ', '-', strtolower($plan->name));
        $buildSlug = $build === null ? 'no-build' : str_replace(' ', '-', strtolower($build->name));

        return $slug.'-'.$buildSlug.'-status.'.$extension;
    }
}
