<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('question_import_batches', function (Blueprint $table) {
            $table->id();

            $table->foreignId('content_source_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->string('batch_code')->unique();

            $table->enum('batch_type', [
                'manual',
                'csv',
                'json',
                'current_affairs',
                'generated',
                'migration'
            ])->index();

            $table->unsignedInteger('received_count')->default(0);
            $table->unsignedInteger('accepted_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);

            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'failed'
            ])->default('pending')->index();

            $table->json('metadata')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_import_batches');
    }
};
