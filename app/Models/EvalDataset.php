<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EvalDataset extends Model {
    protected $guarded=['id'];
    protected function casts(): array { return ['protocol'=>'array','manifest'=>'array','frozen_at'=>'datetime']; }
    protected static function booted(): void {
        static::updating(function(self $d) {
            if($d->getOriginal('status')!=='draft' || $d->isDirty(['assessment_id','created_by','name','data_kind','protocol','creation_key','request_hash'])) throw new \LogicException('Protokol evaluasi dikunci.');
        });
        static::deleting(fn()=>throw new \LogicException('Dataset evaluasi tidak dapat dihapus.'));
    }
    public function assessment() { return $this->belongsTo(Assessment::class); }
    public function cases() { return $this->hasMany(EvalCase::class); }
    public function experiments() { return $this->hasMany(EvalExperiment::class); }
}
