<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who should run a planned case on a given build.
     *
     * The assignment hangs off the plan item (the version-on-this-plan slot)
     * and a build, matching legacy's `user_assignments` on a feature id plus
     * `build_id`. Review assignments are not carried over: nothing reviews a
     * version independently of execution here.
     *
     * Uniqueness is (item, build, user). The same tester can be on two builds,
     * and two testers can share one item, but the same person is not listed
     * twice for the same run.
     */
    public function up(): void
    {
        Schema::create('tester_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_plan_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('build_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assigner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->timestamp('deadline_at')->nullable();
            $table->timestamps();

            $table->unique(['test_plan_item_id', 'build_id', 'user_id']);
            $table->index(['build_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tester_assignments');
    }
};
