<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GradeReview extends Model {
    public const UPDATED_AT=null;
    protected $guarded=['id'];
    protected function casts(): array { return ['criteria'=>'array','version'=>'integer','total_score'=>'decimal:2','created_at'=>'datetime','approved_at'=>'datetime']; }
    protected static function booted(): void {
        static::updating(fn()=>throw new \LogicException('Keputusan dikunci; buat versi pemeriksaan baru.'));
        static::deleting(fn()=>throw new \LogicException('Riwayat keputusan tidak dapat dihapus.'));
    }
    public function run() { return $this->belongsTo(GradingRun::class,'grading_run_id'); }
    public function actor() { return $this->belongsTo(User::class,'created_by'); }
}
