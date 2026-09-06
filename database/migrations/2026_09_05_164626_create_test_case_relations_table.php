<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A directed or symmetric link between two test cases in the same project.
     *
     * Both ends are cases, not versions: "PAY-1 blocks PAY-4" is about the
     * cases, not a particular revision of the steps. Unique per
     * (source, destination, type) so the same pair cannot be related twice
     * in the same way.
     */
    public function up(): void
    {
        Schema::create('test_case_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('test_cases')->cascadeOnDelete();
            $table->foreignId('destination_id')->constrained('test_cases')->cascadeOnDelete();
            $table->string('type');
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['source_id', 'destination_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_case_relations');
    }
};
