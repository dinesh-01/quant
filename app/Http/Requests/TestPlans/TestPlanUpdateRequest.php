<?php

namespace App\Http\Requests\TestPlans;

use App\Concerns\TestPlanValidationRules;
use App\Concerns\ValidatesCustomFieldValues;
use App\Models\TestPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class TestPlanUpdateRequest extends FormRequest
{
    use TestPlanValidationRules;
    use ValidatesCustomFieldValues;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $plan = $this->routeTestPlan();

        return [
            ...$this->testPlanRules($plan->test_project_id, $plan->getKey()),
            ...$this->customFieldRulesFor($plan),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->prepareCheckboxBooleans();
        $this->normaliseCustomFieldInput();
    }

    /**
     * @return array{name: string, description: string|null, is_active: bool, is_open: bool, is_public: bool}
     */
    public function planAttributes(): array
    {
        return $this->testPlanAttributes();
    }

    /**
     * The bound plan, narrowed for static analysis.
     */
    public function routeTestPlan(): TestPlan
    {
        $plan = $this->route('testPlan');

        abort_unless($plan instanceof TestPlan, 404);

        return $plan;
    }
}
