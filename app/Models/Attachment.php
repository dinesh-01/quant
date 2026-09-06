<?php

namespace App\Models;

use Database\Factories\AttachmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A file hanging off a test suite, a test case version or a test project.
 *
 * The bytes live on a private disk; this row holds only where they are and what
 * they were called. Nothing here is written from a request without going
 * through `StoreAttachment`, which is what guarantees `mime_type` was guessed
 * from the file's own content rather than taken from the browser.
 *
 * @property int $id
 * @property string $attachable_type
 * @property int $attachable_id
 * @property int|null $uploader_id
 * @property string|null $title
 * @property string $file_name
 * @property string $disk_path
 * @property string $mime_type
 * @property int $size_bytes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Attachable&Model $attachable
 * @property-read User|null $uploader
 */
class Attachment extends Model
{
    /** @use HasFactory<AttachmentFactory> */
    use HasFactory;

    /**
     * Nothing is mass assignable.
     *
     * Every column is either derived from the file itself or decides where the
     * file is read back from, so a caller must set them one at a time and mean
     * it. `StoreAttachment` is the only writer.
     *
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @return MorphTo<Model, $this>
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    /**
     * What the user should see this called.
     */
    public function label(): string
    {
        return $this->title ?? $this->file_name;
    }

    /**
     * Determine whether a browser may be trusted to render this in place.
     *
     * Only raster images qualify. Legacy served everything inline under its
     * stored MIME type, so an uploaded HTML file ran as a page on this origin.
     */
    public function isSafeToShowInline(): bool
    {
        /** @var list<string> $inline */
        $inline = config('attachments.inline_types');

        return in_array($this->mime_type, $inline, true);
    }

    /**
     * Determine whether the bytes are actually still on the disk.
     *
     * Worth asking before serving: a restored database with an unrestored disk
     * would otherwise fail as a 500 rather than as a missing file.
     */
    public function existsOnDisk(): bool
    {
        return Storage::disk($this->diskName())->exists($this->disk_path);
    }

    public function diskName(): string
    {
        return (string) config('attachments.disk');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'attachable_id' => 'integer',
            'size_bytes' => 'integer',
        ];
    }
}
