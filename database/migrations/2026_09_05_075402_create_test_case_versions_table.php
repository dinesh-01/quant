<?php

use App\Enums\TestCaseExecutionType;
use App\Enums\TestCaseImportance;
use App\Enums\TestCaseStatus;
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
        Schema::create('test_case_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_case_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->string('status')->default(TestCaseStatus::Draft->value);
            $table->text('summary')->nullable();
            $table->text('preconditions')->nullable();
            $table->string('importance')->default(TestCaseImportance::Medium->value);
            $table->string('execution_type')->default(TestCaseExecutionType::Manual->value);
            $table->decimal('estimated_duration', 8, 2)->nullable();
            $table->boolean('is_open')->default(true);
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updater_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['test_case_id', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('test_case_versions');
    }
};
