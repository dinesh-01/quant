<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Files hanging off a test suite, a test case version or a test project.
     *
     * Legacy's `attachments` table could hold the bytes itself, in a `longblob`
     * that was base64 encoded first — so a 1 MB file cost about 1.4 MB of row,
     * and every listing risked dragging file contents into memory. Contents
     * live on a disk here and only the path is stored, which is also what lets
     * the disk be moved to object storage later without a schema change.
     *
     * Dropped from the legacy shape: `content` and `compression_type` with the
     * database storage mode they served, and `description`, which legacy's own
     * write path never populated.
     */
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();

            /*
             * No foreign key is possible on a polymorphic pair, so nothing at
             * the database level removes these rows when their parent goes.
             * `PurgeAttachments` is what keeps that promise, and every action
             * that deletes a possible parent has to call it. Legacy had the
             * same gap and leaked both rows and files through it.
             */
            $table->morphs('attachable');

            $table->foreignId('uploader_id')->nullable()->constrained('users')->nullOnDelete();

            /** Shown instead of the file name when the uploader gives one. */
            $table->string('title')->nullable();

            /** The name the browser sent, cleaned up. Never used as a path. */
            $table->string('file_name');

            /** Where the bytes are on the attachments disk. */
            $table->string('disk_path')->unique();

            /**
             * Guessed by reading the file, never taken from the upload's own
             * `Content-Type` header. Legacy stored the client's claim and then
             * echoed it back on download.
             */
            $table->string('mime_type');

            $table->unsignedBigInteger('size_bytes');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
