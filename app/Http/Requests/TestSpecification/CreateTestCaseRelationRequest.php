<?php

namespace App\Http\Requests\TestSpecification;

use App\Enums\TestCaseRelationType;
use App\Models\TestCase;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateTestCaseRelationRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'destination_id' => ['required', 'integer', 'exists:test_cases,id'],
            'type' => ['required', Rule::enum(TestCaseRelationType::class)],
        ];
    }

    public function destination(): TestCase
    {
        return TestCase::query()->findOrFail($this->integer('destination_id'));
    }

    public function relationType(): TestCaseRelationType
    {
        return TestCaseRelationType::from((string) $this->input('type'));
    }
}
