<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('student_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('student_code')->unique();
            $table->string('phone', 20)->nullable()->index();
            $table->string('photo_path')->nullable();

            $table->date('date_of_birth')->nullable();
            $table->string('gender', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('city')->nullable();
            $table->string('district')->nullable();
            $table->string('state')->nullable();
            $table->string('pincode', 10)->nullable();

            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('admission_approved_at')->nullable();

            $table->enum('status', [
                'pending',
                'active',
                'inactive',
                'suspended'
            ])->default('pending')->index();

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('student_profiles');
    }
};
