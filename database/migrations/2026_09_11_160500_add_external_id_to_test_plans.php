<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plans get a per-project number, the same way test cases do.
 *
 * A plan is referred to as `CO-P12` in conversation and on screen. Using the
 * primary key for that would make the number depend on what other projects
 * happen to have created, so plans carry their own counter.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('test_projects', function (Blueprint $table): void {
            $table->unsignedInteger('test_plan_counter')->default(0)->after('test_case_counter');
        });

        Schema::table('test_plans', function (Blueprint $table): void {
            $table->unsignedInteger('external_id')->nullable()->after('test_project_id');
        });

        $this->numberExistingPlans();

        Schema::table('test_plans', function (Blueprint $table): void {
            $table->unique(['test_project_id', 'external_id']);
        });
    }

    public function down(): void
    {
        Schema::table('test_plans', function (Blueprint $table): void {
            $table->dropUnique(['test_project_id', 'external_id']);
            $table->dropColumn('external_id');
        });

        Schema::table('test_projects', function (Blueprint $table): void {
            $table->dropColumn('test_plan_counter');
        });
    }

    /**
     * Number plans in the order each project created them, and leave every
     * project's counter on its highest number.
     */
    private function numberExistingPlans(): void
    {
        foreach (DB::table('test_projects')->orderBy('id')->pluck('id') as $projectId) {
            $number = 0;

            $plans = DB::table('test_plans')
                ->where('test_project_id', $projectId)
                ->orderBy('id')
                ->pluck('id');

            foreach ($plans as $planId) {
                $number++;
                DB::table('test_plans')->where('id', $planId)->update(['external_id' => $number]);
            }

            DB::table('test_projects')->where('id', $projectId)->update(['test_plan_counter' => $number]);
        }
    }
};
