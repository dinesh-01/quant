<?php

namespace App\Http\Requests\TestPlans;

use App\Concerns\TestPlanValidationRules;
use App\Models\TestProject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TestPlanStoreRequest extends FormRequest
{
    use TestPlanValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->testPlanRules($this->routeTestProject()->getKey());
    }

    /**
     * @return array{name: string, description: string|null, is_active: bool, is_open: bool, is_public: bool}
     */
    public function planAttributes(): array
    {
        return $this->testPlanAttributes();
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
