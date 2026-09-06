<?php

namespace App\Http\Requests\Platforms;

use App\Concerns\PlatformValidationRules;
use App\Models\TestProject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PlatformStoreRequest extends FormRequest
{
    use PlatformValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->platformRules($this->routeTestProject()->getKey());
    }

    /**
     * @return array{name: string, notes: string|null, enable_on_design: bool, enable_on_execution: bool, is_open: bool}
     */
    public function platform(): array
    {
        return $this->platformAttributes();
    }

    public function routeTestProject(): TestProject
    {
        $project = $this->route('testProject');

        abort_unless($project instanceof TestProject, 404);

        return $project;
    }
}
