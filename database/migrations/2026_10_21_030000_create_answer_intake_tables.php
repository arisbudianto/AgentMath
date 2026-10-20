<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('answer_import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->uuid('creation_key')->unique();
            $table->string('file_hash', 64);
            $table->string('status', 20)->default('preview');
            $table->json('rows')->nullable();
            $table->unsignedInteger('row_count');
            $table->unsignedInteger('imported_count')->default(0);
            $table->timestamp('expires_at')->index();
            $table->timestamp('committed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->restrictOnDelete();
            $table->foreignId('question_id')->constrained()->restrictOnDelete();
            $table->foreignId('participant_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->uuid('creation_key')->unique();
            $table->unsignedInteger('current_version')->default(0);
            $table->timestamps();
            $table->unique(['assessment_id','question_id','participant_id'], 'answers_target_unique');
        });
        Schema::create('answer_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('answer_id')->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('import_batch_id')->nullable()->constrained('answer_import_batches')->restrictOnDelete();
            $table->unsignedInteger('version');
            $table->string('content_type', 10);
            $table->string('origin', 20);
            $table->longText('answer_text')->nullable();
            $table->string('photo_path')->nullable();
            $table->string('photo_mime', 30)->nullable();
            $table->string('content_hash', 64);
            $table->string('participant_code_snapshot', 40);
            $table->json('question_snapshot');
            $table->string('source_hash', 64);
            $table->string('reason', 500);
            $table->timestamps();
            $table->unique(['answer_id','version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('answer_revisions');
        Schema::dropIfExists('answers');
        Schema::dropIfExists('answer_import_batches');
    }
};
