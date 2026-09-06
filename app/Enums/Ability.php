<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * The complete catalogue of permissions a role may grant.
 *
 * System abilities cover application-wide administration and are not tied to a
 * test project or test plan, so they are only ever granted by a user's global
 * role. Every other ability is content-scoped and is resolved against the most
 * specific role the user holds for the project or plan being acted upon.
 */
enum Ability: string
{
    case ManageUsers = 'manage_users';
    case ManageRoles = 'manage_roles';
    case AssignGlobalRoles = 'assign_global_roles';
    case ManageSystemSettings = 'manage_system_settings';
    case ManageTestProjects = 'manage_test_projects';
    case ManagePlugins = 'manage_plugins';
    case ViewEventLog = 'view_event_log';
    case ManageEventLog = 'manage_event_log';

    case ViewTestCases = 'view_test_cases';
    case ManageTestCases = 'manage_test_cases';
    case FreezeTestCases = 'freeze_test_cases';
    case DeleteFrozenTestCaseVersions = 'delete_frozen_test_case_versions';

    case ViewKeywords = 'view_keywords';
    case ManageKeywords = 'manage_keywords';
    case AssignKeywords = 'assign_keywords';
    case AssignKeywordsToExecutedTestCases = 'assign_keywords_to_executed_test_cases';

    case ViewRequirements = 'view_requirements';
    case ManageRequirements = 'manage_requirements';
    case UnfreezeRequirements = 'unfreeze_requirements';
    case MonitorRequirements = 'monitor_requirements';
    case ManageRequirementCoverage = 'manage_requirement_coverage';

    case ViewCustomFields = 'view_custom_fields';
    case ManageCustomFields = 'manage_custom_fields';
    case AssignCustomFields = 'assign_custom_fields';

    case ViewPlatforms = 'view_platforms';
    case ManagePlatforms = 'manage_platforms';
    case ManagePlanPlatforms = 'manage_plan_platforms';

    case ViewInventory = 'view_inventory';
    case ManageInventory = 'manage_inventory';

    case ViewIssueTrackers = 'view_issue_trackers';
    case ManageIssueTrackers = 'manage_issue_trackers';
    case ViewCodeTrackers = 'view_code_trackers';
    case ManageCodeTrackers = 'manage_code_trackers';
    case ViewRequirementSources = 'view_requirement_sources';
    case ManageRequirementSources = 'manage_requirement_sources';

    case CreateTestPlans = 'create_test_plans';
    case PlanTestCases = 'plan_test_cases';
    case ManageBuilds = 'manage_builds';
    case ManageMilestones = 'manage_milestones';
    case UpdateLinkedTestCaseVersions = 'update_linked_test_case_versions';
    case SetTestCaseUrgency = 'set_test_case_urgency';
    case UnlinkExecutedTestCases = 'unlink_executed_test_cases';

    case AssignProjectRoles = 'assign_project_roles';
    case AssignPlanRoles = 'assign_plan_roles';

    case ExecuteTests = 'execute_tests';
    case ViewExecutions = 'view_executions';
    case ExecuteOnlyAssignedTestCases = 'execute_only_assigned_test_cases';
    case AssignTesters = 'assign_testers';
    case EditExecutionNotes = 'edit_execution_notes';
    case DeleteExecutions = 'delete_executions';
    case EditExecutedTestCases = 'edit_executed_test_cases';
    case DeleteExecutedTestCases = 'delete_executed_test_cases';

    case ViewPlanMetrics = 'view_plan_metrics';
    case ViewProjectMetrics = 'view_project_metrics';

    /**
     * Determine whether this ability is application-wide rather than scoped to
     * a test project or test plan.
     */
    public function isSystem(): bool
    {
        return in_array($this, self::system(), true);
    }

    /**
     * Determine whether this ability narrows another grant rather than
     * conferring one.
     *
     * Super-admin roles pass every grant. They must not pass a restriction, or
     * the holder is treated as assigned-only and the execute list goes empty.
     */
    public function isRestriction(): bool
    {
        return $this === self::ExecuteOnlyAssignedTestCases;
    }

    /**
     * A human readable name, derived rather than listed.
     *
     * Fifty-four hand-written labels would drift from the cases they describe;
     * the case names already read as sentences once split.
     */
    public function label(): string
    {
        return Str::ucfirst(Str::lower(Str::headline($this->name)));
    }

    /**
     * Every ability under the heading it appears beneath when a role is edited.
     *
     * Fifty-four undifferentiated checkboxes are unusable, so the form needs
     * these headings. They are presentational only — nothing authorizes on a
     * group, which is why they are strings rather than an enum of their own.
     *
     * Declared here rather than derived from a `match` on each case: this is
     * the only copy, so a group cannot disagree with itself, and the arrays
     * fix the order within each heading. `AbilityTest` asserts that every case
     * appears, since an ability missing from this map would simply not be
     * offered when editing a role.
     *
     * @return array<string, list<self>>
     */
    public static function grouped(): array
    {
        return [
            'Administration' => [
                self::ManageUsers,
                self::ManageRoles,
                self::AssignGlobalRoles,
                self::ManageSystemSettings,
                self::ManageTestProjects,
                self::ManagePlugins,
                self::ViewEventLog,
                self::ManageEventLog,
            ],
            'Test specification' => [
                self::ViewTestCases,
                self::ManageTestCases,
                self::FreezeTestCases,
                self::DeleteFrozenTestCaseVersions,
            ],
            'Keywords' => [
                self::ViewKeywords,
                self::ManageKeywords,
                self::AssignKeywords,
                self::AssignKeywordsToExecutedTestCases,
            ],
            'Requirements' => [
                self::ViewRequirements,
                self::ManageRequirements,
                self::UnfreezeRequirements,
                self::MonitorRequirements,
                self::ManageRequirementCoverage,
            ],
            'Custom fields' => [
                self::ViewCustomFields,
                self::ManageCustomFields,
                self::AssignCustomFields,
            ],
            'Platforms' => [
                self::ViewPlatforms,
                self::ManagePlatforms,
                self::ManagePlanPlatforms,
            ],
            'Inventory' => [
                self::ViewInventory,
                self::ManageInventory,
            ],
            'Trackers and sources' => [
                self::ViewIssueTrackers,
                self::ManageIssueTrackers,
                self::ViewCodeTrackers,
                self::ManageCodeTrackers,
                self::ViewRequirementSources,
                self::ManageRequirementSources,
            ],
            'Test planning' => [
                self::CreateTestPlans,
                self::PlanTestCases,
                self::ManageBuilds,
                self::ManageMilestones,
                self::UpdateLinkedTestCaseVersions,
                self::SetTestCaseUrgency,
                self::UnlinkExecutedTestCases,
            ],
            'Role assignment' => [
                self::AssignProjectRoles,
                self::AssignPlanRoles,
            ],
            'Test execution' => [
                self::ExecuteTests,
                self::ViewExecutions,
                self::ExecuteOnlyAssignedTestCases,
                self::AssignTesters,
                self::EditExecutionNotes,
                self::DeleteExecutions,
                self::EditExecutedTestCases,
                self::DeleteExecutedTestCases,
            ],
            'Metrics' => [
                self::ViewPlanMetrics,
                self::ViewProjectMetrics,
            ],
        ];
    }

    /**
     * The abilities that only a user's global role may grant.
     *
     * The two custom field abilities are here because a custom field is defined
     * application-wide: one definition is shared by every project that enables
     * it, so editing it — or deleting it, which takes every project's answers
     * with it — reaches far outside whichever project the acting role was
     * granted in. `AssignCustomFields` is deliberately *not* here, because
     * choosing which of those definitions apply to one project, and whether an
     * answer is mandatory there, is exactly a per-project decision.
     *
     * Legacy reached the same place by a different route: `cfield_view` and
     * `cfield_management` were product-level rights seeded to the admin role
     * only, and no project role could hold them.
     *
     * @return array<int, self>
     */
    public static function system(): array
    {
        return [
            self::ManageUsers,
            self::ManageRoles,
            self::AssignGlobalRoles,
            self::ManageSystemSettings,
            self::ManageTestProjects,
            self::ManagePlugins,
            self::ViewEventLog,
            self::ManageEventLog,
            self::ViewCustomFields,
            self::ManageCustomFields,
        ];
    }
}
