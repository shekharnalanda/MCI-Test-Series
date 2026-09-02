<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('content_sources', function (Blueprint $table) {
            $table->id();

            $table->string('name');
            $table->string('slug')->unique();

            $table->enum('source_type', [
                'official',
                'government',
                'institutional',
                'open_knowledge',
                'internal',
                'manual'
            ])->default('manual')->index();

            $table->text('base_url')->nullable();
            $table->text('feed_url')->nullable();

            $table->unsignedTinyInteger('trust_score')->default(50)->index();

            $table->boolean('allow_current_affairs')->default(false);
            $table->boolean('allow_question_generation')->default(false);
            $table->boolean('auto_publish_allowed')->default(false);

            $table->string('license_note')->nullable();
            $table->text('usage_notes')->nullable();

            $table->boolean('is_active')->default(true)->index();

            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_success_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_sources');
    }
};
