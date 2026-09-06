<?php

namespace App\Actions\Users;

use App\Actions\Audit\AuditLogger;
use App\Concerns\EndsUserSessions;
use App\Enums\Ability;
use App\Enums\AuditAction;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Emails a signed reset link and ends the account's other sessions.
 *
 * Same abilities as `SetUserPassword`: sending the link is taking over the
 * account. The typed-password action stays as a fallback for when mail is
 * only writing to the log.
 */
final class SendPasswordResetLink
{
    use EndsUserSessions;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function __invoke(User $actor, User $user): void
    {
        $gate = Gate::forUser($actor);

        $gate->authorize(Ability::ManageUsers->value);
        $gate->authorize(Ability::AssignGlobalRoles->value);

        DB::transaction(function () use ($actor, $user): void {
            $sessionsEnded = $this->endUserSessions($user);

            $status = Password::sendResetLink(['email' => $user->email]);

            if ($status !== Password::RESET_LINK_SENT) {
                throw ValidationException::withMessages([
                    'email' => __($status),
                ]);
            }

            $this->audit->record(AuditAction::UserPasswordResetLinkSent, $actor, $user, [
                'sessions_ended' => $sessionsEnded,
            ]);
        });
    }
}
