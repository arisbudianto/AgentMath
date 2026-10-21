<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('research_annotations',function(Blueprint $t) {
        $t->id(); $t->foreignId('eval_case_id')->constrained()->restrictOnDelete(); $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
        $t->foreignId('eval_reference_id')->nullable()->constrained()->restrictOnDelete(); $t->string('kind',20); $t->unsignedInteger('version');
        $t->string('rater_code',40)->nullable(); $t->json('data'); $t->string('content_hash',64); $t->uuid('creation_key')->unique(); $t->string('request_hash',64); $t->timestamp('created_at');
        $t->unique(['eval_case_id','kind','version'],'research_annotation_version');
    }); }
    public function down(): void { Schema::dropIfExists('research_annotations'); }
};
