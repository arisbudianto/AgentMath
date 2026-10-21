<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class EvalAttempt extends Model {
    protected $guarded=['id'];
    protected function casts(): array { return ['output'=>'array','number'=>'integer','started_at'=>'datetime','finished_at'=>'datetime']; }
    protected static function booted(): void {
        static::updating(function(self $a) { if($a->getOriginal('status')!=='running') throw new \LogicException('Hasil evaluasi dikunci.'); });
        static::deleting(fn()=>throw new \LogicException('Percobaan evaluasi tidak dapat dihapus.'));
    }
}
