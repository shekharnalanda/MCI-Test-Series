<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attempt_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('test_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('question_order');
            $table->json('option_order')->nullable();
            $table->decimal('marks', 8, 2)->default(1);
            $table->decimal('negative_marks', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['test_attempt_id', 'question_id']);
            $table->unique(['test_attempt_id', 'question_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attempt_questions');
    }
};
