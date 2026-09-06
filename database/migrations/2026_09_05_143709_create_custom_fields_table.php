<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The definition of a custom field: an extra piece of data a team wants to
     * record against its test cases, suites or plans.
     *
     * Definitions are application-wide, as in legacy, and become usable in a
     * project only once enabled there — see the `custom_field_test_project`
     * table. That is what lets one "Browser" field mean the same thing in every
     * project, and what lets an exported test case carry a field name that the
     * importing project can recognise.
     *
     * Dropped from the legacy shape: the `cfield_node_types` link table, folded
     * into `entity_type` (see `CustomFieldEntity`), and the six `show_on_*` /
     * `enable_on_*` flags it also replaces.
     */
    public function up(): void
    {
        Schema::create('custom_fields', function (Blueprint $table) {
            $table->id();

            /**
             * The stable identifier. Unique application-wide, because an
             * exported value carries only its field's name and the importing
             * project matches on it.
             *
             * Case-insensitively unique for free, as the column collation is
             * `utf8mb4_unicode_ci`.
             */
            $table->string('name', 64)->unique();

            /** What the field is called on screen, which may be changed freely. */
            $table->string('label', 64);

            $table->string('type');
            $table->string('entity_type');

            /**
             * The values to choose from, for the types that need them.
             *
             * Legacy packed these into one `varchar(4000)` separated by `|`,
             * with no escaping and no way to enter a value containing one: a
             * stored `a|b` was indistinguishable from two options. A JSON list
             * has no separator to collide with.
             */
            $table->json('options')->nullable();

            /** Offered when no value has been stored yet. */
            $table->text('default_value')->nullable();

            /**
             * The rest of this block is what legacy declared and then never
             * enforced. `valid_regexp` and `length_min` were dead columns:
             * absent from the edit form, dropped by `create()` on import, and
             * read by nothing on save. `length_max` reached the browser as a
             * `maxlength` attribute and no further. All validation was
             * client-side, type-hardcoded, and skipped string, list, checkbox,
             * radio, date and datetime outright, so a crafted POST stored
             * anything at all.
             *
             * They are enforced on the server here, which is the whole point of
             * keeping them.
             */
            $table->string('pattern')->nullable();
            $table->unsignedSmallInteger('minimum_length')->nullable();
            $table->unsignedSmallInteger('maximum_length')->nullable();

            $table->timestamps();

            $table->index('entity_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_fields');
    }
};
