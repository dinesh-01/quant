<?php

namespace App\Listeners;

use App\Enums\Ability;
use App\Models\User;
use App\Notifications\UserRegisteredNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

class NotifyAdministratorsOfRegistration implements ShouldQueue
{
    public function handle(Registered $event): void
    {
        $registered = $event->user;

        if (! $registered instanceof User) {
            return;
        }

        $managers = User::query()
            ->with('role')
            ->whereKeyNot($registered->getKey())
            ->get()
            ->filter(function (User $user): bool {
                return $user->isActive()
                    && Gate::forUser($user)->allows(Ability::ManageUsers->value);
            });

        if ($managers->isEmpty()) {
            return;
        }

        Notification::send($managers, new UserRegisteredNotification(
            $registered->name,
            $registered->email,
        ));
    }
}
