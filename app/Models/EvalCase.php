<?php
namespace App\Models;
class EvalCase extends EvalRecord {
    protected function casts(): array { return ['input_snapshot'=>'array']; }
    public function dataset() { return $this->belongsTo(EvalDataset::class,'eval_dataset_id'); }
    public function references() { return $this->hasMany(EvalReference::class)->orderByDesc('version'); }
    public function latestReference() { return $this->hasOne(EvalReference::class)->ofMany('version','max'); }
}
