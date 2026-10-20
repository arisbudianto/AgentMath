<?php
namespace App\Services;

use App\Models\{Answer, AnswerRevision, AnswerTranscription, Assessment};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TranscriptionWorkflow
{
    public function save(Assessment $assessment, int $actor, int $answerId, int $revisionId, string $key, int $expected, string $action, string $text, string $reason, array $ai=[], bool $reviewed=false): AnswerTranscription
    {
        $text=app(AnswerIntake::class)->text($text);
        if (!in_array($action,['draft','confirm','ai'],true) || !filled($reason) || mb_strlen($reason)>500) $this->fail('Alasan perubahan wajib diisi, maksimal 500 karakter.');
        if ($action==='confirm' && preg_match('/\[(?:tidak terbaca|ragu|illegible|unclear)[^\]]*\]/iu',$text)) $this->fail('Selesaikan penanda bagian yang tidak terbaca sebelum konfirmasi.');
        $hash=hash('sha256',json_encode([$assessment->id,$actor,$answerId,$revisionId,$expected,$action,$text,$reason,$reviewed],JSON_THROW_ON_ERROR));
        return DB::transaction(function () use($assessment,$actor,$answerId,$revisionId,$key,$expected,$action,$text,$reason,$ai,$hash,$reviewed) {
            $locked=Assessment::lockForUpdate()->findOrFail($assessment->id);
            abort_unless((int)$locked->owner_id===$actor,403);
            $answer=$locked->answers()->lockForUpdate()->findOrFail($answerId);
            $revision=$answer->revisions()->findOrFail($revisionId);
            $existing=AnswerTranscription::where('creation_key',$key)->first();
            if ($existing) {
                if (!hash_equals($existing->request_hash,$hash)) $this->fail('Kunci permintaan sudah dipakai untuk perubahan lain.');
                return $existing;
            }
            if ($revision->content_type!=='photo' || $revision->version!==$answer->current_version) $this->fail('Transkripsi hanya dapat diubah pada foto jawaban terkini.');
            $latest=$revision->transcriptions()->first();
            if (($latest?->version ?? 0)!==$expected) $this->fail('Transkripsi berubah di tab lain. Muat ulang sebelum menyimpan.');
            if ($action==='confirm' && (!$latest || $latest->status!=='draft')) $this->fail('Simpan dan periksa draf sebelum konfirmasi.');
            if ($action==='confirm' && $text!==$latest->transcript_text) $this->fail('Simpan koreksi sebagai draf terlebih dahulu, lalu periksa kembali sebelum konfirmasi.');
            if ($action==='confirm' && !$reviewed) $this->fail('Konfirmasi pemeriksaan teks dan semua bagian meragukan diperlukan.');
            return $revision->transcriptions()->create([
                'created_by'=>$actor,'creation_key'=>$key,'request_hash'=>$hash,'version'=>$expected+1,
                'status'=>$action==='confirm'?'confirmed':'draft','origin'=>$action==='ai'?'ai':'teacher',
                'transcript_text'=>$text,'reason'=>$reason,
                'uncertainties'=>$action==='ai'?($ai['uncertainties'] ?? []):($latest?->uncertainties ?? []),
                'ai_metadata'=>$action==='ai'?($ai['metadata'] ?? null):null,
                'confirmed_at'=>$action==='confirm'?now():null,
            ]);
        });
    }

    // Future grading must use this gate, never arbitrary draft text or an older photo.
    public function readyText(Answer $answer): ?string
    {
        $answer=Answer::with(['latestRevision.latestTranscription','question.assessment'])->findOrFail($answer->id);
        $revision=$answer->latestRevision;
        if (!$revision || $revision->source_hash!==RubricValidator::sourceHash($answer->question->rubricSource())) return null;
        if ($revision->content_type==='text') return $revision->answer_text;
        $transcription=$revision->latestTranscription;
        return $transcription?->status==='confirmed' ? $transcription->transcript_text : null;
    }
    private function fail(string $message): never { throw ValidationException::withMessages(['transcription'=>$message]); }
}
