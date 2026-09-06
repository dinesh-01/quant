<?php

namespace Tests\Unit;

use App\Enums\Ability;
use PHPUnit\Framework\TestCase;

class AbilityTest extends TestCase
{
    /**
     * `Ability::grouped()` is what the role form renders, so an ability missing
     * from it can never be granted and one listed twice would render two
     * checkboxes writing the same value. Neither fails loudly on its own.
     */
    public function test_every_ability_appears_in_exactly_one_group()
    {
        $grouped = array_map(
            static fn (Ability $ability): string => $ability->value,
            array_merge(...array_values(Ability::grouped())),
        );

        $all = array_map(static fn (Ability $ability): string => $ability->value, Ability::cases());

        $this->assertSame(
            [],
            array_values(array_diff($all, $grouped)),
            'Abilities missing from Ability::grouped() cannot be granted when editing a role.',
        );

        $this->assertCount(
            count(array_unique($grouped)),
            $grouped,
            'An ability is listed under more than one group, which would render two checkboxes for it.',
        );
    }

    public function test_groups_keep_the_declaration_order_of_their_abilities()
    {
        $this->assertSame(
            [Ability::ViewTestCases, Ability::ManageTestCases],
            array_slice(Ability::grouped()['Test specification'], 0, 2),
        );
    }

    public function test_labels_read_as_sentences()
    {
        $this->assertSame('Manage users', Ability::ManageUsers->label());
        $this->assertSame(
            'Assign keywords to executed test cases',
            Ability::AssignKeywordsToExecutedTestCases->label(),
        );
    }

    public function test_system_abilities_report_themselves_as_such()
    {
        $this->assertTrue(Ability::ManageRoles->isSystem());
        $this->assertFalse(Ability::ExecuteTests->isSystem());
    }

    public function test_restriction_abilities_report_themselves_as_such()
    {
        $this->assertTrue(Ability::ExecuteOnlyAssignedTestCases->isRestriction());
        $this->assertFalse(Ability::ExecuteTests->isRestriction());
    }
}
