<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cached tracker fields for a linked issue. All nullable so a typed-in
     * id still stores when no tracker is configured.
     */
    public function up(): void
    {
        Schema::table('execution_issues', function (Blueprint $table) {
            $table->string('issue_url', 255)->nullable()->after('issue_id');
            $table->string('issue_status', 100)->nullable()->after('issue_url');
            $table->string('issue_summary', 255)->nullable()->after('issue_status');
            $table->timestamp('status_fetched_at')->nullable()->after('issue_summary');
        });
    }

    public function down(): void
    {
        Schema::table('execution_issues', function (Blueprint $table) {
            $table->dropColumn(['issue_url', 'issue_status', 'issue_summary', 'status_fetched_at']);
        });
    }
};
