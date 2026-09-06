<?php

namespace App\Models;

use Database\Factories\MilestoneFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A dated completion target on a plan, split by case priority.
 *
 * @property int $id
 * @property int $test_plan_id
 * @property string $name
 * @property Carbon $target_date
 * @property Carbon|null $start_date
 * @property int $high_percent
 * @property int $medium_percent
 * @property int $low_percent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TestPlan $testPlan
 */
#[Fillable(['name', 'target_date', 'start_date', 'high_percent', 'medium_percent', 'low_percent'])]
class Milestone extends Model
{
    /** @use HasFactory<MilestoneFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<TestPlan, $this>
     */
    public function testPlan(): BelongsTo
    {
        return $this->belongsTo(TestPlan::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_date' => 'date',
            'start_date' => 'date',
            'high_percent' => 'integer',
            'medium_percent' => 'integer',
            'low_percent' => 'integer',
        ];
    }
}
