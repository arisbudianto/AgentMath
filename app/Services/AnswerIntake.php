<?php
namespace App\Services;

use App\Models\{Answer, Assessment};
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{DB, Storage, Validator};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AnswerIntake
{
    public function classroom(Assessment $assessment): void
    {
        if (!$assessment->classroom || $assessment->classroom->owner_id !== $assessment->owner_id) $this->fail('Hubungkan asesmen ke kelas milik Anda terlebih dahulu.');
    }

    public function text(string $text): string
    {
        Validator::make(['answer_text'=>$text], ['answer_text'=>'required|string|max:20000'])->validate();
        if (!mb_check_encoding($text,'UTF-8') || str_contains($text,"\0")) $this->fail('Jawaban harus berupa teks UTF-8 tanpa karakter NUL.');
        return str_replace(["\r\n","\r"],"\n",$text);
    }

    public function photo(UploadedFile $photo): string
    {
        Validator::make(['photo'=>$photo], ['photo'=>'required|file|max:5120'])->validate();
        $info=@getimagesize($photo->getRealPath());
        $extension=strtolower($photo->getClientOriginalExtension());
        $type=$info[2] ?? null;
        if (!$info || !in_array($type,[IMAGETYPE_JPEG,IMAGETYPE_PNG],true) || !in_array($extension,$type===IMAGETYPE_PNG?['png']:['jpg','jpeg'],true)) $this->fail('Foto harus JPG/JPEG atau PNG asli dengan ekstensi yang sesuai.');
        if ($info[0]<1 || $info[1]<1 || $info[0] > 6000 || $info[1] > 6000 || $info[0]*$info[1] > 20000000) $this->fail('Foto maksimal 6000 piksel per sisi dan 20 megapiksel.');
        return $type===IMAGETYPE_PNG?'image/png':'image/jpeg';
    }

    public function create(Assessment $assessment, int $actor, int $participant, int $question, string $key, array $payload): Answer
    {
        return $this->accept($assessment,$actor,$payload,['participant'=>$participant,'question'=>$question,'key'=>$key]);
    }

    public function revise(Assessment $assessment, int $actor, int $answer, int $expectedVersion, string $reason, array $payload): Answer
    {
        Validator::make(['reason'=>$reason], ['reason'=>'required|string|max:500'])->validate();
        return $this->accept($assessment,$actor,$payload,['answer'=>$answer,'expected'=>$expectedVersion,'reason'=>$reason]);
    }

    private function accept(Assessment $assessment, int $actor, array $payload, array $target): Answer
    {
        $photo=($payload['content_type'] ?? '') === 'photo';
        $text=$photo?null:$this->text($payload['answer_text'] ?? '');
        $mime=$photo?$this->photo($payload['photo']):null;
        $hash=hash('sha256',$photo?'photo:'.hash_file('sha256',$payload['photo']->getRealPath()):'text:'.$text);
        $storedPath=null;
        try {
            return DB::transaction(function () use ($assessment,$actor,$payload,$target,$photo,$text,$mime,$hash,&$storedPath) {
                $current=Assessment::whereKey($assessment->id)->lockForUpdate()->firstOrFail();
                $this->classroom($current);
                $answer=isset($target['answer'])?$current->answers()->whereKey($target['answer'])->lockForUpdate()->firstOrFail():null;
                $participant=$current->classroom->participants()->whereKey($answer?->participant_id ?? $target['participant'])->lockForUpdate()->first();
                $question=$current->questions()->whereKey($answer?->question_id ?? $target['question'])->lockForUpdate()->first();
                if (!$participant || !$question) $this->fail('Peserta atau soal tidak termasuk dalam kelas/asesmen ini.');
                $question->setRelation('assessment',$current);
                if (!$answer) {
                    $retry=Answer::where('creation_key',$target['key'])->first();
                    if ($retry) {
                        if ($retry->assessment_id !== $current->id || $retry->created_by !== $actor || $retry->participant_id !== $participant->id || $retry->question_id !== $question->id || $retry->revisions()->where('version',1)->value('content_hash') !== $hash) $this->fail('Permintaan sudah dipakai untuk jawaban lain. Muat ulang formulir.');
                        return $retry;
                    }
                    if ($current->answers()->where('participant_id',$participant->id)->where('question_id',$question->id)->exists()) $this->fail('Jawaban peserta untuk soal ini sudah ada. Buka jawaban tersebut untuk membuat revisi.');
                    $answer=$current->answers()->create(['participant_id'=>$participant->id,'question_id'=>$question->id,'created_by'=>$actor,'creation_key'=>$target['key']]);
                } else {
                    if ($answer->current_version !== $target['expected']) $this->fail('Jawaban berubah di tab lain. Muat ulang sebelum merevisi.');
                    if ($answer->latestRevision?->content_hash === $hash) $this->fail('Isi sama dengan revisi terakhir; tidak ada revisi baru yang disimpan.');
                }
                if ($photo) {
                    $storedPath=Str::uuid().($mime==='image/png'?'.png':'.jpg');
                    if (Storage::disk('answers')->putFileAs('', $payload['photo'], $storedPath, ['visibility'=>'private']) !== $storedPath) $this->fail('Foto belum tersimpan. Coba lagi.');
                }
                $source=$question->rubricSource();
                $version=$answer->current_version+1;
                $answer->revisions()->create([
                    'version'=>$version,'created_by'=>$actor,'content_type'=>$photo?'photo':'text','origin'=>($payload['origin'] ?? '')==='student'?'student':($photo?'photo':(($payload['origin'] ?? '')==='csv'?'csv':'manual')),
                    'import_batch_id'=>($payload['origin'] ?? '')==='csv'?($payload['import_batch_id'] ?? null):null,
                    'answer_text'=>$text,'photo_path'=>$storedPath,'photo_mime'=>$mime,'content_hash'=>$hash,
                    'participant_code_snapshot'=>$participant->code,'question_snapshot'=>['number'=>$question->number]+$source,
                    'source_hash'=>RubricValidator::sourceHash($source),'reason'=>$target['reason'] ?? 'Penerimaan awal.',
                ]);
                $answer->current_version=$version; $answer->save(); return $answer;
            });
        } catch (\Throwable $e) {
            if ($storedPath) { try { Storage::disk('answers')->delete($storedPath); } catch (\Throwable) {} }
            if ($e instanceof ValidationException || $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) throw $e;
            $this->fail('Jawaban belum tersimpan karena kendala penyimpanan. Coba lagi; jangan anggap sebagai nilai nol.');
        }
    }

    private function fail(string $message): never { throw ValidationException::withMessages(['answer'=>$message]); }
}
