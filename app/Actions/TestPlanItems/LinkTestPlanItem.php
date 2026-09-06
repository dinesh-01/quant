<?php

namespace App\Actions\TestPlanItems;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\TestCaseUrgency;
use App\Models\Platform;
use App\Models\TestCaseVersion;
use App\Models\TestPlan;
use App\Models\TestPlanItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Pins a test case version onto a plan, optionally on one platform.
 *
 * The version must belong to the plan's project. A plan with no platforms
 * takes a single no-platform row; a plan with platforms requires one of
 * those platforms. Linking the same version twice on the same platform is
 * refused rather than colliding on the unique index.
 */
final class LinkTestPlanItem
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(
        User $user,
        TestPlan $plan,
        TestCaseVersion $version,
        ?Platform $platform = null,
        TestCaseUrgency $urgency = TestCaseUrgency::Medium,
    ): TestPlanItem {
        $plan->loadMissing('testProject');
        $version->loadMissing('testCase');

        Gate::forUser($user)->authorize(Ability::PlanTestCases->value, $plan);

        if ($version->testCase->test_project_id !== $plan->test_project_id) {
            throw ValidationException::withMessages([
                'version' => 'That test case does not belong to this project.',
            ]);
        }

        $assigned = $plan->platforms()->pluck('platforms.id');

        if ($assigned->isEmpty()) {
            if ($platform !== null) {
                throw ValidationException::withMessages([
                    'platform' => 'This plan has no platforms. Link the case without one.',
                ]);
            }
        } else {
            if ($platform === null) {
                throw ValidationException::withMessages([
                    'platform' => 'This plan uses platforms. Choose one.',
                ]);
            }

            if (! $assigned->contains($platform->getKey())) {
                throw ValidationException::withMessages([
                    'platform' => 'That platform is not assigned to this plan.',
                ]);
            }
        }

        $alreadyLinked = TestPlanItem::query()
            ->where('test_plan_id', $plan->getKey())
            ->where('test_case_version_id', $version->getKey())
            ->when(
                $platform === null,
                fn ($query) => $query->whereNull('platform_id'),
                fn ($query) => $query->where('platform_id', $platform->getKey()),
            )
            ->exists();

        if ($alreadyLinked) {
            throw ValidationException::withMessages([
                'version' => 'That version is already on this plan for that platform.',
            ]);
        }

        $item = new TestPlanItem;
        $item->testPlan()->associate($plan);
        $item->testCaseVersion()->associate($version);

        if ($platform === null) {
            $item->platform()->dissociate();
        } else {
            $item->platform()->associate($platform);
        }

        $item->urgency = $urgency;
        $item->sort_order = (int) $plan->items()->max('sort_order') + 1;
        $item->author_id = $user->getKey();
        $item->save();

        $this->audit->record(AuditAction::TestPlanItemLinked, $user, $plan, [
            'name' => $plan->name,
            'test_case_id' => $version->test_case_id,
            'test_case_name' => $version->testCase->name,
            'version' => $version->version,
            'platform' => $platform?->name,
            'urgency' => $urgency->value,
        ]);

        return $item;
    }
}
