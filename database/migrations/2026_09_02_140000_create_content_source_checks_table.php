<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_source_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_source_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->boolean('healthy')->index();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('reason', 50)->index();
            $table->timestamp('checked_at')->index();
            $table->timestamps();

            $table->index(
                ['content_source_id', 'checked_at'],
                'source_check_history_index'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_source_checks');
    }
};
