<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StudentTask extends Model {
    public const UPDATED_AT=null; protected $guarded=['id'];
    protected function casts(): array { return ['snapshot'=>'array','version'=>'integer','closes_at'=>'datetime','created_at'=>'datetime']; }
    protected static function booted(): void { static::updating(fn()=>throw new \LogicException('Versi tugas dikunci.')); static::deleting(fn()=>throw new \LogicException('Riwayat tugas dikunci.')); }
    public function assessment() { return $this->belongsTo(Assessment::class); }
}
