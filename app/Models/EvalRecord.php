<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
abstract class EvalRecord extends Model {
    protected $guarded=['id'];
    public const UPDATED_AT=null;
    protected static function booted(): void {
        static::updating(fn()=>throw new \LogicException('Catatan evaluasi dikunci; buat versi baru.'));
        static::deleting(fn()=>throw new \LogicException('Riwayat evaluasi tidak dapat dihapus.'));
    }
}
