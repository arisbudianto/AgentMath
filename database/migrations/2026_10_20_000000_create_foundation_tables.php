<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id(); $table->string('name', 120); $table->string('email')->unique();
            $table->string('password'); $table->enum('role', ['admin', 'teacher'])->default('teacher');
            $table->boolean('is_active')->default(true); $table->rememberToken(); $table->timestamps();
        });
        Schema::create('assessments', function (Blueprint $table) {
            $table->id(); $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('title', 200); $table->timestamps(); $table->index(['owner_id', 'created_at']);
        });
    }
    public function down(): void { Schema::dropIfExists('assessments'); Schema::dropIfExists('users'); }
};
