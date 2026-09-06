<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A frozen copy of a plan-status report so later runs can be compared
     * without depending on live executions or still-linked plan items.
     */
    public function up(): void
    {
        Schema::create('report_baselines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('build_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('total');
            $table->json('counts');
            $table->json('items');
            $table->timestamps();

            $table->unique(['test_plan_id', 'name']);
            $table->index(['test_plan_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_baselines');
    }
};
