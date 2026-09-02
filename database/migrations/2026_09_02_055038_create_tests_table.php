<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_series_id')->nullable()->constrained('test_series')->cascadeOnDelete();
            $table->foreignId('exam_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subject_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title');
            $table->string('title_hi')->nullable();
            $table->text('instructions')->nullable();

            $table->enum('test_type', [
                'demo',
                'topic',
                'practice',
                'full_mock',
                'previous_year',
                'current_affairs',
                'special'
            ])->default('practice')->index();

            $table->unsignedInteger('total_questions')->default(0);
            $table->unsignedInteger('duration_minutes')->default(60);
            $table->decimal('positive_marks', 6, 2)->default(1);
            $table->decimal('negative_marks', 6, 2)->default(0);

            $table->boolean('randomize_questions')->default(true);
            $table->boolean('randomize_options')->default(true);
            $table->boolean('auto_generated')->default(false)->index();
            $table->boolean('is_demo')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();

            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();

            $table->json('generation_rules')->nullable();

            $table->timestamps();
        });

        Schema::create('question_test', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->decimal('marks', 6, 2)->nullable();
            $table->decimal('negative_marks', 6, 2)->nullable();
            $table->unique(['test_id', 'question_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('question_test');
        Schema::dropIfExists('tests');
    }
};
