<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EvalSample extends Model {
    protected $guarded=['id'];
    protected function casts(): array { return ['multi_result'=>'array','multi_metadata'=>'array','generation'=>'integer','lease_until'=>'datetime','queued_at'=>'datetime','finished_at'=>'datetime']; }
    protected static function booted(): void {
        static::updating(function(self $s) { if($s->isDirty(['eval_experiment_id','eval_case_id','eval_reference_id','multi_run_id','multi_result','multi_metadata'])) throw new \LogicException('Dasar sampel dikunci.'); });
        static::deleting(fn()=>throw new \LogicException('Sampel evaluasi tidak dapat dihapus.'));
    }
    public function experiment() { return $this->belongsTo(EvalExperiment::class,'eval_experiment_id'); }
    public function case() { return $this->belongsTo(EvalCase::class,'eval_case_id'); }
    public function reference() { return $this->belongsTo(EvalReference::class,'eval_reference_id'); }
    public function attempts() { return $this->hasMany(EvalAttempt::class)->orderBy('number'); }
    public function observations() { return $this->hasMany(EvalObservation::class); }
}
