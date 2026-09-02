<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('test_attempts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('test_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('attempt_number')->default(1);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedInteger('time_taken_seconds')->default(0);

            $table->unsignedInteger('total_questions')->default(0);
            $table->unsignedInteger('attempted_questions')->default(0);
            $table->unsignedInteger('correct_answers')->default(0);
            $table->unsignedInteger('wrong_answers')->default(0);
            $table->unsignedInteger('unanswered')->default(0);

            $table->decimal('maximum_marks', 10, 2)->default(0);
            $table->decimal('obtained_marks', 10, 2)->default(0);
            $table->decimal('percentage', 7, 2)->default(0);

            $table->unsignedInteger('rank')->nullable();
            $table->decimal('percentile', 7, 2)->nullable();

            $table->enum('status', [
                'started',
                'submitted',
                'evaluated',
                'cancelled'
            ])->default('started')->index();

            $table->json('analytics')->nullable();

            $table->timestamps();

            $table->index(['student_profile_id', 'test_id']);
            $table->index(['test_id', 'obtained_marks']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('test_attempts');
    }
};
