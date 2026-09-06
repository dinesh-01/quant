<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One code tracker per project: where this project's automation lives.
     *
     * A global catalogue would mix every team's GitHub org into one list.
     * Unique `test_project_id` is the contract — save upserts this row.
     */
    public function up(): void
    {
        Schema::create('code_trackers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_project_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('type', 32);
            $table->string('base_url', 255);
            $table->string('project_key', 100)->nullable();
            $table->string('view_url_template', 255)->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('code_trackers');
    }
};
