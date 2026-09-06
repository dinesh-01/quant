<?php

namespace App\Models;

use App\Enums\ExecutionStatus;
use Database\Factories\ExecutionStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The result of one step inside a run.
 *
 * @property int $id
 * @property int $execution_id
 * @property int $test_case_step_id
 * @property int $sort_order
 * @property ExecutionStatus $status
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Execution $execution
 * @property-read TestCaseStep $testCaseStep
 */
#[Fillable(['status', 'notes'])]
class ExecutionStep extends Model
{
    /** @use HasFactory<ExecutionStepFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Execution, $this>
     */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(Execution::class);
    }

    /**
     * @return BelongsTo<TestCaseStep, $this>
     */
    public function testCaseStep(): BelongsTo
    {
        return $this->belongsTo(TestCaseStep::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'status' => ExecutionStatus::class,
        ];
    }
}
