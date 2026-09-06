<?php

namespace App\Http\Requests\TestSpecification;

use App\Models\TestCaseVersion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LinkAutomationScriptRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'project_key' => ['required', 'string', 'max:100'],
            'repository' => ['required', 'string', 'max:191'],
            'path' => ['required', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:191'],
            'commit' => ['nullable', 'string', 'max:64'],
        ];
    }

    /**
     * @return array{project_key: string, repository: string, path: string, branch: string|null, commit: string|null}
     */
    public function script(): array
    {
        $validated = $this->validated();

        return [
            'project_key' => $validated['project_key'],
            'repository' => $validated['repository'],
            'path' => $validated['path'],
            'branch' => $validated['branch'] ?? null,
            'commit' => $validated['commit'] ?? null,
        ];
    }

    public function routeTestCaseVersion(): TestCaseVersion
    {
        $version = $this->route('testCaseVersion');

        abort_unless($version instanceof TestCaseVersion, 404);

        return $version;
    }
}
