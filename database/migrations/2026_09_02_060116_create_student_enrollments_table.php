<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('student_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();

            $table->decimal('fee_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('paid_amount', 10, 2)->default(0);

            $table->unsignedInteger('test_limit')->nullable();
            $table->unsignedInteger('tests_used')->default(0);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();

            $table->enum('payment_status', [
                'unpaid',
                'partial',
                'paid',
                'waived'
            ])->default('unpaid')->index();

            $table->enum('status', [
                'pending',
                'active',
                'expired',
                'suspended',
                'cancelled'
            ])->default('pending')->index();

            $table->timestamps();

            $table->index(['student_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_enrollments');
    }
};
