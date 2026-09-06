<?php

namespace App\Models;

use Database\Factories\ReportBaselineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A snapshot of one plan-status report at a point in time.
 *
 * Counts and item rows are denormalized so unlinking a case later cannot
 * erase what the snapshot recorded.
 *
 * @property int $id
 * @property int $test_plan_id
 * @property int|null $build_id
 * @property int|null $user_id
 * @property string $name
 * @property int $total
 * @property array{passed: int, failed: int, blocked: int, not_run: int} $counts
 * @property list<array{id: int, full_external_id: string, name: string, platform: string|null, status: string}> $items
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TestPlan $testPlan
 * @property-read Build|null $build
 * @property-read User|null $user
 */
#[Fillable(['name', 'total', 'counts', 'items'])]
class ReportBaseline extends Model
{
    /** @use HasFactory<ReportBaselineFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<TestPlan, $this>
     */
    public function testPlan(): BelongsTo
    {
        return $this->belongsTo(TestPlan::class);
    }

    /**
     * @return BelongsTo<Build, $this>
     */
    public function build(): BelongsTo
    {
        return $this->belongsTo(Build::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'total' => 'integer',
            'counts' => 'array',
            'items' => 'array',
        ];
    }
}
