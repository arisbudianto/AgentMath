<?php
namespace App\Models;
class EvalReference extends EvalRecord {
    protected function casts(): array { return ['criteria'=>'array','version'=>'integer','independent'=>'boolean','label_seconds'=>'integer','total_score'=>'decimal:2']; }
    public function case() { return $this->belongsTo(EvalCase::class,'eval_case_id'); }
}
