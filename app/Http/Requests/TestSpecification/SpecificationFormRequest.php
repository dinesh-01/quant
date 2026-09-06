<?php

namespace App\Http\Requests\TestSpecification;

use App\Models\TestCase;
use App\Models\TestCaseVersion;
use App\Models\TestProject;
use App\Models\TestSuite;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared route accessors for the specification requests.
 *
 * Route model binding already guarantees these types, but the container returns
 * `mixed`, so each accessor narrows it once here instead of in every rule set.
 *
 * These requests deliberately have no `authorize()` method. Authorization for
 * this area lives inside the action, so that a controller cannot skip it and so
 * that the denied path is testable without a route — see `.ai/rules/actions.md`.
 */
abstract class SpecificationFormRequest extends FormRequest
{
    protected function routeTestProject(): TestProject
    {
        $project = $this->route('testProject');

        abort_unless($project instanceof TestProject, 404);

        return $project;
    }

    protected function routeTestSuite(): TestSuite
    {
        $suite = $this->route('testSuite');

        abort_unless($suite instanceof TestSuite, 404);

        return $suite;
    }

    protected function routeTestCase(): TestCase
    {
        $case = $this->route('testCase');

        abort_unless($case instanceof TestCase, 404);

        return $case;
    }

    protected function routeTestCaseVersion(): TestCaseVersion
    {
        $version = $this->route('testCaseVersion');

        abort_unless($version instanceof TestCaseVersion, 404);

        return $version;
    }

    /**
     * Read an optional id from the input, treating anything non-numeric —
     * including an absent key and an explicit null — as "no id".
     */
    protected function optionalId(string $key): ?int
    {
        $value = $this->input($key);

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * Read an optional free-text field.
     *
     * An empty string becomes null, because a cleared textarea and an untouched
     * one mean the same thing to a user and the columns are nullable. Without
     * this the same "empty" description is stored two different ways depending
     * on which form it came from.
     */
    protected function optionalText(string $key): ?string
    {
        $value = $this->input($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Read a required enum field, narrowed past the nullable return of
     * `enum()`. Validation has already rejected an absent or unknown value, so
     * the abort is unreachable in practice.
     *
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

    /**
     * Read a required string field, narrowed the same way.
     */
    protected function requiredString(string $key): string
    {
        $value = $this->input($key);

        abort_unless(is_string($value), 422);

        return $value;
    }
}
