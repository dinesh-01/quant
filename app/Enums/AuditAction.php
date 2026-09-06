<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * The acts the audit trail records.
 *
 * An enum rather than free strings for the same reason abilities are one: the
 * set has to be enumerable so the event log can offer a filter, and a typo in
 * a string would produce a record that no filter ever matches and nobody ever
 * notices.
 *
 * Values are `subject.verb` in the past tense, so they read as a history.
 */
enum AuditAction: string
{
    case UserCreated = 'user.created';
    case UserUpdated = 'user.updated';
    case UserPasswordSet = 'user.password_set';
    case UserPasswordResetLinkSent = 'user.password_reset_link_sent';

    case RoleCreated = 'role.created';
    case RoleUpdated = 'role.updated';
    case RoleDeleted = 'role.deleted';

    case ScopeRoleAssigned = 'scope_role.assigned';
    case ScopeRoleRevoked = 'scope_role.revoked';

    case AdministratorCreated = 'administrator.created';
    case AdministratorPromoted = 'administrator.promoted';

    case TestProjectCreated = 'test_project.created';
    case TestProjectUpdated = 'test_project.updated';
    case TestProjectDeleted = 'test_project.deleted';

    case TestPlanCreated = 'test_plan.created';
    case TestPlanUpdated = 'test_plan.updated';
    case TestPlanDeleted = 'test_plan.deleted';

    case TestSuiteCreated = 'test_suite.created';
    case TestSuiteUpdated = 'test_suite.updated';
    case TestSuiteDeleted = 'test_suite.deleted';
    case TestSuiteMoved = 'test_suite.moved';
    case TestSuiteCopied = 'test_suite.copied';

    case TestCaseCreated = 'test_case.created';
    case TestCaseRenamed = 'test_case.renamed';
    case TestCaseDeleted = 'test_case.deleted';
    case TestCaseMoved = 'test_case.moved';
    case TestCaseCopied = 'test_case.copied';
    case TestCaseRelationCreated = 'test_case.relation_created';
    case TestCaseRelationDeleted = 'test_case.relation_deleted';
    case TestSuiteExported = 'test_suite.exported';
    case TestSuiteImported = 'test_suite.imported';

    case TestCaseVersionCreated = 'test_case_version.created';
    case TestCaseVersionUpdated = 'test_case_version.updated';
    case TestCaseVersionDeleted = 'test_case_version.deleted';
    case TestCaseVersionFrozen = 'test_case_version.frozen';
    case TestCaseVersionUnfrozen = 'test_case_version.unfrozen';

    case TestCaseStepCreated = 'test_case_step.created';
    case TestCaseStepUpdated = 'test_case_step.updated';
    case TestCaseStepDeleted = 'test_case_step.deleted';

    /*
     * There is no case for attachments removed with their parent. That is not
     * a separate act: the parent's own deletion record carries how many files
     * went with it, the same way it carries how many suites and cases did.
     */
    case AttachmentUploaded = 'attachment.uploaded';
    case AttachmentDeleted = 'attachment.deleted';

    case KeywordCreated = 'keyword.created';
    case KeywordUpdated = 'keyword.updated';
    case KeywordDeleted = 'keyword.deleted';

    /**
     * The keywords on one case were changed from the case's own screen.
     */
    case TestCaseKeywordsChanged = 'test_case.keywords_changed';

    /**
     * A bulk assignment across a suite, recorded once against the suite rather
     * than once per case it touched — one button press is one act, the same
     * judgement a subtree copy is recorded under. The count of cases and the
     * keywords involved go in the properties, which is what a reader asking
     * "why does everything under here say smoke?" actually needs.
     */
    case TestSuiteKeywordsApplied = 'test_suite.keywords_applied';

    case CodeTrackerSaved = 'code_tracker.saved';
    case IssueTrackerSaved = 'issue_tracker.saved';
    case AutomationScriptLinked = 'automation_script.linked';
    case AutomationScriptUnlinked = 'automation_script.unlinked';

    case PlatformCreated = 'platform.created';
    case PlatformUpdated = 'platform.updated';
    case PlatformDeleted = 'platform.deleted';

    /**
     * Which platforms a plan executes against changed.
     *
     * Recorded against the plan. Items sitting on a removed platform are
     * deleted with it, and that count goes in the properties.
     */
    case PlanPlatformsChanged = 'test_plan.platforms_changed';

    /**
     * Recorded against the test case: a version has no name of its own.
     */
    case TestCaseVersionPlatformsChanged = 'test_case_version.platforms_changed';

    case BuildCreated = 'build.created';
    case BuildUpdated = 'build.updated';
    case BuildDeleted = 'build.deleted';

    case TestPlanItemLinked = 'test_plan_item.linked';
    case TestPlanItemUnlinked = 'test_plan_item.unlinked';
    case TestPlanItemUrgencySet = 'test_plan_item.urgency_set';
    case TestPlanItemVersionUpdated = 'test_plan_item.version_updated';

    case RequirementSpecCreated = 'requirement_spec.created';
    case RequirementSpecUpdated = 'requirement_spec.updated';
    case RequirementSpecDeleted = 'requirement_spec.deleted';
    case RequirementSpecMoved = 'requirement_spec.moved';

    case RequirementCreated = 'requirement.created';
    case RequirementUpdated = 'requirement.updated';
    case RequirementDeleted = 'requirement.deleted';
    case RequirementMoved = 'requirement.moved';

    case RequirementVersionCreated = 'requirement_version.created';
    case RequirementVersionUpdated = 'requirement_version.updated';
    case RequirementVersionFrozen = 'requirement_version.frozen';
    case RequirementVersionUnfrozen = 'requirement_version.unfrozen';

    case RequirementCoverageLinked = 'requirement_coverage.linked';
    case RequirementCoverageUnlinked = 'requirement_coverage.unlinked';
    case RequirementWatched = 'requirement.watched';
    case RequirementUnwatched = 'requirement.unwatched';
    case RequirementSpecExported = 'requirement_spec.exported';
    case RequirementSpecImported = 'requirement_spec.imported';

    case MilestoneCreated = 'milestone.created';
    case MilestoneUpdated = 'milestone.updated';
    case MilestoneDeleted = 'milestone.deleted';

    case ExecutionSaved = 'execution.saved';
    case ExecutionCompleted = 'execution.completed';
    case ExecutionDeleted = 'execution.deleted';
    case ExecutionNotesUpdated = 'execution.notes_updated';
    case ExecutionIssueLinked = 'execution.issue_linked';
    case ExecutionIssueCreated = 'execution.issue_created';
    case ExecutionIssueUnlinked = 'execution.issue_unlinked';

    case TesterAssigned = 'tester.assigned';
    case TesterUnassigned = 'tester.unassigned';
    case TesterAssignmentUpdated = 'tester_assignment.updated';
    case TesterAssignmentsCopied = 'tester_assignment.copied';

    /*
     * Definitions are application-wide, so these three are recorded for the
     * same reason role changes are: one edit reaches every project that has the
     * field enabled, and a delete takes every project's answers with it.
     *
     * Filling in a value is not recorded. That is ordinary content editing —
     * no more auditable than typing in a case's summary, which is also not
     * recorded — and a record per answer would bury the acts above.
     */
    case CustomFieldCreated = 'custom_field.created';
    case CustomFieldUpdated = 'custom_field.updated';
    case CustomFieldDeleted = 'custom_field.deleted';

    /**
     * Which fields a project uses, or how one behaves there, was changed.
     *
     * Recorded against the project rather than the field, because that is the
     * scope the act belongs to and the question a reader asks: not "where has
     * this field been switched on" but "why did this project's cases grow a
     * mandatory field".
     */
    case ProjectCustomFieldsChanged = 'test_project.custom_fields_changed';

    /**
     * Recorded after the rows are removed, so the one record of the removal is
     * not among the rows it removed.
     */
    case EventLogPruned = 'event_log.pruned';

    /**
     * Abandoned drafts discarded by the scheduler. One row for the run, not
     * one per draft — that volume would drown the trail.
     */
    case ExecutionDraftsPruned = 'execution.drafts_pruned';

    case ReportBaselineSaved = 'report_baseline.saved';

    /**
     * A human readable name, derived so it cannot drift from the case.
     */
    public function label(): string
    {
        return Str::ucfirst(Str::lower(Str::headline($this->name)));
    }
}
