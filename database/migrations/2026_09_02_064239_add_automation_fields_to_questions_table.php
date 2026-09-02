<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * cPanel's SQLite/Laravel schema introspection has a compatibility
         * issue on ALTER TABLE. Raw SQLite statements avoid that path.
         * Production MySQL keeps normal Laravel schema + foreign keys.
         */

        if (DB::getDriverName() === 'sqlite') {

            DB::statement(
                'ALTER TABLE questions ADD COLUMN content_source_id INTEGER NULL'
            );

            DB::statement(
                'ALTER TABLE questions ADD COLUMN question_import_batch_id INTEGER NULL'
            );

            DB::statement(
                'ALTER TABLE questions ADD COLUMN normalized_hash VARCHAR(64) NULL'
            );

            DB::statement(
                'ALTER TABLE questions ADD COLUMN external_reference VARCHAR(255) NULL'
            );

            DB::statement(
                'ALTER TABLE questions ADD COLUMN quality_score INTEGER NOT NULL DEFAULT 0'
            );

            DB::statement(
                'ALTER TABLE questions ADD COLUMN freshness_score INTEGER NOT NULL DEFAULT 0'
            );

            DB::statement(
                'ALTER TABLE questions ADD COLUMN verified_at DATETIME NULL'
            );

            DB::statement(
                'ALTER TABLE questions ADD COLUMN published_at DATETIME NULL'
            );

            DB::statement(
                'CREATE UNIQUE INDEX questions_normalized_hash_unique
                 ON questions(normalized_hash)'
            );

            DB::statement(
                'CREATE INDEX questions_external_reference_idx
                 ON questions(external_reference)'
            );

            DB::statement(
                'CREATE INDEX questions_quality_score_idx
                 ON questions(quality_score)'
            );

            DB::statement(
                'CREATE INDEX questions_freshness_score_idx
                 ON questions(freshness_score)'
            );

            DB::statement(
                'CREATE INDEX questions_generation_pool_idx
                 ON questions(
                    subject_id,
                    difficulty,
                    verification_status,
                    is_published
                 )'
            );

            DB::statement(
                'CREATE INDEX questions_content_source_idx
                 ON questions(content_source_id)'
            );

            DB::statement(
                'CREATE INDEX questions_import_batch_idx
                 ON questions(question_import_batch_id)'
            );

            return;
        }

        Schema::table('questions', function (Blueprint $table) {

            $table->foreignId('content_source_id')
                ->nullable()
                ->constrained('content_sources')
                ->nullOnDelete();

            $table->foreignId('question_import_batch_id')
                ->nullable()
                ->constrained('question_import_batches')
                ->nullOnDelete();

            $table->char('normalized_hash', 64)
                ->nullable()
                ->unique();

            $table->string('external_reference')
                ->nullable()
                ->index();

            $table->unsignedTinyInteger('quality_score')
                ->default(0)
                ->index();

            $table->unsignedTinyInteger('freshness_score')
                ->default(0)
                ->index();

            $table->timestamp('verified_at')->nullable();
            $table->timestamp('published_at')->nullable();

            $table->index(
                [
                    'subject_id',
                    'difficulty',
                    'verification_status',
                    'is_published'
                ],
                'questions_generation_pool_idx'
            );
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {

            DB::statement(
                'DROP INDEX IF EXISTS questions_generation_pool_idx'
            );

            DB::statement(
                'DROP INDEX IF EXISTS questions_external_reference_idx'
            );

            DB::statement(
                'DROP INDEX IF EXISTS questions_quality_score_idx'
            );

            DB::statement(
                'DROP INDEX IF EXISTS questions_freshness_score_idx'
            );

            DB::statement(
                'DROP INDEX IF EXISTS questions_normalized_hash_unique'
            );

            DB::statement(
                'DROP INDEX IF EXISTS questions_content_source_idx'
            );

            DB::statement(
                'DROP INDEX IF EXISTS questions_import_batch_idx'
            );

            /*
             * SQLite test DB is recreated with migrate:fresh,
             * therefore destructive column rebuild is unnecessary here.
             */
            return;
        }

        Schema::table('questions', function (Blueprint $table) {

            $table->dropIndex('questions_generation_pool_idx');

            $table->dropForeign(['content_source_id']);
            $table->dropForeign(['question_import_batch_id']);

            $table->dropColumn([
                'content_source_id',
                'question_import_batch_id',
                'normalized_hash',
                'external_reference',
                'quality_score',
                'freshness_score',
                'verified_at',
                'published_at',
            ]);
        });
    }
};
