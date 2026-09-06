<?php

namespace App\Concerns;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

trait TestSuiteValidationRules
{
    /**
     * Require a suite name to be unique among its siblings.
     *
     * There is deliberately no unique index behind this. `test_suites.parent_id`
     * is NULL for root suites and MySQL treats NULLs as distinct, so a
     * `UNIQUE (test_project_id, parent_id, name)` index would silently not
     * apply at the project root — the one place users would notice. Legacy had
     * no DDL constraint either and checked in PHP.
     */
    protected function siblingNameRule(int $projectId, ?int $parentId, ?int $ignoreSuiteId = null): Unique
    {
        $rule = Rule::unique('test_suites', 'name')->where('test_project_id', $projectId);

        $rule = $parentId === null
            ? $rule->whereNull('parent_id')
            : $rule->where('parent_id', $parentId);

        return $ignoreSuiteId === null ? $rule : $rule->ignore($ignoreSuiteId);
    }
}
