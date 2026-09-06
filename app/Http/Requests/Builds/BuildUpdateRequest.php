<?php

namespace App\Http\Requests\Builds;

use App\Concerns\BuildValidationRules;
use App\Models\Build;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BuildUpdateRequest extends FormRequest
{
    use BuildValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $build = $this->routeBuild();

        return $this->buildRules($build->test_plan_id, $build->getKey());
    }

    /**
     * @return array{name: string, notes: string|null, is_active: bool, is_open: bool, release_date: string|null}
     */
    public function build(): array
    {
        return $this->buildAttributes();
    }

    public function routeBuild(): Build
    {
        $build = $this->route('build');

        abort_unless($build instanceof Build, 404);

        return $build;
    }
}
