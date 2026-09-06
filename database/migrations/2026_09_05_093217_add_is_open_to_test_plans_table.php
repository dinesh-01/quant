<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether the plan still accepts execution results.
     *
     * Distinct from `is_active`, which only decides whether the plan is offered
     * in listings. A closed plan stays visible and readable — its results are
     * the record of a completed round of testing — but nothing new can be
     * recorded against it. Phase 6 is what enforces that; the column is added
     * here so plan editing has the switch from the start rather than needing a
     * second migration once execution exists.
     */
    public function up(): void
    {
        Schema::table('test_plans', function (Blueprint $table) {
            $table->boolean('is_open')->default(true)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('test_plans', function (Blueprint $table) {
            $table->dropColumn('is_open');
        });
    }
};
