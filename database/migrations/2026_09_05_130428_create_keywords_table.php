<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A project's catalogue of keywords, the free-form tags a team sorts its
     * test cases by — `smoke`, `regression`, `needs-data`.
     *
     * Scoped to a test project, as in legacy: two teams naming a keyword the
     * same thing mean different things by it, and a shared catalogue would put
     * every project's vocabulary in one list.
     */
    public function up(): void
    {
        Schema::create('keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_project_id')->constrained()->cascadeOnDelete();

            /**
             * Capped at 100 to match legacy, which is generous for a tag.
             *
             * Legacy also refused a name containing `"` or `,`. That was not a
             * rule about keywords: it passed assignments around as
             * comma-separated strings in query strings, so a comma broke the
             * parsing. Nothing here does that, so the restriction is dropped.
             */
            $table->string('name', 100);

            $table->text('notes')->nullable();
            $table->timestamps();

            /**
             * The uniqueness users rely on, and case-insensitive for free: the
             * column collation is `utf8mb4_unicode_ci`, so `Smoke` collides
             * with `smoke`. Legacy compared with `UPPER()` in PHP and only
             * added the database constraint in 1.9.19, so older installations
             * accumulated duplicates its own screens could not tell apart.
             */
            $table->unique(['test_project_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keywords');
    }
};
