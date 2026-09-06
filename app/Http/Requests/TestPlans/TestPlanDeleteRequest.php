<?php

namespace App\Http\Requests\TestPlans;

use App\Models\TestPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Guards plan deletion behind typing the plan's name, for the reasons set out
 * on DeleteTestPlan.
 */
class TestPlanDeleteRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'confirm_name' => ['required', 'string', Rule::in([$this->routeTestPlan()->name])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'confirm_name.in' => 'Type the plan name exactly to confirm deletion.',
        ];
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
