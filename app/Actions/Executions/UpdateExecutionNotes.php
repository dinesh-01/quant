<?php

namespace App\Actions\Executions;

use App\Actions\Audit\AuditLogger;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\Execution;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Edits notes on a completed run.
 */
final class UpdateExecutionNotes
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $user, Execution $execution, ?string $notes): Execution
    {
        $execution->loadMissing('testPlan');

        Gate::forUser($user)->authorize(Ability::EditExecutionNotes->value, $execution->testPlan);

        if ($execution->is_draft) {
            throw ValidationException::withMessages([
                'notes' => 'Edit a draft by saving the run, not through notes.',
            ]);
        }

        $execution->notes = $notes;
        $execution->save();

        $this->audit->record(AuditAction::ExecutionNotesUpdated, $user, $execution->testPlan, [
            'execution_id' => $execution->id,
        ]);

        return $execution;
    }
}
