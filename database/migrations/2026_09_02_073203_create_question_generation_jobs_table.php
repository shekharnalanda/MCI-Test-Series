<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_generation_jobs', function (Blueprint $table) {
            $table->id();

            $table->string('job_code')->unique();

            $table->foreignId('exam_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('subject_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('topic_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->unsignedInteger('target_count')->default(100);
            $table->unsignedInteger('generated_count')->default(0);
            $table->unsignedInteger('accepted_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);

            $table->string('difficulty', 20)->default('mixed');
            $table->string('language', 20)->default('bilingual');

            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'partial',
                'failed'
            ])->default('pending')->index();

            $table->unsignedTinyInteger('priority')->default(50)->index();

            $table->json('generation_rules')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->text('error_message')->nullable();

            $table->timestamps();

            $table->index(
                ['status','priority'],
                'question_generation_queue_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_generation_jobs');
    }
};
