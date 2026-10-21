<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('grade_reviews',function(Blueprint $t) {
            $t->id(); $t->foreignId('answer_id')->constrained()->restrictOnDelete();
            $t->foreignId('grading_run_id')->constrained()->restrictOnDelete();
            $t->foreignId('confidence_attempt_id')->constrained('grading_attempts')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('parent_id')->nullable()->constrained('grade_reviews')->restrictOnDelete();
            $t->foreignId('supersedes_approval_id')->nullable()->constrained('grade_reviews')->restrictOnDelete();
            $t->unsignedInteger('version'); $t->uuid('creation_key')->unique(); $t->string('request_hash',64);
            $t->string('status',20); $t->json('criteria'); $t->decimal('total_score',8,2);
            $t->text('reason'); $t->timestamp('approved_at')->nullable(); $t->timestamp('created_at');
            $t->unique(['answer_id','version']); $t->index(['grading_run_id','status']);
        });
    }
    public function down(): void { Schema::dropIfExists('grade_reviews'); }
};
