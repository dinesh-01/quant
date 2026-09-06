<?php

namespace App\Http\Requests\CodeTrackers;

use App\Enums\CodeTrackerType;
use App\Models\TestProject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CodeTrackerUpdateRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::enum(CodeTrackerType::class)],
            'base_url' => ['required', 'string', 'url:http,https', 'max:255'],
            'project_key' => ['nullable', 'string', 'max:100'],
            'view_url_template' => ['nullable', 'string', 'max:255'],
            'is_enabled' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array{name: string, type: CodeTrackerType, base_url: string, project_key: string|null, view_url_template: string|null, is_enabled: bool}
     */
    public function tracker(): array
    {
        $validated = $this->validated();

        return [
            'name' => $validated['name'],
            'type' => CodeTrackerType::from($validated['type']),
            'base_url' => rtrim($validated['base_url'], '/'),
            'project_key' => $validated['project_key'] ?? null,
            'view_url_template' => $validated['view_url_template'] ?? null,
            'is_enabled' => $this->boolean('is_enabled'),
        ];
    }

    public function routeTestProject(): TestProject
    {
        $project = $this->route('testProject');

        abort_unless($project instanceof TestProject, 404);

        return $project;
    }
}
