<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('competencies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $t->string('name', 120);
            $t->text('description')->nullable();
            $t->timestamps();
            $t->unique(['owner_id', 'name']);
        });
        Schema::table('questions', function (Blueprint $t) {
            // Nullable and optional: tagging a question with a competency is opt-in, so
            // existing questions remain valid without a migration-time backfill.
            $t->foreignId('competency_id')->nullable()->after('max_score')->constrained()->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('questions', function (Blueprint $t) {
            $t->dropConstrainedForeignId('competency_id');
        });
        Schema::dropIfExists('competencies');
    }
};
