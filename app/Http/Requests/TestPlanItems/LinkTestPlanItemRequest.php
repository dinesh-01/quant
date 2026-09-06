<?php

namespace App\Http\Requests\TestPlanItems;

use App\Enums\TestCaseUrgency;
use App\Models\Platform;
use App\Models\TestCaseVersion;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LinkTestPlanItemRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'test_case_version_id' => ['required', 'integer', 'exists:test_case_versions,id'],
            'platform_id' => ['nullable', 'integer', 'exists:platforms,id'],
            'urgency' => ['nullable', Rule::enum(TestCaseUrgency::class)],
        ];
    }

    public function version(): TestCaseVersion
    {
        return TestCaseVersion::query()->findOrFail($this->integer('test_case_version_id'));
    }

    public function platform(): ?Platform
    {
        $id = $this->input('platform_id');

        if ($id === null || $id === '') {
            return null;
        }

        return Platform::query()->findOrFail((int) $id);
    }

    public function urgency(): TestCaseUrgency
    {
        $value = $this->input('urgency');

        return is_string($value)
            ? TestCaseUrgency::from($value)
            : TestCaseUrgency::Medium;
    }
}
