<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which of a project's platforms a plan executes against.
     *
     * A real pivot with two foreign keys, both cascading, so deleting a
     * platform or a plan clears the assignment. The plan-item unique key
     * treats "no platform" as one slot, so a plan with no rows here can still
     * hold one item per version.
     */
    public function up(): void
    {
        Schema::create('test_plan_platform', function (Blueprint $table) {
            $table->foreignId('test_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_id')->constrained()->cascadeOnDelete();

            $table->primary(['test_plan_id', 'platform_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_plan_platform');
    }
};
