<?php

namespace App\Actions\TestPlanItems;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\TestCaseVersion;
use App\Models\TestPlanItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Moves a plan item onto a newer version of the same case.
 *
 * Defaults to the case's latest version. Only a newer version of the *same*
 * case is accepted — switching cases is unlinking and linking, not an
 * update. Refused when that newer version is already pinned on the same
 * platform, because the unique slot is (plan, version, platform).
 */
final class UpdateLinkedTestCaseVersion
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $user, TestPlanItem $item, ?TestCaseVersion $target = null): TestPlanItem
    {
        $item->loadMissing('testPlan', 'testCaseVersion.testCase');

        Gate::forUser($user)->authorize(Ability::UpdateLinkedTestCaseVersions->value, $item->testPlan);

        $source = $item->testCaseVersion;
        $target ??= $source->testCase->latestVersion;

        if ($target === null || $target->test_case_id !== $source->test_case_id) {
            throw ValidationException::withMessages([
                'version' => 'The item can only move to a newer version of the same test case.',
            ]);
        }

        if ($target->version < $source->version) {
            throw ValidationException::withMessages([
                'version' => 'The item can only move to a newer version of the same test case.',
            ]);
        }

        if ($target->is($source)) {
            return $item;
        }

        $collision = TestPlanItem::query()
            ->where('test_plan_id', $item->test_plan_id)
            ->where('test_case_version_id', $target->getKey())
            ->whereKeyNot($item->getKey())
            ->when(
                $item->platform_id === null,
                fn ($query) => $query->whereNull('platform_id'),
                fn ($query) => $query->where('platform_id', $item->platform_id),
            )
            ->exists();

        if ($collision) {
            throw ValidationException::withMessages([
                'version' => 'That version is already on this plan for that platform.',
            ]);
        }

        $from = $source->version;

        $item->testCaseVersion()->associate($target);
        $item->save();

        $this->audit->record(AuditAction::TestPlanItemVersionUpdated, $user, $item->testPlan, [
            'name' => $item->testPlan->name,
            'test_case_name' => $source->testCase->name,
            'from_version' => $from,
            'to_version' => $target->version,
        ]);

        return $item;
    }
}
