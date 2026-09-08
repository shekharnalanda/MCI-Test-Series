<?php
use Illuminate\Database\Migrations\Migration; use Illuminate\Database\Schema\Blueprint; use Illuminate\Support\Facades\Schema;
return new class extends Migration { public function up():void{Schema::table('student_enrollment_tests',function(Blueprint $table){$table->boolean('selected_by_student')->default(false)->index();$table->timestamp('selected_at')->nullable();});} public function down():void{Schema::table('student_enrollment_tests',function(Blueprint $table){$table->dropColumn(['selected_by_student','selected_at']);});}};
