<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_practice_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('bridge_id', 32);
            $table->unsignedBigInteger('library_student_id');
            $table->unsignedBigInteger('library_user_id');
            $table->string('student_code');
            $table->string('student_name');
            $table->unsignedBigInteger('branch_id');
            $table->foreignId('student_profile_id')->unique()->constrained('student_profiles')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['bridge_id', 'library_student_id'], 'library_account_identity_unique');
        });
        Schema::create('library_practice_months', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_practice_account_id')->constrained()->restrictOnDelete();
            $table->string('period', 7);
            $table->string('terms_version');
            $table->timestamp('terms_accepted_at');
            $table->timestamps();
            $table->unique(['library_practice_account_id', 'period'], 'library_account_month_unique');
        });
        Schema::create('library_practice_selections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('library_practice_month_id')->constrained()->restrictOnDelete();
            $table->foreignId('test_id')->constrained()->restrictOnDelete();
            $table->foreignId('test_attempt_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['library_practice_month_id', 'test_id'], 'library_month_test_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_practice_selections');
        Schema::dropIfExists('library_practice_months');
        Schema::dropIfExists('library_practice_accounts');
    }
};
