<?php
namespace App\Services;

use App\Models\{AnswerImportBatch, Assessment};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AnswerImport
{
    public function preview(Assessment $assessment, int $actor, string $key, string $csv): AnswerImportBatch
    {
        AnswerImportBatch::where('status','preview')->where('expires_at','<=',now())->update(['rows'=>null,'status'=>'expired']);
        $parsed=app(AnswerCsv::class)->parse($csv);
        $fileHash=hash('sha256',$csv);
        return DB::transaction(function () use ($assessment,$actor,$key,$parsed,$fileHash) {
            $current=Assessment::whereKey($assessment->id)->lockForUpdate()->firstOrFail();
            $existing=AnswerImportBatch::where('creation_key',$key)->first();
            if ($existing) {
                if ($existing->assessment_id !== $current->id || $existing->created_by !== $actor) $this->fail('Permintaan pratinjau tidak cocok. Muat ulang formulir.');
                if ($existing->file_hash !== $fileHash) $this->fail('Isi CSV berubah. Muat ulang halaman untuk membuat pratinjau baru.');
                return $existing;
            }
            $rows=app(AnswerCsv::class)->resolve($current,$parsed,true);
            return $current->answerImports()->create(['created_by'=>$actor,'creation_key'=>$key,'file_hash'=>$fileHash,'rows'=>$rows,'row_count'=>count($rows),'expires_at'=>now()->addHour()]);
        });
    }

    public function commit(Assessment $assessment, int $id, int $actor): AnswerImportBatch
    {
        return DB::transaction(function () use ($assessment,$id,$actor) {
            $current=Assessment::whereKey($assessment->id)->lockForUpdate()->firstOrFail();
            $batch=$current->answerImports()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($batch->created_by !== $actor) $this->fail('Pratinjau bukan milik Anda.');
            if ($batch->status==='committed') return $batch;
            if ($batch->status!=='preview' || $batch->expires_at->isPast()) $this->fail('Pratinjau kedaluwarsa atau dibatalkan. Unggah CSV kembali.');
            $stored=$batch->rows;
            foreach ($stored as $row) if ($row['errors']) $this->fail('Perbaiki seluruh kesalahan dan unggah ulang sebelum konfirmasi.');
            $fresh=app(AnswerCsv::class)->resolve($current,$stored,true);
            foreach ($fresh as $i=>$row) {
                if ($row['errors'] || $row['participant_id'] !== $stored[$i]['participant_id'] || $row['question_id'] !== $stored[$i]['question_id'] || $row['source_hash'] !== $stored[$i]['source_hash']) $this->fail('Data kelas, soal, atau jawaban berubah sejak pratinjau. Unggah CSV kembali untuk pemeriksaan terbaru.');
            }
            foreach ($fresh as $row) {
                app(AnswerIntake::class)->create($current,$actor,$row['participant_id'],$row['question_id'],(string)Str::uuid(),['content_type'=>'text','answer_text'=>$row['answer_text'],'origin'=>'csv','import_batch_id'=>$batch->id]);
            }
            $batch->status='committed'; $batch->imported_count=count($fresh); $batch->committed_at=now(); $batch->rows=null; $batch->save(); return $batch;
        });
    }

    private function fail(string $message): never { throw ValidationException::withMessages(['import'=>$message]); }
}
