<?php
namespace App\Models;
class BankQuestionLink extends EvalRecord {
    protected function casts(): array { return ['source_snapshot'=>'array','created_at'=>'datetime']; }
    public function question() { return $this->belongsTo(Question::class); }
}
