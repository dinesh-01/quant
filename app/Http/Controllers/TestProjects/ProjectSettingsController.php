<?php

namespace App\Http\Controllers\TestProjects;

use App\Actions\CustomFields\ResolveCustomFields;
use App\Concerns\PresentsAttachments;
use App\Enums\Ability;
use App\Enums\CodeTrackerType;
use App\Enums\IssueTrackerType;
use App\Http\Controllers\Controller;
use App\Models\CustomField;
use App\Models\TestProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * Project Settings hub that matches the mockup tabs.
 */
class ProjectSettingsController extends Controller
{
    use PresentsAttachments;

    public function __construct(private readonly ResolveCustomFields $fields) {}

    public function index(Request $request, TestProject $testProject): Response
    {
        $user = $this->actingUser($request);
        $gate = Gate::forUser($user);

        abort_unless(
            $gate->allows(Ability::ViewTestCases->value, $testProject)
            || $gate->allows(Ability::ManageTestProjects->value)
            || $gate->allows(Ability::AssignCustomFields->value, $testProject)
            || $gate->allows(Ability::ViewCodeTrackers->value, $testProject)
            || $gate->allows(Ability::ViewIssueTrackers->value, $testProject),
            403,
        );

        $assigned = $testProject->customFields()->get();
        $answerCounts = $this->fields->answerCountsIn($testProject, $assigned);
        $available = CustomField::query()
            ->whereNotIn('id', $assigned->modelKeys())
            ->alphabetically()
            ->get();

        $codeTracker = $testProject->codeTracker;
        $issueTracker = $testProject->issueTracker;

        $tokens = $user->tokens()
            ->orderByDesc('id')
            ->get()
            ->map(fn (PersonalAccessToken $token): array => [
                'id' => $token->id,
                'name' => $token->name,
                'last_used_at' => $token->last_used_at?->toIso8601String(),
                'created_at' => $token->created_at?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('project-settings/show', [
            'project' => [
                'id' => $testProject->id,
                'name' => $testProject->name,
                'prefix' => $testProject->prefix,
                'description' => $testProject->description,
                'is_active' => $testProject->is_active,
                'is_public' => $testProject->is_public,
                'prefix_locked' => $testProject->test_case_counter > 0,
                'test_cases_count' => $testProject->testCases()->count(),
                'attachments' => $this->attachmentProps($testProject),
            ],
            'attachmentRules' => $this->attachmentRules(),
            'assignedFields' => array_values($assigned->map(fn (CustomField $field): array => [
                'id' => $field->id,
                'name' => $field->name,
                'label' => $field->label,
                'type_label' => $field->type->label(),
                'entity_label' => $field->entity_type->label(),
                'is_active' => $field->isActiveIn(),
                'answers_count' => $answerCounts[$field->id] ?? 0,
            ])->all()),
            'availableFields' => array_values($available->map(fn (CustomField $field): array => [
                'id' => $field->id,
                'name' => $field->name,
                'label' => $field->label,
                'type_label' => $field->type->label(),
                'entity_label' => $field->entity_type->label(),
            ])->all()),
            'tokens' => $tokens,
            'plainTextToken' => $request->session()->pull('plainTextToken'),
            'codeTracker' => $codeTracker === null ? null : [
                'name' => $codeTracker->name,
                'type' => $codeTracker->type->value,
                'base_url' => $codeTracker->base_url,
                'project_key' => $codeTracker->project_key,
                'view_url_template' => $codeTracker->view_url_template,
                'is_enabled' => $codeTracker->is_enabled,
            ],
            'issueTracker' => $issueTracker === null ? null : [
                'name' => $issueTracker->name,
                'type' => $issueTracker->type->value,
                'base_url' => $issueTracker->base_url,
                'project_key' => $issueTracker->setting('project_key'),
                'issue_type' => $issueTracker->setting('issue_type', 'Bug'),
                'email' => $issueTracker->setting('email'),
                'has_api_token' => $issueTracker->hasApiToken(),
                'proxy' => $issueTracker->setting('proxy'),
                'is_enabled' => $issueTracker->is_enabled,
            ],
            'codeTrackerTypes' => array_map(
                fn (CodeTrackerType $type): array => [
                    'value' => $type->value,
                    'label' => $type->name === 'Github' ? 'GitHub' : ucfirst($type->value),
                ],
                CodeTrackerType::cases(),
            ),
            'issueTrackerTypes' => array_map(
                fn (IssueTrackerType $type): array => [
                    'value' => $type->value,
                    'label' => 'Jira',
                ],
                IssueTrackerType::cases(),
            ),
            'can' => [
                'manageProject' => $gate->allows(Ability::ManageTestProjects->value),
                'assignCustomFields' => $gate->allows(Ability::AssignCustomFields->value, $testProject),
                'manageCodeTrackers' => $gate->allows(Ability::ManageCodeTrackers->value, $testProject),
                'manageIssueTrackers' => $gate->allows(Ability::ManageIssueTrackers->value, $testProject),
                'prune' => $gate->allows(Ability::ManageEventLog->value),
            ],
        ]);
    }
}
