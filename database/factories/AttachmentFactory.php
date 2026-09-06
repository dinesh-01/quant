<?php

namespace Database\Factories;

use App\Models\Attachable;
use App\Models\Attachment;
use App\Models\TestSuite;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @extends Factory<Attachment>
 */
class AttachmentFactory extends Factory
{
    /**
     * Rows only. Nothing here writes bytes to a disk, so a test that reads the
     * file back has to put them there — use `withContent()`, which does both.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'attachable_type' => TestSuite::class,
            'attachable_id' => TestSuite::factory(),
            'uploader_id' => User::factory(),
            'title' => null,
            'file_name' => $this->faker->word().'.png',
            'disk_path' => 'test-suites/pending/'.Str::ulid().'.png',
            'mime_type' => 'image/png',
            'size_bytes' => $this->faker->numberBetween(100, 500_000),
        ];
    }

    /**
     * Hang the attachment off the given model, with a disk path shaped the way
     * `StoreAttachment` would have written it.
     *
     * Named to stay clear of the factory's own `for()`, which sets up a
     * relationship rather than a polymorphic pair.
     */
    public function attachedTo(Attachable&Model $parent): static
    {
        return $this->state(fn (array $attributes): array => [
            'attachable_type' => $parent::class,
            'attachable_id' => $parent->getKey(),
            'disk_path' => $parent->attachmentFolder()
                .'/'.$parent->getKey()
                .'/'.Str::ulid()
                .'.'.pathinfo((string) $attributes['file_name'], PATHINFO_EXTENSION),
        ]);
    }

    /**
     * Also write the bytes, so the download endpoint has something to serve.
     *
     * Requires `Storage::fake()`, which every attachment test sets up.
     */
    public function withContent(string $content = 'file contents'): static
    {
        return $this->afterCreating(function (Attachment $attachment) use ($content): void {
            Storage::disk($attachment->diskName())->put($attachment->disk_path, $content);

            $attachment->forceFill(['size_bytes' => strlen($content)])->save();
        });
    }

    public function titled(string $title): static
    {
        return $this->state(['title' => $title]);
    }

    /**
     * A type a browser must never be trusted to render in place.
     */
    public function html(): static
    {
        return $this->state(fn (array $attributes): array => [
            'file_name' => 'payload.html',
            'mime_type' => 'text/html',
            'disk_path' => Str::replaceLast('.png', '.html', (string) $attributes['disk_path']),
        ]);
    }
}
