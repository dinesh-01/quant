<?php

namespace App\Actions\Attachments;

use App\Models\Attachable;
use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Duplicates one parent's attachments onto another.
 *
 * Legacy did this on a new test case version, on copying a case or a suite, and
 * on copying a plan, and it is worth keeping: a new version that silently lost
 * its screenshots would be a worse copy than no copy at all, and the files are
 * usually the evidence the version is about.
 *
 * **The bytes are copied, not shared.** Two rows pointing at one file would
 * mean deleting either copy breaks the other, and `DeleteAttachment` has no way
 * to know it is not the last reference. Legacy shared nothing either, though it
 * reused the source's generated filename in the new folder.
 *
 * Deliberately does not authorize and takes no acting user: it is an internal
 * collaborator of copy actions that have already authorized both ends, the same
 * rule as `CopyTestCase::duplicate()`. Never call it from a controller.
 */
final class CopyAttachments
{
    /**
     * @return int how many were copied
     */
    public function __invoke(Attachable&Model $source, Attachable&Model $target): int
    {
        $disk = Storage::disk((string) config('attachments.disk'));

        $copied = 0;

        foreach ($source->attachments()->get() as $attachment) {
            /*
             * A row whose file has gone — a restored database against an
             * unrestored disk — is skipped rather than copied as a row
             * pointing at nothing, which would spread the breakage.
             */
            if (! $disk->exists($attachment->disk_path)) {
                continue;
            }

            $path = $target->attachmentFolder()
                .'/'.$target->getKey()
                .'/'.Str::ulid()
                .$this->extensionOf($attachment);

            $disk->copy($attachment->disk_path, $path);

            $copy = new Attachment;
            $copy->attachable_type = $target::class;
            $copy->attachable_id = (int) $target->getKey();
            $copy->uploader_id = $attachment->uploader_id;
            $copy->title = $attachment->title;
            $copy->file_name = $attachment->file_name;
            $copy->disk_path = $path;
            $copy->mime_type = $attachment->mime_type;
            $copy->size_bytes = $attachment->size_bytes;
            $copy->save();

            $copied++;
        }

        return $copied;
    }

    private function extensionOf(Attachment $attachment): string
    {
        $extension = pathinfo($attachment->disk_path, PATHINFO_EXTENSION);

        return $extension === '' ? '' : '.'.$extension;
    }
}
