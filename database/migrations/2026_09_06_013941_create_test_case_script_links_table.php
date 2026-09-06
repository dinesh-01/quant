<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Automation scripts pinned to a test case version.
     *
     * Hang off the version so a new revision keeps its own paths, and so
     * branching a version (or copying a case) can copy the rows forward.
     */
    public function up(): void
    {
        Schema::create('test_case_script_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_case_version_id')->constrained()->cascadeOnDelete();
            $table->string('project_key', 100);
            $table->string('repository', 191);
            $table->string('path', 255);
            $table->string('branch', 191)->nullable();
            $table->string('commit', 64)->nullable();
            $table->timestamps();

            $table->unique(
                ['test_case_version_id', 'project_key', 'repository', 'path'],
                'test_case_script_links_version_script_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_case_script_links');
    }
};
