<?php

use App\Enums\TestCaseExecutionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('test_case_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_case_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('actions')->nullable();
            $table->text('expected_results')->nullable();
            $table->string('execution_type')->default(TestCaseExecutionType::Manual->value);
            $table->timestamps();

            $table->index(['test_case_version_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_case_steps');
    }
};
