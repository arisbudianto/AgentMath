<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('answer_diagnoses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('answer_id')->constrained()->restrictOnDelete();
            $t->foreignId('grade_review_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('answer_diagnoses')->restrictOnDelete();
            $t->unsignedInteger('version');
            $t->uuid('creation_key')->unique();
            $t->string('status', 20); // draft | approved | rejected
            $t->string('source', 10); // ai | teacher
            $t->json('items'); // [{criterion_id, category, explanation, recommended_action}]
            $t->text('reason')->nullable();
            $t->timestamp('approved_at')->nullable();
            $t->timestamp('created_at');
            $t->unique(['answer_id', 'version']);
            $t->index(['grade_review_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answer_diagnoses');
    }
};
