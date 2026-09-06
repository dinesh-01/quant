<?php

namespace App\Models;

use Database\Factories\AuditEventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One recorded act, for the event log.
 *
 * Immutable by intent: rows are inserted and read, never changed, which is why
 * the table has no `updated_at` and this model turns Eloquent's second
 * timestamp off.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property array<string, mixed>|null $properties
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property-read User|null $user
 */
#[Fillable(['action', 'properties', 'ip_address'])]
class AuditEvent extends Model
{
    /** @use HasFactory<AuditEventFactory> */
    use HasFactory;

    /**
     * @var string|null
     */
    public const UPDATED_AT = null;

    /**
     * The account that did it, absent when a console command did.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * What was acted on.
     *
     * A morph rather than a column per type because the trail has to cover
     * every model without a migration each time one is added. The subject may
     * be gone — a deleted role, say — so readers must cope with null, which is
     * the reason `properties` records a name at the time rather than relying
     * on the relation resolving later.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
