<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('classrooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->string('name', 120);
            $table->unsignedTinyInteger('grade');
            $table->string('school_year', 30);
            $table->timestamps();
            $table->index(['owner_id', 'created_at']);
        });
        Schema::create('participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('classroom_id')->constrained()->restrictOnDelete();
            $table->string('code', 40);
            $table->timestamps();
            $table->unique(['classroom_id', 'code']);
        });
        Schema::table('assessments', function (Blueprint $table) {
            // Nullable so existing M00 assessments remain accessible as incomplete drafts.
            $table->foreignId('classroom_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('instructions')->nullable();
        });
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->text('prompt');
            $table->text('answer_key');
            $table->text('scoring_guidelines');
            $table->decimal('max_score', 8, 2);
            $table->timestamps();
            $table->unique(['assessment_id', 'number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
        Schema::table('assessments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('classroom_id');
            $table->dropColumn('instructions');
        });
        Schema::dropIfExists('participants');
        Schema::dropIfExists('classrooms');
    }
};
