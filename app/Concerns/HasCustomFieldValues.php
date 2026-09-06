<?php

namespace App\Concerns;

use App\Models\CustomFieldSubject;
use App\Models\CustomFieldValue;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * The values relation for a model implementing `CustomFieldSubject`.
 *
 * Only the relation is shared. The entity kind and the project stay on each
 * model: those are what genuinely differ, and a defaulted guess at either would
 * mean answering the wrong field or asking the wrong project's role.
 *
 * @phpstan-require-implements CustomFieldSubject
 */
trait HasCustomFieldValues
{
    /**
     * @return MorphMany<CustomFieldValue, $this>
     */
    public function customFieldValues(): MorphMany
    {
        return $this->morphMany(CustomFieldValue::class, 'subject');
    }
}
