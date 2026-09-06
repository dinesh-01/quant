<?php

namespace Database\Seeders;

use App\Enums\Ability;
use App\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the default roles. Re-running restores their default grants.
     */
    public function run(): void
    {
        foreach ($this->roles() as $role) {
            Role::updateOrCreate(['name' => $role['name']], $role);
        }
    }

    /**
     * The roles a new installation starts with.
     *
     * @return array<int, array<string, mixed>>
     */
    private function roles(): array
    {
        return [
            [
                'name' => 'Admin',
                'description' => 'Unrestricted access to every test project and administration screen.',
                'abilities' => Ability::cases(),
                'is_super_admin' => true,
            ],
            [
                'name' => 'Leader',
                'description' => 'Runs a test project: specification, planning, execution and role assignment.',
                'abilities' => [
                    Ability::AssignGlobalRoles,
                    Ability::AssignPlanRoles,
                    Ability::CreateTestPlans,
                    Ability::PlanTestCases,
                    Ability::ManageBuilds,
                    Ability::ExecuteTests,
                    Ability::ViewExecutions,
                    Ability::AssignTesters,
                    Ability::ManageMilestones,
                    Ability::ViewTestCases,
                    Ability::ManageTestCases,
                    Ability::FreezeTestCases,
                    Ability::ViewKeywords,
                    Ability::ManageKeywords,
                    Ability::AssignKeywords,
                    Ability::ViewRequirements,
                    Ability::ManageRequirements,
                    Ability::UnfreezeRequirements,
                    Ability::MonitorRequirements,
                    Ability::ManageRequirementCoverage,
                    Ability::ViewPlatforms,
                    Ability::ManagePlatforms,
                    Ability::ManagePlanPlatforms,
                    Ability::SetTestCaseUrgency,
                    Ability::UpdateLinkedTestCaseVersions,
                    Ability::ViewInventory,
                    Ability::ManageInventory,
                    Ability::ViewPlanMetrics,
                    Ability::ViewProjectMetrics,
                    Ability::ViewCodeTrackers,
                    Ability::ManageCodeTrackers,
                    Ability::ViewIssueTrackers,
                    Ability::ManageIssueTrackers,
                ],
            ],
            [
                'name' => 'Senior Tester',
                'description' => 'Executes tests and maintains the test specification.',
                'abilities' => [
                    Ability::ExecuteTests,
                    Ability::ViewExecutions,
                    Ability::AssignTesters,
                    Ability::ManageBuilds,
                    Ability::ViewTestCases,
                    Ability::ManageTestCases,
                    Ability::ViewKeywords,
                    Ability::ManageKeywords,
                    Ability::AssignKeywords,
                    // The replaced application granted requirement editing
                    // without the matching view right, leaving the screens
                    // unreachable; both are granted here.
                    Ability::ViewRequirements,
                    Ability::ManageRequirements,
                    Ability::UnfreezeRequirements,
                    Ability::MonitorRequirements,
                    Ability::ManageRequirementCoverage,
                    Ability::ViewPlatforms,
                    Ability::ViewInventory,
                    Ability::ViewPlanMetrics,
                    Ability::ViewCodeTrackers,
                    Ability::ViewIssueTrackers,
                ],
            ],
            [
                'name' => 'Tester',
                'description' => 'Executes the tests assigned to them.',
                'abilities' => [
                    Ability::ExecuteTests,
                    Ability::ViewExecutions,
                    Ability::ExecuteOnlyAssignedTestCases,
                    Ability::ViewTestCases,
                    Ability::ViewKeywords,
                    Ability::ViewPlanMetrics,
                ],
            ],
            [
                'name' => 'Test Designer',
                'description' => 'Writes test cases and requirements but does not execute tests.',
                'abilities' => [
                    Ability::ViewTestCases,
                    Ability::ManageTestCases,
                    Ability::ViewKeywords,
                    Ability::ManageKeywords,
                    Ability::AssignKeywords,
                    Ability::ViewRequirements,
                    Ability::ManageRequirements,
                    Ability::UnfreezeRequirements,
                    Ability::MonitorRequirements,
                    Ability::ManageRequirementCoverage,
                    Ability::ViewPlatforms,
                    Ability::ViewPlanMetrics,
                    Ability::ViewCodeTrackers,
                    Ability::ViewIssueTrackers,
                ],
            ],
            [
                'name' => 'Guest',
                'description' => 'Read-only access to test cases, keywords, platforms and plan metrics.',
                'abilities' => [
                    Ability::ViewTestCases,
                    Ability::ViewKeywords,
                    Ability::ViewPlatforms,
                    Ability::ViewPlanMetrics,
                ],
                'is_default' => true,
            ],
            [
                'name' => 'No Access',
                'description' => 'Grants nothing. Used to suspend access without deleting assignments.',
                'abilities' => [],
            ],
        ];
    }
}
