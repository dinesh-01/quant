<?php

namespace App\Models;

use App\Enums\TestCaseUrgency;
use Database\Factories\TestPlanItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A test case version pinned onto a plan, optionally on one platform.
 *
 * The unique slot is (plan, version, platform). A null platform is one slot,
 * not many — that is the row a plan without platforms uses. Urgency is on
 * this row so the same version can be routine in one plan and urgent in
 * another. Importance stays on the version.
 *
 * @property int $id
 * @property int $test_plan_id
 * @property int $test_case_version_id
 * @property int|null $platform_id
 * @property int $sort_order
 * @property TestCaseUrgency $urgency
 * @property int|null $author_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TestPlan $testPlan
 * @property-read TestCaseVersion $testCaseVersion
 * @property-read Platform|null $platform
 * @property-read User|null $author
 * @property-read Collection<int, TesterAssignment> $testerAssignments
 */
#[Fillable(['platform_id', 'sort_order', 'urgency'])]
class TestPlanItem extends Model
{
    /** @use HasFactory<TestPlanItemFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'sort_order' => 0,
        'urgency' => 'medium',
    ];

    /**
     * @return BelongsTo<TestPlan, $this>
     */
    public function testPlan(): BelongsTo
    {
        return $this->belongsTo(TestPlan::class);
    }

    /**
     * @return BelongsTo<TestCaseVersion, $this>
     */
    public function testCaseVersion(): BelongsTo
    {
        return $this->belongsTo(TestCaseVersion::class);
    }

    /**
     * @return BelongsTo<Platform, $this>
     */
    public function platform(): BelongsTo
    {
        return $this->belongsTo(Platform::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return HasMany<TesterAssignment, $this>
     */
    public function testerAssignments(): HasMany
    {
        return $this->hasMany(TesterAssignment::class);
    }

    /**
     * @return HasMany<Execution, $this>
     */
    public function executions(): HasMany
    {
        return $this->hasMany(Execution::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'urgency' => TestCaseUrgency::class,
        ];
    }
}
