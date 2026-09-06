<?php

namespace App\Actions\TestSpecification;

use App\Models\TestCase;
use App\Models\TestCaseStep;
use App\Models\TestProject;

/**
 * Expands `[ghost]...[/ghost]` includes at read time.
 *
 * The markup stays stored as written. Opening a case or running it substitutes
 * the referenced case's summary, preconditions or step so testers see the
 * live wording without a stored copy that can drift.
 */
final class ExpandGhostMarkup
{
    public function __invoke(?string $markup, TestProject $project, string $field = 'actions'): ?string
    {
        if ($markup === null || $markup === '') {
            return $markup;
        }

        return preg_replace_callback(
            '/\[ghost\](.*?)\[\/ghost\]/s',
            function (array $match) use ($project, $field): string {
                $included = $this->resolve($match[1], $project, $field);

                return $included ?? $match[0];
            },
            $markup,
        );
    }

    private function resolve(string $body, TestProject $project, string $field): ?string
    {
        $fields = [];

        if (preg_match_all('/"([^"]+)":"([^"]*)"/', $body, $pairs, PREG_SET_ORDER) === false) {
            return null;
        }

        foreach ($pairs as $pair) {
            $fields[$pair[1]] = $pair[2];
        }

        $key = $fields['TestCase'] ?? null;

        if (! is_string($key) || $key === '') {
            return null;
        }

        $case = $this->findCase($key, $project);

        if ($case === null) {
            return null;
        }

        $version = $case->latestVersion;

        if (isset($fields['Version']) && $fields['Version'] !== '') {
            $named = $case->versions()->where('version', (int) $fields['Version'])->first();
            $version = $named ?? $version;
        }

        if ($version === null) {
            return null;
        }

        if (isset($fields['Preconditions'])) {
            return $version->preconditions;
        }

        if (isset($fields['Step']) && $fields['Step'] !== '') {
            $step = $version->steps()
                ->where('sort_order', (int) $fields['Step'])
                ->first();

            if (! $step instanceof TestCaseStep) {
                return null;
            }

            return $field === 'expected_results' ? $step->expected_results : $step->actions;
        }

        return $version->summary;
    }

    private function findCase(string $key, TestProject $project): ?TestCase
    {
        $prefix = $project->prefix.'-';

        if (! str_starts_with($key, $prefix)) {
            return null;
        }

        $externalId = (int) substr($key, strlen($prefix));

        return TestCase::query()
            ->where('test_project_id', $project->getKey())
            ->where('external_id', $externalId)
            ->with(['latestVersion.steps', 'versions'])
            ->first();
    }
}
