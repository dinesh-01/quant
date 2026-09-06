<?php

namespace App\Http\Requests\TestProjects;

use App\Models\TestProject;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Guards the project cascade behind typing the project's name.
 *
 * Deleting a project removes every suite, case, version, step and plan under
 * it, with no undo and nothing else in the application able to restore it. A
 * click-through confirmation is too easy to dismiss for a loss that large, so
 * the name has to be typed. Legacy asked for a single confirmation click.
 */
class TestProjectDeleteRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'confirm_name' => ['required', 'string', Rule::in([$this->routeTestProject()->name])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirm_name.in' => 'Type the project name exactly to confirm deletion.',
        ];
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
