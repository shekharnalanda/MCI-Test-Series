<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('test_series', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('name_hi')->nullable();
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            $table->enum('series_type', [
                'demo',
                'topic',
                'practice',
                'full_mock',
                'previous_year',
                'current_affairs',
                'special'
            ])->default('practice')->index();

            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedInteger('validity_days')->nullable();
            $table->unsignedInteger('test_limit')->nullable();

            $table->boolean('is_free')->default(false)->index();
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_active')->default(true)->index();

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('test_series');
    }
};
