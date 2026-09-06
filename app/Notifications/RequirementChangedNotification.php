<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Tells a watcher that a requirement they follow has changed.
 *
 * Fields are primitives so a queued send still works after the requirement
 * is deleted, and so the job does not have to reload it.
 */
class RequirementChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $requirementId,
        public int $projectId,
        public string $docId,
        public string $name,
        public string $actorName,
        public string $reason,
        public bool $deleted = false,
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
        $message = (new MailMessage)
            ->subject('Requirement '.$this->docId.' changed')
            ->line($this->actorName.' '.$this->reason.'.')
            ->line($this->docId.' — '.$this->name);

        if ($this->deleted) {
            return $message->action(
                'Open project',
                route('requirements.show', $this->projectId),
            );
        }

        return $message->action(
            'Open requirement',
            route('requirements.items.show', [
                'testProject' => $this->projectId,
                'requirement' => $this->requirementId,
            ]),
        );
    }
}
