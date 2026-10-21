<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('grading_runs',function(Blueprint $t) {
            $t->id(); $t->foreignId('assessment_id')->constrained()->restrictOnDelete();
            $t->foreignId('answer_id')->constrained()->restrictOnDelete();
            $t->foreignId('answer_revision_id')->constrained()->restrictOnDelete();
            $t->foreignId('answer_transcription_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('rubric_version_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->uuid('creation_key')->unique(); $t->string('input_hash',64); $t->json('input_snapshot'); $t->json('config_snapshot');
            $t->string('status',30); $t->string('stage',30); $t->unsignedInteger('generation')->default(1);
            $t->timestamp('lease_until')->nullable()->index(); $t->uuid('lease_token')->nullable();
            $t->timestamp('queued_at'); $t->timestamp('finished_at')->nullable(); $t->timestamps();
            $t->index(['assessment_id','status']);
        });
        Schema::create('grading_attempts',function(Blueprint $t) {
            $t->id(); $t->foreignId('grading_run_id')->constrained()->restrictOnDelete();
            $t->string('stage',30); $t->unsignedInteger('number'); $t->uuid('lease_token')->unique();
            $t->string('status',20); $t->json('output')->nullable(); $t->string('error_code',60)->nullable();
            $t->timestamp('started_at'); $t->timestamp('finished_at')->nullable(); $t->timestamps();
            $t->unique(['grading_run_id','stage','number']);
        });
        Schema::create('grading_events',function(Blueprint $t) {
            $t->id(); $t->foreignId('grading_run_id')->constrained()->restrictOnDelete();
            $t->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $t->string('code',60); $t->string('stage',30); $t->timestamp('created_at');
        });
        Schema::create('agentmath_jobs',function(Blueprint $t) {
            $t->bigIncrements('id'); $t->string('queue')->index(); $t->longText('payload'); $t->unsignedTinyInteger('attempts');
            $t->unsignedInteger('reserved_at')->nullable(); $t->unsignedInteger('available_at'); $t->unsignedInteger('created_at');
        });
        Schema::create('failed_jobs',function(Blueprint $t) {
            $t->id(); $t->string('uuid')->unique(); $t->text('connection'); $t->text('queue'); $t->longText('payload'); $t->longText('exception'); $t->timestamp('failed_at')->useCurrent();
        });
    }
    public function down(): void { foreach(['failed_jobs','agentmath_jobs','grading_events','grading_attempts','grading_runs'] as $t) Schema::dropIfExists($t); }
};
