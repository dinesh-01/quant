<?php

namespace App\Enums;

use App\Models\Execution;
use App\Models\TestCaseVersion;
use App\Models\TestPlan;
use App\Models\TestSuite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * What a custom field can be attached to.
 *
 * This is legacy's `cfield_node_types` link table collapsed into a column. The
 * table had a composite primary key that could have held several node types per
 * field, but the screens only ever wrote one and `update()` overwrote whatever
 * was there, so it stored a single value behind a table's worth of ceremony.
 *
 * It also absorbs legacy's `show_on_*` / `enable_on_*` matrix. Those six
 * columns were meant to say which screen a field appears and is editable on,
 * but the edit form collapsed them into one exclusive "enable on" combo, and
 * the getters then disagreed about which flag to read: the test-plan-design
 * getter ignored `show_on_testplan_design` entirely, and the design getter
 * rewrote its own `show_on_testplan_design` filter to read `enable_on_*` under
 * a comment admitting that was probably wrong. Once values are polymorphic, the
 * screen a field belongs to *is* the thing it hangs off, so one column answers
 * what six flags could not agree on.
 *
 * Builds and the test-plan side of a linked case are still unbuilt here.
 */
enum CustomFieldEntity: string
{
    case TestCase = 'test_case';
    case TestSuite = 'test_suite';
    case TestPlan = 'test_plan';
    case Execution = 'execution';

    /**
     * A human readable name, derived rather than listed, as `Ability` does.
     */
    public function label(): string
    {
        return Str::ucfirst(Str::lower(Str::headline($this->name)));
    }

    /**
     * The model a value of this kind is actually stored against.
     *
     * Note that a test case field resolves to `TestCaseVersion`. Values belong
     * to the version so that an old version keeps the data it was written with,
     * which is legacy's behaviour too — every writer there passed
     * `$tcversion_id` — and it is why `CreateTestCaseVersion` has to copy them
     * forward. This is the one case where the enum's name and the model it
     * points at deliberately differ, because the *field* is defined against
     * "test case" in the user's language.
     *
     * @return class-string<Model>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::TestCase => TestCaseVersion::class,
            self::TestSuite => TestSuite::class,
            self::TestPlan => TestPlan::class,
            self::Execution => Execution::class,
        };
    }

    /**
     * The ability that filling in a value of this kind rides on.
     *
     * There is no separate right for writing values, in legacy or here: if you
     * may edit the case, you may fill in the case's fields. `manage_custom_fields`
     * governs the definitions and `assign_custom_fields` governs which project
     * they apply to, both of which are a different job from using them.
     */
    public function writeAbility(): Ability
    {
        return match ($this) {
            self::TestCase, self::TestSuite => Ability::ManageTestCases,
            self::TestPlan => Ability::CreateTestPlans,
            self::Execution => Ability::ExecuteTests,
        };
    }
}
