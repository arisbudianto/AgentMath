<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AnswerTranscription extends Model
{
    protected $guarded=['id'];
    protected function casts(): array { return ['version'=>'integer','uncertainties'=>'array','ai_metadata'=>'array','confirmed_at'=>'datetime']; }
    protected static function booted(): void {
        static::updating(fn()=>throw new \LogicException('Transkripsi dikunci; simpan versi baru.'));
        static::deleting(fn()=>throw new \LogicException('Riwayat transkripsi tidak dapat dihapus melalui model.'));
    }
    public function revision() { return $this->belongsTo(AnswerRevision::class,'answer_revision_id'); }
    public function creator() { return $this->belongsTo(User::class,'created_by'); }
}
