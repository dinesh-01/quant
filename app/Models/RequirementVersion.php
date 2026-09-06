<?php

namespace App\Models;

use App\Casts\SanitizedHtml;
use App\Enums\RequirementStatus;
use App\Enums\RequirementType;
use Database\Factories\RequirementVersionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One wording of a requirement. Coverage hangs here so a new version does
 * not inherit or steal the previous version's links.
 *
 * @property int $id
 * @property int $requirement_id
 * @property int $version
 * @property string|null $scope
 * @property RequirementStatus $status
 * @property RequirementType $type
 * @property int $expected_coverage
 * @property bool $is_open
 * @property int|null $author_id
 * @property int|null $updater_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Requirement $requirement
 * @property-read User|null $author
 * @property-read User|null $updater
 * @property-read EloquentCollection<int, RequirementCoverage> $coverages
 */
#[Fillable(['scope', 'status', 'type', 'expected_coverage'])]
class RequirementVersion extends Model
{
    /** @use HasFactory<RequirementVersionFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'type' => 'feature',
        'expected_coverage' => 1,
        'is_open' => true,
    ];

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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updater_id');
    }

    /**
     * @return HasMany<RequirementCoverage, $this>
     */
    public function coverages(): HasMany
    {
        return $this->hasMany(RequirementCoverage::class);
    }

    public function isFrozen(): bool
    {
        return ! $this->is_open;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => SanitizedHtml::class,
            'version' => 'integer',
            'status' => RequirementStatus::class,
            'type' => RequirementType::class,
            'expected_coverage' => 'integer',
            'is_open' => 'boolean',
        ];
    }
}
