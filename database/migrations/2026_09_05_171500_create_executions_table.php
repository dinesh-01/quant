<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A run against one plan item on one build. `version` freezes the case
     * version number at the time of the run. At most one draft per
     * (item, build, tester) is enforced in the action, not here — MySQL
     * cannot express a partial unique on is_draft.
     */
    public function up(): void
    {
        Schema::create('executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignId('build_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_plan_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_case_version_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('tester_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->text('notes')->nullable();
            $table->decimal('duration', 6, 2)->nullable();
            $table->boolean('is_draft')->default(true);
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['test_plan_item_id', 'build_id', 'tester_id', 'is_draft'], 'executions_draft_lookup');
        });

        Schema::create('execution_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('execution_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_case_step_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order');
            $table->string('status');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['execution_id', 'test_case_step_id'], 'execution_steps_unique');
        });

        Schema::create('execution_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('execution_id')->constrained()->cascadeOnDelete();
            $table->string('issue_id', 64);
            $table->timestamps();

            $table->unique(['execution_id', 'issue_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_issues');
        Schema::dropIfExists('execution_steps');
        Schema::dropIfExists('executions');
    }
};
