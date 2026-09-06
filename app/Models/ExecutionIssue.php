<?php

namespace App\Models;

use Database\Factories\ExecutionIssueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An external issue recorded against a run.
 *
 * Testers can type an id, or create one in the project's tracker from a
 * failed or blocked run. Status and URL are cached from the host.
 *
 * @property int $id
 * @property int $execution_id
 * @property string $issue_id
 * @property string|null $issue_url
 * @property string|null $issue_status
 * @property string|null $issue_summary
 * @property Carbon|null $status_fetched_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Execution $execution
 */
#[Fillable(['issue_id', 'issue_url', 'issue_status', 'issue_summary', 'status_fetched_at'])]
class ExecutionIssue extends Model
{
    /** @use HasFactory<ExecutionIssueFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Execution, $this>
     */
    public function execution(): BelongsTo
    {
        return $this->belongsTo(Execution::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_fetched_at' => 'datetime',
        ];
    }
}
