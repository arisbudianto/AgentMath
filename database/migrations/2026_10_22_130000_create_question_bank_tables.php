<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('bank_books',function(Blueprint $t) { $t->id(); $t->string('code',80)->unique(); $t->string('title'); $t->string('authors'); $t->string('edition',100); $t->string('isbn',40); $t->unsignedTinyInteger('grade'); $t->string('source_hash',64); $t->string('manifest_hash',64); $t->timestamp('created_at'); });
        Schema::create('bank_chapters',function(Blueprint $t) { $t->id(); $t->foreignId('bank_book_id')->constrained()->restrictOnDelete(); $t->unsignedTinyInteger('number'); $t->string('title'); $t->unsignedSmallInteger('start_page'); $t->unsignedSmallInteger('end_page'); $t->text('objectives'); $t->unique(['bank_book_id','number']); });
        Schema::create('bank_pages',function(Blueprint $t) { $t->id(); $t->foreignId('bank_book_id')->constrained()->restrictOnDelete(); $t->unsignedSmallInteger('number'); $t->unsignedSmallInteger('pdf_page'); $t->text('source_text'); $t->string('image_hash',64); $t->string('content_hash',64); $t->unique(['bank_book_id','number']); });
        Schema::create('bank_sets',function(Blueprint $t) { $t->id(); $t->foreignId('bank_chapter_id')->constrained()->restrictOnDelete(); $t->string('code',100)->unique(); $t->string('kind',30); $t->string('title'); $t->unsignedSmallInteger('start_page'); $t->unsignedSmallInteger('end_page'); $t->text('source_text'); $t->string('content_hash',64); $t->index(['bank_chapter_id','kind']); });
        Schema::create('bank_question_links',function(Blueprint $t) { $t->id(); $t->foreignId('question_id')->unique()->constrained()->restrictOnDelete(); $t->foreignId('bank_set_id')->constrained()->restrictOnDelete(); $t->foreignId('created_by')->constrained('users')->restrictOnDelete(); $t->uuid('creation_key')->unique(); $t->string('request_hash',64); $t->json('source_snapshot'); $t->timestamp('created_at'); });
    }
    public function down(): void { foreach(['bank_question_links','bank_sets','bank_pages','bank_chapters','bank_books'] as $t) Schema::dropIfExists($t); }
};
