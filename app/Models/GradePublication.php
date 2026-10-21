<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GradePublication extends Model {
    public const UPDATED_AT=null;
    protected $guarded=['id'];
    protected function casts(): array { return ['snapshot'=>'array','version'=>'integer','created_at'=>'datetime']; }
    protected static function booted(): void {
        static::updating(fn()=>throw new \LogicException('Publikasi dikunci; buat peristiwa baru.'));
        static::deleting(fn()=>throw new \LogicException('Riwayat publikasi tidak dapat dihapus.'));
    }
}
