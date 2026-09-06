<?php

namespace App\Http\Requests\TestSpecification;

use App\Concerns\ValidatesCustomFieldValues;
use App\Enums\TestCaseExecutionType;
use App\Enums\TestCaseImportance;
use App\Enums\TestCaseStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

class TestCaseVersionUpdateRequest extends SpecificationFormRequest
{
    use ValidatesCustomFieldValues;

    /**
     * Get the validation rules that apply to the request.
     *
     * `status` accepts any case of the enum from any other: it is a free-form
     * label rather than a workflow (open question Q18), so there is no
     * transition table to validate against. Refusing to edit a frozen version
     * is UpdateTestCaseVersion's job.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'summary' => ['nullable', 'string'],
            'preconditions' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(TestCaseStatus::class)],
            'importance' => ['required', Rule::enum(TestCaseImportance::class)],
            'execution_type' => ['required', Rule::enum(TestCaseExecutionType::class)],
            'estimated_duration' => ['nullable', 'numeric', 'min:0', 'max:999999.99'],
            /*
             * Derived from the project's enabled fields rather than written
             * here, so a mandatory field is mandatory on save and not merely
             * marked with an asterisk. See `ValidatesCustomFieldValues`.
             */
            ...$this->customFieldRulesFor($this->routeTestCaseVersion()),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->normaliseCustomFieldInput();
    }

    /**
     * @return array{summary: string|null, preconditions: string|null, status: TestCaseStatus, importance: TestCaseImportance, execution_type: TestCaseExecutionType, estimated_duration: string|null}
     */
    public function versionAttributes(): array
    {
        return [
            'summary' => $this->optionalText('summary'),
            'preconditions' => $this->optionalText('preconditions'),
            'status' => $this->requiredEnum('status', TestCaseStatus::class),
            'importance' => $this->requiredEnum('importance', TestCaseImportance::class),
            'execution_type' => $this->requiredEnum('execution_type', TestCaseExecutionType::class),
            'estimated_duration' => $this->optionalText('estimated_duration'),
        ];
    }
}
