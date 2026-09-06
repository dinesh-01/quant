<?php

namespace App\Http\Requests\Attachments;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * Validates an upload.
 *
 * No `authorize()`: authorization is `StoreAttachment`'s job, so a new caller
 * cannot forget it. This class only decides whether the file is one the
 * application is willing to keep.
 *
 * The parent is never a field here. It comes from the route binding, which is
 * what stops a caller naming its own target — legacy read the parent's table
 * name from the query string, so an upload could be pinned to any row in any
 * table it cared to name.
 */
class AttachmentStoreRequest extends FormRequest
{
    /**
     * The file is checked twice, by design.
     *
     * `mimes` compares the extension guessed from the file's own bytes against
     * the list, and `extensions` compares the name the browser sent. Passing
     * one and not the other is exactly the shape of a disguised upload: HTML
     * saved as `.png` fails the first, and a payload honestly named `.html`
     * fails the second. Legacy checked neither — it stored the browser's
     * `Content-Type` claim verbatim and compared extensions case-sensitively,
     * so `SHELL.PHP` passed a list that contained `php` in lower case.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var list<string> $extensions */
        $extensions = config('attachments.extensions');
        /** @var list<string> $content */
        $content = config('attachments.content_extensions');

        return [
            'file' => [
                'required',
                'file',
                'max:'.(int) config('attachments.max_kilobytes'),
                'mimes:'.implode(',', $content),
                'extensions:'.implode(',', $extensions),
            ],
            'title' => ['nullable', 'string', 'max:250'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        /** @var list<string> $extensions */
        $extensions = config('attachments.extensions');

        return [
            'file.mimes' => __('That file\'s contents are not one of the accepted types: :types.', [
                'types' => implode(', ', $extensions),
            ]),
            'file.extensions' => __('Only these file types may be attached: :types.', [
                'types' => implode(', ', $extensions),
            ]),
        ];
    }

    public function uploadedFile(): UploadedFile
    {
        /** @var UploadedFile $file */
        $file = $this->file('file');

        return $file;
    }

    public function title(): ?string
    {
        $title = $this->validated('title');

        return is_string($title) && trim($title) !== '' ? $title : null;
    }
}
