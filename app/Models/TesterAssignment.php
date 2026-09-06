<?php

namespace App\Models;

use App\Enums\TesterAssignmentStatus;
use Database\Factories\TesterAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A tester asked to run one planned case on one build.
 *
 * @property int $id
 * @property int $test_plan_item_id
 * @property int $build_id
 * @property int $user_id
 * @property int|null $assigner_id
 * @property TesterAssignmentStatus $status
 * @property Carbon|null $deadline_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TestPlanItem $testPlanItem
 * @property-read Build $build
 * @property-read User $user
 * @property-read User|null $assigner
 */
#[Fillable(['status', 'deadline_at'])]
class TesterAssignment extends Model
{
    /** @use HasFactory<TesterAssignmentFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'open',
    ];

    /**
     * @return BelongsTo<TestPlanItem, $this>
     */
    public function testPlanItem(): BelongsTo
    {
        return $this->belongsTo(TestPlanItem::class);
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
     * @return BelongsTo<User, $this>
     */
    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigner_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TesterAssignmentStatus::class,
            'deadline_at' => 'datetime',
        ];
    }
}
