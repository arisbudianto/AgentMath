<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};
return new class extends Migration {
    public function up(): void {
        Schema::table('users',fn(Blueprint $t)=>$t->enum('role',['admin','teacher','student'])->default('teacher')->change());
        Schema::create('student_profiles',function(Blueprint $t) {
            $t->id(); $t->foreignId('user_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('participant_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete(); $t->timestamps();
        });
    }
    public function down(): void {
        if(DB::table('users')->where('role','student')->exists()) throw new RuntimeException('Akun siswa masih ada; rollback dibatalkan tanpa menghapus data.');
        Schema::dropIfExists('student_profiles');
        Schema::table('users',fn(Blueprint $t)=>$t->enum('role',['admin','teacher'])->default('teacher')->change());
    }
};
