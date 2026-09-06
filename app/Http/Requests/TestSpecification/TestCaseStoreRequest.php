<?php

namespace App\Http\Requests\TestSpecification;

use App\Enums\TestCaseExecutionType;
use App\Enums\TestCaseImportance;
use App\Enums\TestCaseStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class TestCaseStoreRequest extends SpecificationFormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * Case names are not required to be unique: two suites, or even one suite,
     * may hold cases with the same name, because the `PREFIX-N` identifier is
     * what distinguishes them. Legacy behaved the same way.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string'],
            'preconditions' => ['nullable', 'string'],
            'status' => ['nullable', Rule::enum(TestCaseStatus::class)],
            'importance' => ['nullable', Rule::enum(TestCaseImportance::class)],
            'execution_type' => ['nullable', Rule::enum(TestCaseExecutionType::class)],
            'estimated_duration' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }

    /**
     * The new case's name plus the content of the first version.
     *
     * The three enum fields are only included when the request sent them, so
     * that an omitted one falls through to the column default rather than
     * being pinned here in a second place.
     *
     * @return array{name: string, summary: string|null, preconditions: string|null, estimated_duration: string|null, status?: TestCaseStatus, importance?: TestCaseImportance, execution_type?: TestCaseExecutionType}
     */
    public function caseAttributes(): array
    {
        $attributes = [
            'name' => $this->requiredString('name'),
            'summary' => $this->optionalText('summary'),
            'preconditions' => $this->optionalText('preconditions'),
            'estimated_duration' => $this->optionalText('estimated_duration'),
        ];

        $status = $this->enum('status', TestCaseStatus::class);
        $importance = $this->enum('importance', TestCaseImportance::class);
        $executionType = $this->enum('execution_type', TestCaseExecutionType::class);

        if ($status !== null) {
            $attributes['status'] = $status;
        }

        if ($importance !== null) {
            $attributes['importance'] = $importance;
        }

        if ($executionType !== null) {
            $attributes['execution_type'] = $executionType;
        }

        return $attributes;
    }
}
