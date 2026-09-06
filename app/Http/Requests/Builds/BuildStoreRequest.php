<?php

namespace App\Http\Requests\Builds;

use App\Concerns\BuildValidationRules;
use App\Models\TestPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BuildStoreRequest extends FormRequest
{
    use BuildValidationRules;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->buildRules($this->routeTestPlan()->getKey());
    }

    /**
     * @return array{name: string, notes: string|null, is_active: bool, is_open: bool, release_date: string|null}
     */
    public function build(): array
    {
        return $this->buildAttributes();
    }

    public function routeTestPlan(): TestPlan
    {
        $plan = $this->route('testPlan');

        abort_unless($plan instanceof TestPlan, 404);

        return $plan;
    }
}
