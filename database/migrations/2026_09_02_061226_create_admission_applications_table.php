<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admission_applications', function (Blueprint $table) {
            $table->id();

            $table->string('application_no')->unique();
            $table->foreignId('exam_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('package_id')->nullable()->constrained()->nullOnDelete();

            $table->string('name');
            $table->string('father_name')->nullable();
            $table->string('mother_name')->nullable();

            $table->string('email')->index();
            $table->string('phone', 20)->index();

            $table->date('date_of_birth');
            $table->string('gender', 20);

            $table->text('address');
            $table->string('city');
            $table->string('district');
            $table->string('state');
            $table->string('pincode', 10);

            $table->string('photo_path');

            $table->timestamp('email_verified_at')->nullable();

            $table->enum('status', [
                'draft',
                'submitted',
                'approved',
                'rejected'
            ])->default('draft')->index();

            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();

            $table->foreignId('created_user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->index(['email', 'status']);
            $table->index(['phone', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admission_applications');
    }
};
