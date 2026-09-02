<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();

            $table->longText('question_text');
            $table->longText('question_text_hi')->nullable();
            $table->longText('explanation')->nullable();
            $table->longText('explanation_hi')->nullable();

            $table->enum('question_type', [
                'single_choice',
                'multiple_choice',
                'true_false',
                'numerical'
            ])->default('single_choice')->index();

            $table->enum('difficulty', [
                'easy',
                'medium',
                'hard'
            ])->default('medium')->index();

            $table->string('language', 20)->default('bilingual')->index();

            $table->boolean('is_current_affairs')->default(false)->index();
            $table->date('current_affair_date')->nullable()->index();

            $table->string('source_name')->nullable();
            $table->text('source_url')->nullable();
            $table->date('source_published_at')->nullable();
            $table->unsignedTinyInteger('source_confidence')->default(0)->index();

            $table->enum('verification_status', [
                'pending',
                'verified',
                'rejected',
                'needs_review'
            ])->default('pending')->index();

            $table->enum('generation_method', [
                'manual',
                'import',
                'automated',
                'ai_assisted'
            ])->default('manual')->index();

            $table->char('content_hash', 64)->unique();
            $table->unsignedInteger('usage_count')->default(0)->index();
            $table->timestamp('last_used_at')->nullable()->index();

            $table->boolean('auto_publish')->default(false);
            $table->boolean('is_published')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();

            $table->index(['subject_id', 'topic_id', 'difficulty']);
            $table->index(['is_current_affairs', 'current_affair_date']);
            $table->index(['verification_status', 'is_published']);
        });

        Schema::create('exam_question', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('relevance_score')->default(100);
            $table->unique(['exam_id', 'question_id']);
            $table->index(['question_id', 'exam_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('exam_question');
        Schema::dropIfExists('questions');
    }
};
