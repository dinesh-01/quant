<?php

namespace App\Http\Requests\Requirements;

use App\Models\RequirementVersion;
use App\Models\TestCaseVersion;
use Illuminate\Contracts\Validation\ValidationRule;

class LinkRequirementCoverageRequest extends RequirementsFormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'requirement_version_id' => ['sometimes', 'integer', 'exists:requirement_versions,id'],
            'test_case_version_id' => ['sometimes', 'integer', 'exists:test_case_versions,id'],
        ];
    }

    public function requirementVersion(): RequirementVersion
    {
        $fromRoute = $this->route('requirementVersion');

        if ($fromRoute instanceof RequirementVersion) {
            return $fromRoute;
        }

        return RequirementVersion::query()->findOrFail($this->integer('requirement_version_id'));
    }

    public function testCaseVersion(): TestCaseVersion
    {
        $fromRoute = $this->route('testCaseVersion');

        if ($fromRoute instanceof TestCaseVersion) {
            return $fromRoute;
        }

        return TestCaseVersion::query()->findOrFail($this->integer('test_case_version_id'));
    }
}
