<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('learning_paths',function(Blueprint $t) {
            $t->id(); $t->foreignId('classroom_id')->unique()->constrained()->restrictOnDelete();
            $t->foreignId('bank_book_id')->constrained('bank_books')->restrictOnDelete();
            $t->foreignId('pre_assessment_id')->constrained('assessments')->restrictOnDelete();
            $t->foreignId('post_assessment_id')->constrained('assessments')->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete(); $t->timestamps();
        });
        Schema::create('learning_stage_completions',function(Blueprint $t) {
            $t->id(); $t->foreignId('learning_path_id')->constrained()->restrictOnDelete();
            $t->foreignId('participant_id')->constrained()->restrictOnDelete();
            $t->unsignedTinyInteger('stage'); $t->foreignId('student_task_id')->nullable()->constrained()->restrictOnDelete();
            $t->timestamp('created_at'); $t->unique(['learning_path_id','participant_id','stage'],'learning_stage_once');
        });
        Schema::create('learning_page_visits',function(Blueprint $t) {
            $t->id(); $t->foreignId('learning_path_id')->constrained()->restrictOnDelete();
            $t->foreignId('participant_id')->constrained()->restrictOnDelete();
            $t->foreignId('bank_page_id')->constrained('bank_pages')->restrictOnDelete();
            $t->timestamp('created_at'); $t->unique(['learning_path_id','participant_id','bank_page_id'],'learning_page_once');
        });
    }
    public function down(): void { Schema::dropIfExists('learning_page_visits'); Schema::dropIfExists('learning_stage_completions'); Schema::dropIfExists('learning_paths'); }
};
