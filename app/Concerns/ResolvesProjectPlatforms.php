<?php

namespace App\Concerns;

use App\Models\Platform;
use App\Models\TestProject;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\ValidationException;

/**
 * Turns platform ids from a form into this project's platforms, or refuses.
 *
 * Ids arrive as bare numbers: nothing about a `7` says which project it
 * belongs to. Resolving here keeps plan assignment, version assignment and
 * any later bulk action from inventing a second check that can drift.
 */
trait ResolvesProjectPlatforms
{
    /**
     * @param  list<int>  $platformIds
     * @return EloquentCollection<int, Platform>
     *
     * @throws ValidationException when any id is not one of this project's
     */
    protected function resolvePlatforms(TestProject $project, array $platformIds): EloquentCollection
    {
        $platformIds = array_values(array_unique($platformIds));

        $platforms = Platform::query()
            ->forProject($project)
            ->whereKey($platformIds)
            ->get();

        if ($platforms->count() !== count($platformIds)) {
            throw ValidationException::withMessages([
                'platforms' => 'One of those platforms does not belong to this test project.',
            ]);
        }

        return $platforms;
    }
}
