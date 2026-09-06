<?php

namespace App\Actions\TestSpecification;

use App\Models\TestCaseScriptLink;
use App\Models\TestCaseVersion;

/**
 * Copies automation-script links from one version onto another.
 *
 * Used when branching a version and when copying a case. Coverage is left
 * behind on purpose; scripts travel with the steps.
 */
final class CopyScriptLinks
{
    public function __invoke(TestCaseVersion $source, TestCaseVersion $target): int
    {
        $copied = 0;

        foreach ($source->scriptLinks as $link) {
            $copy = new TestCaseScriptLink;
            $copy->test_case_version_id = $target->getKey();
            $copy->project_key = $link->project_key;
            $copy->repository = $link->repository;
            $copy->path = $link->path;
            $copy->branch = $link->branch;
            $copy->commit = $link->commit;
            $copy->save();
            $copied++;
        }

        return $copied;
    }
}
