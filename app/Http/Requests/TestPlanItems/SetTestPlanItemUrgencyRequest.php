<?php

namespace App\Http\Requests\TestPlanItems;

use App\Enums\TestCaseUrgency;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetTestPlanItemUrgencyRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'urgency' => ['required', Rule::enum(TestCaseUrgency::class)],
        ];
    }

    public function urgency(): TestCaseUrgency
    {
        return TestCaseUrgency::from((string) $this->input('urgency'));
    }
}
