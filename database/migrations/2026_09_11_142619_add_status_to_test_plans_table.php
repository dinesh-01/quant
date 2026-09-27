<?php

use App\Enums\TestPlanStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add the plan listing lifecycle used by the mockup Active / Draft / Archived tabs.
     */
    public function up(): void
    {
        Schema::table('test_plans', function (Blueprint $table) {
            $table->string('status', 16)->default(TestPlanStatus::Active->value)->after('is_public');
            $table->index(['test_project_id', 'status']);
        });

        DB::table('test_plans')
            ->where('is_active', false)
            ->update(['status' => TestPlanStatus::Archived->value]);
    }

    public function down(): void
    {
        Schema::table('test_plans', function (Blueprint $table) {
            $table->dropIndex(['test_project_id', 'status']);
            $table->dropColumn('status');
        });
    }
};
