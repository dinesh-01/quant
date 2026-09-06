<?php

namespace App\Http\Requests\Reports;

use App\Models\TestPlan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportBaselineRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('report_baselines', 'name')->where(
                    'test_plan_id',
                    $this->routeTestPlan()->getKey(),
                ),
            ],
            'build' => ['nullable', 'integer'],
        ];
    }

    public function baselineName(): string
    {
        return (string) $this->input('name');
    }

    public function routeTestPlan(): TestPlan
    {
        $plan = $this->route('testPlan');

        abort_unless($plan instanceof TestPlan, 404);

        return $plan;
    }
}
