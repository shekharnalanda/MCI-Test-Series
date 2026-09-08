<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::table('tests', function (Blueprint $table) { $table->string('answer_visibility', 20)->default('immediate')->index()->after('available_until'); $table->timestamp('answers_available_at')->nullable()->index()->after('answer_visibility'); }); }
    public function down(): void { Schema::table('tests', function (Blueprint $table) { $table->dropColumn(['answer_visibility', 'answers_available_at']); }); }
};
