<?php

namespace App\Http\Requests\IssueTrackers;

use App\Enums\IssueTrackerType;
use App\Models\TestProject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IssueTrackerUpdateRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'is_enabled' => $this->boolean('is_enabled'),
        ]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $project = $this->routeTestProject();
        $hasToken = filled($project->issueTracker?->setting('api_token'));

        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(IssueTrackerType::class)],
            'base_url' => ['required', 'string', 'url:http,https', 'max:255'],
            'project_key' => ['required', 'string', 'max:32'],
            'issue_type' => ['required', 'string', 'max:64'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'api_token' => [$hasToken ? 'nullable' : 'required', 'string', 'max:255'],
            'proxy' => ['nullable', 'string', 'url:http,https', 'max:255'],
            'is_enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array{name: string, type: IssueTrackerType, base_url: string, is_enabled: bool, project_key: string, issue_type: string, email: string, api_token: string|null, proxy: string|null}
     */
    public function tracker(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'type' => IssueTrackerType::from($validated['type']),
            'base_url' => rtrim($validated['base_url'], '/'),
            'is_enabled' => $this->boolean('is_enabled'),
            'project_key' => $validated['project_key'],
            'issue_type' => $validated['issue_type'],
            'email' => $validated['email'],
            'api_token' => $validated['api_token'] ?? null,
            'proxy' => $validated['proxy'] ?? null,
        ];
    }

    public function routeTestProject(): TestProject
    {
        $project = $this->route('testProject');

        abort_unless($project instanceof TestProject, 404);

        return $project;
    }
}
