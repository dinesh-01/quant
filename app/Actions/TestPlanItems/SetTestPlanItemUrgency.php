<?php

namespace App\Actions\TestPlanItems;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Enums\TestCaseUrgency;
use App\Models\TestPlanItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/**
 * Sets how urgently a planned version should be run.
 *
 * Urgency is on the item, not the case, so the same version can be routine
 * in one plan and urgent in another. Importance stays on the version.
 */
final class SetTestPlanItemUrgency
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, TestPlanItem $item, TestCaseUrgency $urgency): TestPlanItem
    {
        $item->loadMissing('testPlan', 'testCaseVersion.testCase');

        Gate::forUser($user)->authorize(Ability::SetTestCaseUrgency->value, $item->testPlan);

        $item->urgency = $urgency;

        $properties = $this->audit->changes($item);

        $item->save();

        if ($properties !== []) {
            $this->audit->record(AuditAction::TestPlanItemUrgencySet, $user, $item->testPlan, [
                'name' => $item->testPlan->name,
                'test_case_name' => $item->testCaseVersion->testCase->name,
                'version' => $item->testCaseVersion->version,
                ...$properties,
            ]);
        }

        return $item;
    }
}
