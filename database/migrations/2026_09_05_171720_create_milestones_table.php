<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Per-priority completion targets on a plan. Reports (Phase 7) read
     * these; authoring them does not need the report screens.
     */
    public function up(): void
    {
        Schema::create('milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_plan_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->date('target_date');
            $table->date('start_date')->nullable();
            $table->unsignedTinyInteger('high_percent')->default(0);
            $table->unsignedTinyInteger('medium_percent')->default(0);
            $table->unsignedTinyInteger('low_percent')->default(0);
            $table->timestamps();

            $table->unique(['test_plan_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milestones');
    }
};
