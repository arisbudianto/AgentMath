<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GradingRun extends Model
{
    protected $guarded=['id'];
    protected function casts(): array { return ['input_snapshot'=>'array','config_snapshot'=>'array','generation'=>'integer','queued_at'=>'datetime','lease_until'=>'datetime','finished_at'=>'datetime']; }
    protected static function booted(): void {
        static::updating(function(self $run) {
            foreach(['assessment_id','answer_id','answer_revision_id','answer_transcription_id','rubric_version_id','created_by','creation_key','input_hash','input_snapshot','config_snapshot'] as $field) if($run->isDirty($field)) throw new \LogicException('Dasar proses dikunci; buat proses baru.');
        });
        static::deleting(fn()=>throw new \LogicException('Riwayat proses tidak dapat dihapus melalui model.'));
    }
    public function assessment() { return $this->belongsTo(Assessment::class); }
    public function answer() { return $this->belongsTo(Answer::class); }
    public function revision() { return $this->belongsTo(AnswerRevision::class,'answer_revision_id'); }
    public function rubric() { return $this->belongsTo(RubricVersion::class,'rubric_version_id'); }
    public function attempts() { return $this->hasMany(GradingAttempt::class)->orderBy('id'); }
    public function events() { return $this->hasMany(GradingEvent::class)->orderByDesc('id'); }
    public function label(): string { return self::labels()[$this->status] ?? $this->status; }
    public static function labels(): array { return ['queued'=>'Dalam antrean','running'=>'Sedang diproses','awaiting_agent'=>'Menunggu agent penilaian','failed'=>'Gagal, perlu pemeriksaan','stale'=>'Dasar proses sudah berubah','cancelled'=>'Dibatalkan','completed'=>'Tahapan AI selesai']; }
}
