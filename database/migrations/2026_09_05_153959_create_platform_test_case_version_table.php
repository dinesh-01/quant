<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which platforms a test case *version* is written for.
     *
     * This hangs off the version, not the case — the opposite of keywords.
     * A keyword describes what a case is about and does not change when its
     * steps are edited. A platform assignment is a claim about this revision
     * of the steps (`this version covers iOS`), so an older version keeps
     * what it said and `CreateTestCaseVersion` has to copy the rows forward.
     * Legacy stored them per version for the same reason.
     */
    public function up(): void
    {
        Schema::create('platform_test_case_version', function (Blueprint $table) {
            $table->foreignId('platform_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_case_version_id')->constrained()->cascadeOnDelete();

            $table->primary(['platform_id', 'test_case_version_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_test_case_version');
    }
};
