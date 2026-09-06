<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The audit trail, replacing legacy's `events` table.
     *
     * Three deliberate departures from that table:
     *
     * - It stored a **pre-rendered, localised** `description` string. That
     *   freezes each record in whatever language the actor's interface was set
     *   to, cannot be re-rendered when the wording improves, and cannot be
     *   filtered on anything inside the sentence. Here `action` and
     *   `properties` hold the facts and the sentence is composed when read.
     * - It carried `log_level` and `source`, conflating application debug
     *   logging with the audit trail. Those are different concerns with
     *   different retention; Laravel's logger covers the first.
     * - `fired_at` was a unix integer. A real timestamp sorts and filters in
     *   SQL without conversion.
     *
     * There is no `updated_at`: an audit row that can be edited is not an
     * audit row.
     */
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();

            /**
             * Nullable because a console command has no actor —
             * `app:make-administrator` is exactly the sort of privileged act
             * this table should carry, and it runs without a session.
             */
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            $table->string('action')->index();

            /** What was acted on, absent for account-wide events such as a sign-in. */
            $table->nullableMorphs('subject');

            $table->json('properties')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
