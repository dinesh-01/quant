<?php

namespace App\Models;

use Database\Factories\RequirementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A requirement. Versions carry the wording; `doc_id` is the user-facing
 * identifier and is unique per project.
 *
 * @property int $id
 * @property int $test_project_id
 * @property int $requirement_spec_id
 * @property string $name
 * @property string $doc_id
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TestProject $testProject
 * @property-read RequirementSpec $requirementSpec
 * @property-read EloquentCollection<int, RequirementVersion> $versions
 * @property-read RequirementVersion|null $latestVersion
 */
#[Fillable(['name', 'doc_id', 'sort_order'])]
class Requirement extends Model
{
    /** @use HasFactory<RequirementFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<TestProject, $this>
     */
    public function testProject(): BelongsTo
    {
        return $this->belongsTo(TestProject::class);
    }

    /**
     * @return BelongsTo<RequirementSpec, $this>
     */
    public function requirementSpec(): BelongsTo
    {
        return $this->belongsTo(RequirementSpec::class);
    }

    /**
     * @return HasMany<RequirementVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(RequirementVersion::class)->orderBy('version');
    }

    /**
     * @return HasOne<RequirementVersion, $this>
     */
    public function latestVersion(): HasOne
    {
        return $this->hasOne(RequirementVersion::class)->ofMany('version', 'max');
    }

    /**
     * @return HasMany<RequirementMonitor, $this>
     */
    public function monitors(): HasMany
    {
        return $this->hasMany(RequirementMonitor::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }
}
