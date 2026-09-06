<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One issue tracker per project: where failed runs open tickets.
     *
     * Credentials live in the encrypted `settings` JSON so a dump does not
     * hold the API token in the clear. Unique `test_project_id` is the
     * contract — save upserts this row.
     */
    public function up(): void
    {
        Schema::create('issue_trackers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('type', 32);
            $table->string('base_url', 255);
            $table->text('settings');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issue_trackers');
    }
};
