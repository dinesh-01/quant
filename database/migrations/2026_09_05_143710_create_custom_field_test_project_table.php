<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which projects a custom field applies to, and how it behaves in each.
     *
     * A definition is application-wide but inert until it is enabled here, so
     * this table is what stops every project's screens from growing every other
     * project's fields. Legacy's `cfield_testprojects`, minus two columns.
     *
     * Dropped: `location`, the eight slots a field could be pinned to in the
     * case editor — including slot 8, `hide_because_is_used_as_variable`, which
     * hid the field from the editor so its value could be interpolated as a
     * `[tlVar]` and left users hunting for a field that was there all along.
     * Ordering alone replaces it. Also dropped: `monitorable`, stored by the
     * assignment screen and read by nothing, and the older undifferentiated
     * `required` that `required_on_design` and `required_on_execution` succeed.
     */
    public function up(): void
    {
        Schema::create('custom_field_test_project', function (Blueprint $table) {
            $table->foreignId('custom_field_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_project_id')->constrained()->cascadeOnDelete();

            /**
             * Enabled but switched off: the field stops appearing while its
             * stored values are kept, so switching it back on restores them.
             */
            $table->boolean('is_active')->default(true);

            /** Not unique, as elsewhere: reordering renumbers contiguously. */
            $table->unsignedSmallInteger('sort_order')->default(0);

            /**
             * Whether a value must be given, per context rather than once.
             *
             * A field can be optional while a case is being written and
             * mandatory when a result is recorded against it, which is what
             * legacy's DDL intended with these two columns. Neither was ever
             * read, and neither shipped with an upgrade script, so on an
             * upgraded 1.9 installation they may not exist at all.
             *
             * `required_on_design` is enforced here. `required_on_execution` is
             * stored and cannot be enforced until executions exist, and Phase 6
             * owns making it bite.
             */
            $table->boolean('required_on_design')->default(false);
            $table->boolean('required_on_execution')->default(false);

            /**
             * One row per field per project, clustered the way it is read: the
             * screens always ask "which fields does this project have", never
             * "which projects have this field".
             */
            $table->primary(['test_project_id', 'custom_field_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_field_test_project');
    }
};
