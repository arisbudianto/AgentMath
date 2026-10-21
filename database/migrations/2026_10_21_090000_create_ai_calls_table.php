<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('ai_calls',function(Blueprint $t) {
            $t->id(); $t->foreignId('assessment_id')->constrained()->restrictOnDelete();
            $t->string('kind',20); $t->unsignedBigInteger('source_id'); $t->uuid('correlation_id');
            $t->string('stage',80); $t->string('provider',30); $t->string('model',160); $t->string('prompt_version',100);
            $t->string('state',24)->default('in_flight'); $t->string('outcome',24)->default('pending');
            $t->unsignedSmallInteger('http_status')->nullable(); $t->unsignedBigInteger('duration_ms')->nullable();
            $t->unsignedBigInteger('input_tokens')->nullable(); $t->unsignedBigInteger('output_tokens')->nullable(); $t->unsignedBigInteger('cached_tokens')->nullable();
            $t->json('tariff')->nullable(); $t->string('currency',3)->nullable(); $t->decimal('estimated_cost',24,12)->nullable();
            $t->timestamp('started_at'); $t->timestamp('finished_at')->nullable();
            $t->index(['assessment_id','started_at']); $t->index(['kind','source_id']); $t->index('correlation_id');
        });
    }
    public function down(): void { Schema::dropIfExists('ai_calls'); }
};
