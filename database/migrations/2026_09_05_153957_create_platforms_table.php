<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A project's catalogue of platforms — the environments a case is written
     * for and later executed on (`Chrome`, `iOS`, `API`).
     *
     * Scoped to a test project, as in legacy: two teams naming a platform the
     * same thing mean different environments, and a shared catalogue would put
     * every project's vocabulary in one list.
     */
    public function up(): void
    {
        Schema::create('platforms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_project_id')->constrained()->cascadeOnDelete();

            $table->string('name', 100);
            $table->text('notes')->nullable();

            /**
             * Design-time: the platform may be assigned to a test case version
             * as "this case applies here". Execution-time: it may be assigned
             * to a plan as a dimension of a planned item. Both default true so
             * a newly created platform is usable until someone restricts it.
             * Legacy defaulted design-time off; that hid the version-side
             * table from anyone who had not found the flag.
             */
            $table->boolean('enable_on_design')->default(true);
            $table->boolean('enable_on_execution')->default(true);

            $table->boolean('is_open')->default(true);
            $table->timestamps();

            /**
             * Case-insensitive for free: the column collation is
             * `utf8mb4_unicode_ci`, so `Chrome` collides with `chrome`.
             */
            $table->unique(['test_project_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platforms');
    }
};
