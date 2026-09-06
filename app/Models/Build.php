<?php

namespace App\Models;

use Database\Factories\BuildFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A named snapshot of the software a plan is executed against.
 *
 * `is_active` is listing; `is_open` is whether results may still be recorded.
 * The two are different questions, matching the split already on test plans.
 * Phase 6 enforces the execution side.
 *
 * @property int $id
 * @property int $test_plan_id
 * @property string $name
 * @property string|null $notes
 * @property bool $is_active
 * @property bool $is_open
 * @property Carbon|null $release_date
 * @property int|null $author_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TestPlan $testPlan
 * @property-read User|null $author
 * @property-read Collection<int, TesterAssignment> $testerAssignments
 */
#[Fillable(['name', 'notes', 'is_active', 'is_open', 'release_date'])]
class Build extends Model
{
    /** @use HasFactory<BuildFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<TestPlan, $this>
     */
    public function testPlan(): BelongsTo
    {
        return $this->belongsTo(TestPlan::class);
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
     * @param  Builder<$this>  $query
     */
    #[Scope]
    protected function forPlan(Builder $query, TestPlan $plan): void
    {
        $query->where('test_plan_id', $plan->getKey());
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_open' => 'boolean',
            'release_date' => 'date',
        ];
    }
}
