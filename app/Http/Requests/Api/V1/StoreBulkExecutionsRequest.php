<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\ExecutionStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBulkExecutionsRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'results' => ['required', 'array', 'min:1', 'max:100'],
            'results.*.test_plan_item_id' => ['required_without:results.*.full_external_id', 'nullable', 'integer'],
            'results.*.full_external_id' => ['required_without:results.*.test_plan_item_id', 'nullable', 'string', 'max:64'],
            'results.*.platform' => ['nullable', 'string', 'max:255'],
            'results.*.build_id' => ['required_without:results.*.build', 'nullable', 'integer'],
            'results.*.build' => ['required_without:results.*.build_id', 'nullable', 'string', 'max:255'],
            'results.*.status' => ['required', Rule::enum(ExecutionStatus::class)],
            'results.*.notes' => ['nullable', 'string'],
            'results.*.duration' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'results.*.complete' => ['boolean'],
            'results.*.steps' => ['nullable', 'array'],
            'results.*.steps.*.test_case_step_id' => ['required', 'integer', 'exists:test_case_steps,id'],
            'results.*.steps.*.status' => ['required', Rule::enum(ExecutionStatus::class)],
            'results.*.steps.*.notes' => ['nullable', 'string'],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function results(): array
    {
        $results = [];

        foreach ($this->validated('results') as $result) {
            if (is_array($result)) {
                $results[] = $result;
            }
        }

        return $results;
    }
}
