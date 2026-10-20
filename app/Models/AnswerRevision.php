<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnswerRevision extends Model
{
    protected $guarded = ['id'];
    protected $hidden = ['photo_path'];
    protected function casts(): array { return ['question_snapshot'=>'array','version'=>'integer']; }
    protected static function booted(): void
    {
        static::updating(fn()=>throw new \LogicException('Revisi jawaban dikunci; buat revisi baru.'));
        static::deleting(fn()=>throw new \LogicException('Riwayat jawaban tidak dapat dihapus melalui model.'));
    }
    public function answer() { return $this->belongsTo(Answer::class); }
    public function transcriptions() { return $this->hasMany(AnswerTranscription::class)->orderByDesc('version'); }
    public function latestTranscription() { return $this->hasOne(AnswerTranscription::class)->ofMany('version','max'); }
}
