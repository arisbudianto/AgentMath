<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('student_tasks',function(Blueprint $t) {
            $t->id(); $t->foreignId('assessment_id')->constrained()->restrictOnDelete(); $t->foreignId('classroom_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete(); $t->unsignedInteger('version'); $t->string('action',10);
            $t->json('snapshot')->nullable(); $t->string('snapshot_hash',64)->nullable(); $t->timestamp('closes_at')->nullable();
            $t->uuid('creation_key')->unique(); $t->string('request_hash',64); $t->timestamp('created_at'); $t->unique(['assessment_id','version']);
        });
        Schema::create('student_submissions',function(Blueprint $t) {
            $t->id(); $t->foreignId('student_task_id')->constrained()->restrictOnDelete(); $t->foreignId('participant_id')->constrained()->restrictOnDelete();
            $t->foreignId('question_id')->constrained()->restrictOnDelete(); $t->foreignId('answer_revision_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete(); $t->uuid('creation_key')->unique(); $t->string('request_hash',64); $t->timestamp('created_at');
            $t->index(['student_task_id','participant_id','question_id'],'student_submission_scope');
        });
    }
    public function down(): void { Schema::dropIfExists('student_submissions'); Schema::dropIfExists('student_tasks'); }
};
