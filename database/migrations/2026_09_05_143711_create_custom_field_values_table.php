<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What was actually filled in, for any kind of thing a field can hang off.
     *
     * This one table replaces four in legacy — `cfield_design_values`,
     * `cfield_execution_values`, `cfield_testplan_design_values` and
     * `cfield_build_design_values` — each keyed differently, and each with its
     * own copy of the read, write, copy and delete logic. Choosing the wrong one
     * was a real and easy mistake there: `get_linked_cfields_at_design()` only
     * looked at the build table when passed `'build'` as an argument, so a
     * forgotten parameter wrote a build's value into the design table, where
     * nothing would ever read it again.
     */
    public function up(): void
    {
        Schema::create('custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('custom_field_id')->constrained()->cascadeOnDelete();

            /*
             * Named for what it is rather than as `-able`: the row records the
             * subject a field was answered about. As with attachments, no
             * foreign key is possible on a polymorphic pair, so nothing at the
             * database level clears these when the subject goes — the delete
             * actions do, through `PurgeCustomFieldValues`. Legacy had the same
             * gap under MySQL and only cascaded under Postgres, so the two
             * supported databases disagreed about whether deletes leaked.
             */
            $table->morphs('subject');

            /**
             * The answer, as text for a scalar and as a JSON list for the
             * multi-value types. `CustomFieldType` decides which, and the
             * `CustomFieldAnswer` cast does the encoding.
             *
             * Not nullable, because clearing a field deletes the row rather
             * than storing an empty one — legacy's behaviour, kept
             * deliberately. The table therefore stays sparse and holds only
             * answers somebody actually gave, at the cost of not being able to
             * tell "cleared" from "never filled in". Anything reading values
             * must start from the definitions and treat a missing row as
             * unanswered, never inner join this table and assume completeness.
             */
            $table->text('value');

            $table->timestamps();

            /**
             * One answer per field per subject. Legacy got this from a composite
             * primary key; the surrogate key here keeps the polymorphic pair out
             * of the clustered index, and the unique constraint is what makes
             * the write path an upsert rather than a select-then-branch.
             *
             * Named explicitly because the derived name is 66 characters and
             * MySQL's limit for an identifier is 64.
             */
            $table->unique(['custom_field_id', 'subject_type', 'subject_id'], 'custom_field_values_field_subject_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_values');
    }
};
