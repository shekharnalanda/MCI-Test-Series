<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->string('source_reference', 150)->nullable()->after('source_url');
            $table->timestamp('imported_at')->nullable()->after('source_published_at');
            $table->index(['content_source_id', 'source_published_at'], 'question_source_provenance_index');
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $table) {
            $table->dropIndex('question_source_provenance_index');
            $table->dropColumn(['source_reference', 'imported_at']);
        });
    }
};
