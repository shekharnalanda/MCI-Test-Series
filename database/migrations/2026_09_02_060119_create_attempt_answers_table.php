<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attempt_answers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('test_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('selected_option_id')
                ->nullable()
                ->constrained('question_options')
                ->nullOnDelete();

            $table->json('selected_option_ids')->nullable();
            $table->text('numerical_answer')->nullable();

            $table->boolean('is_correct')->nullable()->index();
            $table->decimal('marks_awarded', 8, 2)->default(0);
            $table->unsignedInteger('time_spent_seconds')->default(0);

            $table->boolean('is_marked_for_review')->default(false);

            $table->timestamps();

            $table->unique(['test_attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_answers');
    }
};
