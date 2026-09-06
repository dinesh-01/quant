<?php

namespace App\Http\Requests\Keywords;

use App\Concerns\KeywordValidationRules;
use App\Models\TestProject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class KeywordStoreRequest extends FormRequest
{
    use KeywordValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->keywordRules($this->routeTestProject()->getKey());
    }

    /**
     * @return array{name: string, notes: string|null}
     */
    public function keyword(): array
    {
        return $this->keywordAttributes();
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
