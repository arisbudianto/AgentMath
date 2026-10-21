<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GradingAttempt extends Model
{
    protected $guarded=['id'];
    protected function casts(): array { return ['output'=>'array','started_at'=>'datetime','finished_at'=>'datetime','number'=>'integer']; }
    protected static function booted(): void {
        static::updating(function(self $entry) { if($entry->getOriginal('status')!=='running') throw new \LogicException('Hasil percobaan sudah dikunci.'); });
        static::deleting(fn()=>throw new \LogicException('Riwayat percobaan tidak dapat dihapus.'));
    }
}
