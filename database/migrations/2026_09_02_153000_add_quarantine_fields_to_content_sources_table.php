<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_sources', function (Blueprint $table) {
            $table->boolean('is_quarantined')->default(false)->index()->after('is_active');
            $table->timestamp('quarantined_at')->nullable()->after('is_quarantined');
            $table->string('quarantine_reason', 100)->nullable()->after('quarantined_at');
        });
    }

    public function down(): void
    {
        Schema::table('content_sources', function (Blueprint $table) {
            $table->dropIndex(['is_quarantined']);
            $table->dropColumn(['is_quarantined', 'quarantined_at', 'quarantine_reason']);
        });
    }
};
