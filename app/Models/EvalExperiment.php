<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EvalExperiment extends Model {
    public const UPDATED_AT=null; protected $guarded=['id'];
    protected function casts(): array { return ['config_snapshot'=>'array','test_reused'=>'boolean','cancelled_at'=>'datetime','created_at'=>'datetime']; }
    protected static function booted(): void {
        static::updating(function(self $e) { if(array_diff(array_keys($e->getDirty()),['cancelled_at'])) throw new \LogicException('Dasar eksperimen dikunci.'); });
        static::deleting(fn()=>throw new \LogicException('Eksperimen tidak dapat dihapus.'));
    }
    public function dataset() { return $this->belongsTo(EvalDataset::class,'eval_dataset_id'); }
    public function samples() { return $this->hasMany(EvalSample::class); }
}
