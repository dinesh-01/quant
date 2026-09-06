<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A test case version pinned onto a plan, optionally on one platform.
     *
     * This is legacy's `testplan_tcversions` ("feature"). It points at a
     * *version*, not a case: a plan promises to run a specific revision, and
     * a later edit of the case must not silently change that promise.
     *
     * Urgency lives here, not on the case. Importance stays on the version;
     * priority is derived from the two later, when a screen reads it.
     *
     * `sort_order` is deliberately not unique. MySQL cannot defer a unique
     * check to commit, so a reorder would collide mid-transaction — the same
     * reason suites, cases and steps use a non-unique `sort_order`.
     */
    public function up(): void
    {
        Schema::create('test_plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_case_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_id')->nullable()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('urgency')->default('medium');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        /**
         * MySQL treats NULLs as distinct in a unique index, so a plain unique
         * on (plan, version, platform) would allow two "no platform" rows for
         * the same version — the slot a plan without platforms uses. A
         * functional key part turns that NULL into 0 for the index only; the
         * column stays a nullable foreign key (legacy used the sentinel 0 as
         * the column itself). A stored generated column cannot do this: InnoDB
         * refuses a foreign key on a column that a generated column reads.
         */
        DB::statement('alter table `test_plan_items` add unique `test_plan_items_plan_version_platform_unique` (`test_plan_id`, `test_case_version_id`, (ifnull(`platform_id`, 0)))');
    }

    public function down(): void
    {
        Schema::dropIfExists('test_plan_items');
    }
};
