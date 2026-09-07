<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_enrollment_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_enrollment_id')->constrained('student_enrollments')->cascadeOnDelete();
            $table->foreignId('test_id')->constrained('tests')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['student_enrollment_id', 'test_id'], 'enrollment_test_unique');
        });
    }

    public function down(): void { Schema::dropIfExists('student_enrollment_tests'); }
};
