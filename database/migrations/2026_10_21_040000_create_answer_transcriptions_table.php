<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('answer_transcriptions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('answer_revision_id')->constrained()->restrictOnDelete();
            $t->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $t->uuid('creation_key')->unique();
            $t->string('request_hash',64);
            $t->unsignedInteger('version');
            $t->string('status',20);
            $t->string('origin',20);
            $t->longText('transcript_text');
            $t->json('uncertainties');
            $t->json('ai_metadata')->nullable();
            $t->string('reason',500);
            $t->timestamp('confirmed_at')->nullable();
            $t->timestamps();
            $t->unique(['answer_revision_id','version']);
        });
    }
    public function down(): void { Schema::dropIfExists('answer_transcriptions'); }
};
