<?php

namespace App\Http\Requests\TestProjects;

use App\Concerns\TestProjectValidationRules;
use App\Models\TestProject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TestProjectUpdateRequest extends FormRequest
{
    use TestProjectValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->testProjectRules($this->routeTestProject()->getKey());
    }

    /**
     * @return array{name: string, prefix: string, description: string|null, is_active: bool, is_public: bool}
     */
    public function projectAttributes(): array
    {
        return $this->testProjectAttributes();
    }

    /**
     * The bound project, narrowed for static analysis.
     */
    public function routeTestProject(): TestProject
    {
        $project = $this->route('testProject');

        abort_unless($project instanceof TestProject, 404);

        return $project;
    }
}
