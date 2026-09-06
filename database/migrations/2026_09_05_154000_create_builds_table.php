<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A named snapshot of the software a plan is executed against.
     *
     * Unique per plan (`1.2.0` in two plans are different builds). `is_active`
     * decides whether the build appears in listings; `is_open` decides whether
     * it still accepts results — the same split plans already use. Phase 6
     * enforces the execution side.
     *
     * VCS metadata columns (commit, tag, branch) are not here: nothing reads
     * them yet, and leftover columns against no consumer is the mistake the
     * deferred enums were written to avoid.
     */
    public function up(): void
    {
        Schema::create('builds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_plan_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_open')->default(true);
            $table->date('release_date')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['test_plan_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('builds');
    }
};
