<?php

namespace App\Concerns;

use App\Models\Attachable;
use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;

trait PresentsAttachments
{
    /**
     * The files hanging off one entity, as a screen needs them.
     *
     * No authorization here. An attachment is part of the thing it hangs off,
     * so every caller has already authorized that thing's view ability to be
     * rendering it at all — and a second check against the same ability would
     * only be a second place to get it wrong.
     *
     * `is_image` is the server's answer, taken from the sniffed type through
     * `isSafeToShowInline()`, rather than something the page works out from a
     * file name. It decides whether the browser is asked to render the bytes,
     * which is not a judgement to make from a string the uploader chose.
     *
     * @return list<array<string, mixed>>
     */
    protected function attachmentProps(Attachable&Model $target): array
    {
        return array_values($target->attachments()
            ->with('uploader:id,name')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Attachment $attachment): array => [
                'id' => $attachment->id,
                'label' => $attachment->label(),
                'file_name' => $attachment->file_name,
                'size_bytes' => $attachment->size_bytes,
                'mime_type' => $attachment->mime_type,
                'is_image' => $attachment->isSafeToShowInline(),
                'uploader' => $attachment->uploader?->name,
                'uploaded_at' => $attachment->created_at?->toIso8601String(),
            ])
            ->all());
    }

    /**
     * What the upload control should tell the user it will accept.
     *
     * Read from the same config the validation rules read, so the hint on the
     * screen cannot drift from what the server will actually take.
     *
     * @return array{max_kilobytes: int, extensions: list<string>}
     */
    protected function attachmentRules(): array
    {
        /** @var list<string> $extensions */
        $extensions = config('attachments.extensions');

        return [
            'max_kilobytes' => (int) config('attachments.max_kilobytes'),
            'extensions' => $extensions,
        ];
    }
}
