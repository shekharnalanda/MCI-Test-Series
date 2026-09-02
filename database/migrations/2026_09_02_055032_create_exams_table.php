<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('name_hi')->nullable();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('conducting_body')->nullable();
            $table->string('official_url')->nullable();
            $table->json('pattern')->nullable();
            $table->json('syllabus')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('exams');
    }
};
