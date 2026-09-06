<?php

namespace App\Actions\Reports;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\Build;
use App\Models\ReportBaseline;
use App\Models\TestPlan;
use App\Models\User;
use App\Reports\PlanStatusReport;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Freezes the current plan-status report as a named snapshot.
 */
final class SaveReportBaseline
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PlanStatusReport $planStatusReport,
    ) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, TestPlan $plan, ?Build $build, string $name): ReportBaseline
    {
        Gate::forUser($user)->authorize(Ability::ViewPlanMetrics->value, $plan);

        $status = ($this->planStatusReport)($plan, $build);

        $baseline = new ReportBaseline;
        $baseline->test_plan_id = $plan->id;
        $baseline->build_id = $build?->id;
        $baseline->user_id = $user->id;
        $baseline->name = $name;
        $baseline->total = $status['total'];
        $baseline->counts = $status['counts'];
        $baseline->items = $status['items'];
        $baseline->save();

        $this->audit->record(AuditAction::ReportBaselineSaved, $user, $baseline, [
            'name' => $baseline->name,
            'test_plan_id' => $plan->id,
            'build_id' => $build?->id,
            'total' => $baseline->total,
        ]);

        return $baseline;
    }
}
