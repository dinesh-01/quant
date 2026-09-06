<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Requirement specs nest like suites. Requirements hold versions only —
     * there is no parallel revision table. Coverage pins a requirement
     * version to a test case version so a new version does not silently
     * move the links.
     */
    public function up(): void
    {
        Schema::create('requirement_specs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('requirement_specs')->cascadeOnDelete();
            $table->string('name');
            $table->string('doc_id', 64);
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['test_project_id', 'doc_id']);
        });

        Schema::create('requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requirement_spec_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('doc_id', 64);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['test_project_id', 'doc_id']);
        });

        Schema::create('requirement_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requirement_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->text('scope')->nullable();
            $table->string('status');
            $table->string('type');
            $table->unsignedInteger('expected_coverage')->default(1);
            $table->boolean('is_open')->default(true);
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updater_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['requirement_id', 'version']);
        });

        Schema::create('requirement_coverages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requirement_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_case_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['requirement_version_id', 'test_case_version_id'], 'requirement_coverages_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requirement_coverages');
        Schema::dropIfExists('requirement_versions');
        Schema::dropIfExists('requirements');
        Schema::dropIfExists('requirement_specs');
    }
};
