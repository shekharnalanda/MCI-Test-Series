<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('current_affair_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('content_source_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('external_reference')->nullable();
            $table->string('title');
            $table->text('summary')->nullable();

            $table->text('source_url')->nullable();

            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('fetched_at')->nullable();

            $table->char('content_hash', 64)->unique();

            $table->unsignedTinyInteger('trust_score')
                ->default(0)
                ->index();

            $table->unsignedTinyInteger('freshness_score')
                ->default(0)
                ->index();

            $table->unsignedTinyInteger('quality_score')
                ->default(0)
                ->index();

            $table->enum('status', [
                'pending',
                'approved',
                'rejected',
                'processed',
                'expired'
            ])->default('pending')->index();

            $table->boolean('auto_approved')->default(false);
            $table->boolean('question_generated')->default(false)->index();

            $table->timestamp('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->json('metadata')->nullable();

            $table->timestamps();

            $table->index([
                'status',
                'freshness_score',
                'question_generated'
            ], 'ca_processing_pool_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('current_affair_items');
    }
};
