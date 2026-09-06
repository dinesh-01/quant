<?php

namespace App\Http\Requests\Requirements;

use App\Models\Requirement;
use App\Models\RequirementSpec;
use App\Models\RequirementVersion;
use App\Models\TestProject;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared route accessors. Authorization lives in the actions.
 */
abstract class RequirementsFormRequest extends FormRequest
{
    protected function routeTestProject(): TestProject
    {
        $project = $this->route('testProject');

        abort_unless($project instanceof TestProject, 404);

        return $project;
    }

    protected function routeRequirementSpec(): RequirementSpec
    {
        $spec = $this->route('requirementSpec');

        abort_unless($spec instanceof RequirementSpec, 404);

        return $spec;
    }

    protected function routeRequirement(): Requirement
    {
        $requirement = $this->route('requirement');

        abort_unless($requirement instanceof Requirement, 404);

        return $requirement;
    }

    protected function routeRequirementVersion(): RequirementVersion
    {
        $version = $this->route('requirementVersion');

        abort_unless($version instanceof RequirementVersion, 404);

        return $version;
    }

    protected function optionalId(string $key): ?int
    {
        $value = $this->input($key);

        return is_numeric($value) ? (int) $value : null;
    }

    protected function optionalText(string $key): ?string
    {
        $value = $this->input($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @template TEnum of \BackedEnum
     *
     * @param  class-string<TEnum>  $enum
     * @return TEnum
     */
    protected function requiredEnum(string $key, string $enum): \BackedEnum
    {
        $value = $this->enum($key, $enum);

        abort_unless($value instanceof $enum, 422);

        return $value;
    }

    protected function requiredString(string $key): string
    {
        $value = $this->input($key);

        abort_unless(is_string($value), 422);

        return $value;
    }
}
