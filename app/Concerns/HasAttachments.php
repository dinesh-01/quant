<?php

namespace App\Concerns;

use App\Models\Attachable;
use App\Models\Attachment;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * The attachments relation for a model implementing `Attachable`.
 *
 * Only the relation is shared. The two abilities and the scope stay on each
 * model, because they are the one part that genuinely differs per type and the
 * part that must not be defaulted: a wrong guess here is a hole, not a bug.
 *
 * @phpstan-require-implements Attachable
 */
trait HasAttachments
{
    /**
     * Newest first, matching legacy's `ORDER BY date_added DESC`. Ordering is
     * on the relation so no caller has to remember it.
     *
     * @return MorphMany<Attachment, $this>
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable')
            ->latest('id');
    }
}
