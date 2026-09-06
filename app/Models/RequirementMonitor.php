<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A person watching one requirement for wording changes.
 *
 * @property int $id
 * @property int $requirement_id
 * @property int $user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Requirement $requirement
 * @property-read User $user
 */
class RequirementMonitor extends Model
{
    /**
     * @return BelongsTo<Requirement, $this>
     */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
