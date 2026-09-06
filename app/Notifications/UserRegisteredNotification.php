<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a user manager that someone signed up through the public form.
 */
class UserRegisteredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $name,
        public string $email,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('New account registered'))
            ->line(__(':name (:email) created an account.', [
                'name' => $this->name,
                'email' => $this->email,
            ]))
            ->action(__('Open the user directory'), route('users.index'));
    }
}
