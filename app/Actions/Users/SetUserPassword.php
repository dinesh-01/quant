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

/**
 * Sets another account's password, for when its owner cannot.
 *
 * Without this and without outbound mail, a forgotten password is unrecoverable
 * — so this exists to be replaced by a reset link rather than to be the
 * permanent answer. Legacy emailed a new password in plain text.
 *
 * **It needs `assign_global_roles`, not merely `manage_users`.** Setting
 * someone's password is taking over their account, which is at least as
 * powerful as changing what that account may do — so it takes the same
 * ability. Gating it on `manage_users` alone would quietly turn that into an
 * administrator-level ability: its holder could take over a super admin and
 * inherit everything. Keeping the two apart is what lets `manage_users` remain
 * genuinely narrow — able to add and deactivate people, but not to escalate.
 */
final class SetUserPassword
{
    use EndsUserSessions;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $actor, User $user, string $password): void
    {
        $gate = Gate::forUser($actor);

        $gate->authorize(Ability::ManageUsers->value);
        $gate->authorize(Ability::AssignGlobalRoles->value);

        DB::transaction(function () use ($actor, $user, $password): void {
            $user->forceFill([
                'password' => $password,
            ])->save();

            $sessionsEnded = $this->endUserSessions($user);

            /**
             * Inside the transaction: a takeover of someone's account that
             * went unrecorded because the insert failed afterwards is worse
             * than one that did not happen at all.
             *
             * The password itself never reaches the trail — `changes()` is not
             * used here, and `properties` is written by hand.
             */
            $this->audit->record(AuditAction::UserPasswordSet, $actor, $user, [
                'sessions_ended' => $sessionsEnded,
            ]);
        });
    }
}
