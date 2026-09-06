<?php

namespace App\Actions\Attachments;

use App\Actions\Audit\AuditLogger;
use App\Enums\AuditAction;
use App\Models\Attachable;
use App\Models\Attachment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

/**
 * Puts an uploaded file on the attachments disk and records it against its
 * parent.
 *
 * The only writer of the `attachments` table. Everything a request could lie
 * about is recomputed here rather than accepted: the MIME type is guessed from
 * the file's own bytes, the name on disk is generated, and the parent comes
 * from a route binding rather than from a form field.
 *
 * That last point is not theoretical. Legacy's upload popup took the parent's
 * table name from the query string and kept it in the session, so a caller
 * could attach a file to any row in any table it named.
 */
final class StoreAttachment
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @throws AuthorizationException
     */
    public function __invoke(
        User $user,
        Attachable&Model $target,
        UploadedFile $file,
        ?string $title = null,
    ): Attachment {
        Gate::forUser($user)->authorize(
            $target->attachmentManageAbility()->value,
            $target->attachmentScope(),
        );

        /*
         * Written before the row so a failed write cannot leave a row pointing
         * at nothing. The disk is configured to throw, so a failure here
         * aborts rather than returning false. The reverse order would be worse:
         * a leaked file is invisible, a row with no file is a broken download.
         */
        $path = $file->storeAs(
            $this->folderFor($target),
            $this->nameOnDisk($file),
            (string) config('attachments.disk'),
        );

        $attachment = new Attachment;
        $attachment->attachable_type = $target::class;
        $attachment->attachable_id = (int) $target->getKey();
        $attachment->uploader_id = $user->getKey();
        $attachment->title = $this->cleanTitle($title);
        $attachment->file_name = $this->cleanFileName($file);
        $attachment->disk_path = (string) $path;
        $attachment->mime_type = $file->getMimeType() ?? 'application/octet-stream';
        $attachment->size_bytes = $file->getSize() ?: 0;
        $attachment->save();

        $this->audit->record(AuditAction::AttachmentUploaded, $user, $attachment, [
            'file_name' => $attachment->file_name,
            'mime_type' => $attachment->mime_type,
            'size_bytes' => $attachment->size_bytes,
            'attached_to' => $target::class,
            'attached_to_id' => $target->getKey(),
        ]);

        return $attachment;
    }

    /**
     * One folder per parent, so a stray file can be traced back to what it
     * belonged to and no single directory grows without bound.
     */
    private function folderFor(Attachable&Model $target): string
    {
        return $target->attachmentFolder().'/'.$target->getKey();
    }

    /**
     * A generated name, keeping only the extension.
     *
     * The uploader's name is never used as a path. It is the caller's string,
     * so it can traverse directories, collide with a file already there, or
     * carry characters the filesystem reads specially. A ULID cannot do any of
     * those and sorts by upload time, which makes a directory listing useful.
     */
    private function nameOnDisk(UploadedFile $file): string
    {
        $extension = Str::lower($file->getClientOriginalExtension());

        return Str::ulid().($extension === '' ? '' : '.'.$extension);
    }

    /**
     * The uploader's file name, kept for display only.
     *
     * Stripped to its basename so it cannot read as a path, stripped of
     * control characters — which would let it inject a header on the way back
     * out — and capped to the column's width.
     */
    private function cleanFileName(UploadedFile $file): string
    {
        $name = preg_replace(
            '/[\x00-\x1F\x7F]/u',
            '',
            basename($file->getClientOriginalName()),
        ) ?? '';

        $name = trim($name);

        return $name === '' ? 'attachment' : Str::limit($name, 250, '');
    }

    private function cleanTitle(?string $title): ?string
    {
        $title = trim($title ?? '');

        return $title === '' ? null : Str::limit($title, 250, '');
    }
}
