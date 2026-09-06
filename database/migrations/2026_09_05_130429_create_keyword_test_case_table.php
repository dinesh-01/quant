<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which keywords are on which test cases.
     *
     * **A real pivot with two foreign keys, not a polymorphic link.** Both
     * sides cascade, so deleting a keyword clears its assignments and deleting
     * a case — or the suite or project above it — clears its own. Nothing has
     * to remember: compare `attachments`, where a polymorphic pair cannot carry
     * a foreign key and four delete actions have to call `PurgeAttachments` by
     * hand. Keywords do not need that, so they do not pay for it. Giving suites
     * or requirements keywords later is one more table using the same trait.
     *
     * Assignments are per test *case*, not per version. Legacy moved them to
     * the version in 1.9.18 and then kept reading them both ways — its tree and
     * plan filters look at the latest version while its two search screens
     * match any version — so "does this case have keyword X?" has two answers
     * depending on which screen asks. A keyword describes what a case is for,
     * which does not change when its steps are revised.
     */
    public function up(): void
    {
        Schema::create('keyword_test_case', function (Blueprint $table) {
            $table->foreignId('keyword_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_case_id')->constrained()->cascadeOnDelete();

            /**
             * The pair *is* the row, so there is no surrogate key: assigning
             * the same keyword twice is impossible rather than something the
             * write path has to check for. InnoDB indexes each foreign key on
             * its own, which is what the tree filter reads by case.
             */
            $table->primary(['keyword_id', 'test_case_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('keyword_test_case');
    }
};
