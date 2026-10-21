<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('eval_datasets',function(Blueprint $t) {
            $t->id(); $t->foreignId('assessment_id')->constrained()->restrictOnDelete(); $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('name',160); $t->string('data_kind',20); $t->json('protocol'); $t->string('status',20)->default('draft');
            $t->uuid('creation_key')->unique(); $t->string('request_hash',64); $t->json('manifest')->nullable(); $t->string('manifest_hash',64)->nullable(); $t->timestamp('frozen_at')->nullable(); $t->timestamps();
        });
        Schema::create('eval_cases',function(Blueprint $t) {
            $t->id(); $t->foreignId('eval_dataset_id')->constrained()->restrictOnDelete(); $t->foreignId('answer_id')->constrained()->restrictOnDelete();
            $t->foreignId('answer_revision_id')->constrained()->restrictOnDelete(); $t->foreignId('answer_transcription_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('question_id')->constrained()->restrictOnDelete(); $t->foreignId('rubric_version_id')->constrained()->restrictOnDelete();
            $t->string('split',12); $t->json('input_snapshot'); $t->string('input_hash',64); $t->uuid('creation_key')->unique(); $t->string('request_hash',64);
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete(); $t->timestamp('created_at'); $t->unique(['eval_dataset_id','answer_revision_id']);
        });
        Schema::create('eval_references',function(Blueprint $t) {
            $t->id(); $t->foreignId('eval_case_id')->constrained()->restrictOnDelete(); $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->unsignedInteger('version'); $t->json('criteria'); $t->decimal('total_score',8,2); $t->text('reason'); $t->boolean('independent');
            $t->unsignedInteger('label_seconds')->nullable(); $t->string('reference_hash',64); $t->uuid('creation_key')->unique(); $t->string('request_hash',64); $t->timestamp('created_at'); $t->unique(['eval_case_id','version']);
        });
        Schema::create('eval_experiments',function(Blueprint $t) {
            $t->id(); $t->foreignId('eval_dataset_id')->constrained()->restrictOnDelete(); $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('split',12); $t->string('protocol_version',40); $t->json('config_snapshot'); $t->boolean('test_reused')->default(false);
            $t->uuid('creation_key')->unique(); $t->string('request_hash',64); $t->timestamp('cancelled_at')->nullable(); $t->timestamp('created_at');
        });
        Schema::create('eval_samples',function(Blueprint $t) {
            $t->id(); $t->foreignId('eval_experiment_id')->constrained()->restrictOnDelete(); $t->foreignId('eval_case_id')->constrained()->restrictOnDelete();
            $t->foreignId('eval_reference_id')->constrained()->restrictOnDelete(); $t->foreignId('multi_run_id')->nullable()->constrained('grading_runs')->restrictOnDelete();
            $t->json('multi_result')->nullable(); $t->json('multi_metadata')->nullable(); $t->string('status',24); $t->unsignedInteger('generation')->default(1);
            $t->uuid('lease_token')->nullable(); $t->timestamp('lease_until')->nullable(); $t->timestamp('queued_at')->nullable(); $t->timestamp('finished_at')->nullable(); $t->timestamps();
            $t->unique(['eval_experiment_id','eval_case_id']);
        });
        Schema::create('eval_attempts',function(Blueprint $t) {
            $t->id(); $t->foreignId('eval_sample_id')->constrained()->restrictOnDelete(); $t->unsignedInteger('number'); $t->uuid('lease_token')->unique();
            $t->string('status',20); $t->json('output')->nullable(); $t->string('error_code',60)->nullable(); $t->timestamp('started_at'); $t->timestamp('finished_at')->nullable(); $t->timestamps(); $t->unique(['eval_sample_id','number']);
        });
        Schema::create('eval_observations',function(Blueprint $t) {
            $t->id(); $t->foreignId('eval_sample_id')->constrained()->restrictOnDelete(); $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->string('method',12); $t->unsignedInteger('version'); $t->unsignedInteger('review_seconds')->nullable(); $t->string('category',30); $t->text('note');
            $t->uuid('creation_key')->unique(); $t->string('request_hash',64); $t->timestamp('created_at'); $t->unique(['eval_sample_id','method','version']);
        });
    }
    public function down(): void { foreach(['eval_observations','eval_attempts','eval_samples','eval_experiments','eval_references','eval_cases','eval_datasets'] as $table) Schema::dropIfExists($table); }
};
