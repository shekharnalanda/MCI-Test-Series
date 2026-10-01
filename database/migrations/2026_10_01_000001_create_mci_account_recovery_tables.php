<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mci_recovery_limits', function (Blueprint $t) {
            $t->string('key', 64)->primary();
            $t->unsignedBigInteger('sent_at');
        });
        Schema::create('mci_recovery_challenges', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->string('email');
            $t->string('purpose', 32);
            $t->string('code_hash');
            $t->text('payload');
            $t->string('session_hash', 64);
            $t->unsignedTinyInteger('attempts')->default(0);
            $t->unsignedBigInteger('expires_at')->index();
            $t->unsignedBigInteger('consumed_at')->nullable();
        });
        Schema::create('mci_recovery_contacts', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('user_id')->unique();
            $t->string('email')->index();
            $t->timestamp('verified_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mci_recovery_contacts');
        Schema::dropIfExists('mci_recovery_challenges');
        Schema::dropIfExists('mci_recovery_limits');
    }
};
