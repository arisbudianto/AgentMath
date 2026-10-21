<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void { Schema::create('grade_publications',function(Blueprint $t) {
        $t->id(); $t->foreignId('answer_id')->constrained()->restrictOnDelete(); $t->foreignId('grade_review_id')->nullable()->constrained()->restrictOnDelete();
        $t->foreignId('created_by')->constrained('users')->restrictOnDelete(); $t->unsignedInteger('version'); $t->string('action',12);
        $t->json('snapshot')->nullable(); $t->string('snapshot_hash',64)->nullable(); $t->text('message')->nullable();
        $t->uuid('creation_key')->unique(); $t->string('request_hash',64); $t->timestamp('created_at'); $t->unique(['answer_id','version']);
    }); }
    public function down(): void { Schema::dropIfExists('grade_publications'); }
};
