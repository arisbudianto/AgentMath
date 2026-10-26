<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('question_import_batches', function (Blueprint $table) {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('question_import_batches');
    }
};
