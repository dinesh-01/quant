<?php

namespace App\Actions\Attachments;

use App\Actions\Audit\AuditLogger;
use App\Enums\AuditAction;
use App\Models\Attachable;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

/**
 * Removes an attachment and its file.
 *
 * Authorized against the parent, so removing a file needs the same standing as
 * changing the thing it hangs off. Legacy gated this on nothing but "have you
 * recently seen a page that listed this id", held in the session — and its
 * entity pages passed a flag that switched even that off.
 */
final class DeleteAttachment
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(User $user, Attachment $attachment): void
    {
        $attachment->loadMissing('attachable');

        /** @var Attachable&Model $target */
        $target = $attachment->attachable;

        Gate::forUser($user)->authorize(
            $target->attachmentManageAbility()->value,
            $target->attachmentScope(),
        );

        $path = $attachment->disk_path;
        $disk = $attachment->diskName();

        DB::transaction(function () use ($user, $attachment, $target): void {
            /*
             * Recorded before the delete, because afterwards there is no row
             * to name and the file name is the only useful thing about it.
             */
            $this->audit->record(AuditAction::AttachmentDeleted, $user, $attachment, [
                'file_name' => $attachment->file_name,
                'attached_to' => $target::class,
                'attached_to_id' => $target->getKey(),
            ]);

            $attachment->delete();
        });

        /*
         * After the commit, deliberately. A file removed inside a transaction
         * that then rolls back is gone while its row survives, which is a
         * download that fails forever. The opposite risk is a file nobody
         * references, which costs disk and nothing else.
         */
        Storage::disk($disk)->delete($path);
    }
}
