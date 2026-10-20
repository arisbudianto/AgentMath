<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rubric_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('rubric_versions')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->uuid('creation_key')->unique();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->string('status', 20)->default('draft');
            $table->string('origin', 20);
            $table->unsignedInteger('revision')->default(1);
            $table->json('source_snapshot');
            $table->string('source_hash', 64);
            $table->json('rules');
            $table->json('criteria');
            $table->text('grading_policy');
            $table->json('ai_metadata')->nullable();
            $table->timestamps();
            $table->unique(['question_id', 'version']);
        });
    }

    public function down(): void { Schema::dropIfExists('rubric_versions'); }
};
