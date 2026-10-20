<?php

namespace App\Services;

use App\Models\{Assessment, Question, RubricVersion};
use Illuminate\Support\Facades\{Cache, DB};
use Illuminate\Validation\ValidationException;

class RubricWorkflow
{
    public function create(Question $question, int $actor, string $key, string $origin, ?RubricVersion $copy = null): RubricVersion
    {
        $lock = Cache::lock('m02-create:'.$question->id.':'.$key, 120);
        if (!$lock->get()) $this->fail('Pembuatan draf ini masih berjalan. Muat ulang halaman sebelum mencoba lagi.');
        try {
            $existing = RubricVersion::where('creation_key', $key)->first();
            if ($existing) {
                if ($existing->question_id !== $question->id || $existing->created_by !== $actor) $this->fail('Permintaan draf tidak cocok. Muat ulang halaman.');
                return $existing;
            }
            $source = $question->rubricSource();
            $metadata = null;
            if ($copy) {
                if ($copy->status !== 'approved') $this->fail('Hanya versi yang sudah disahkan dapat disalin menjadi revisi.');
                if (!$copy->matchesSource($source)) $this->fail('Dasar soal sudah berubah. Buat draf baru dari soal terbaru.');
                $data = $copy->only(['rules','criteria','grading_policy']);
            } elseif ($origin === 'ai') {
                $generated = app(RubricAi::class)->generate($source);
                $data = $generated['data']; $metadata = $generated['metadata'];
            } else {
                $data = [
                    'rules'=>[['rule_id'=>'R1','source'=>'scoring_guidelines','quote'=>$source['scoring_guidelines'],'description'=>'Pedoman penilaian soal dari pengajar.']],
                    'criteria'=>[['criterion_id'=>'C1','description'=>'Ketepatan jawaban dan langkah sesuai pedoman.','evidence'=>'Jawaban dan langkah yang sesuai dengan kunci serta pedoman.','scoring'=>$source['scoring_guidelines'],'max_score'=>$source['max_score'],'rule_ids'=>['R1']]],
                    'grading_policy'=>$source['scoring_guidelines'],
                ];
            }
            $data = app(RubricValidator::class)->validate($data, $source);
            return DB::transaction(function () use ($question, $actor, $key, $origin, $source, $metadata, $data, $copy) {
                // Serialize version assignment and source verification with edits/approval.
                $assessment = Assessment::whereKey($question->assessment_id)->lockForUpdate()->firstOrFail();
                $current = Question::whereKey($question->id)->lockForUpdate()->firstOrFail();
                $current->setRelation('assessment', $assessment);
                if (RubricValidator::sourceHash($current->rubricSource()) !== RubricValidator::sourceHash($source)) $this->fail('Soal atau pedoman berubah selama pembuatan draf. Muat ulang dan coba lagi.');
                return $current->rubricVersions()->create($data + [
                    'version'=>($current->rubricVersions()->max('version') ?? 0) + 1,
                    'creation_key'=>$key,'created_by'=>$actor,'origin'=>$origin,
                    'parent_id'=>$copy?->id,
                    'source_snapshot'=>$source,'source_hash'=>RubricValidator::sourceHash($source),'ai_metadata'=>$metadata,
                ]);
            });
        } finally { $lock->release(); }
    }

    public function save(Question $question, int $id, int $revision, array $data, bool $approve, int $actor): RubricVersion
    {
        return DB::transaction(function () use ($question, $id, $revision, $data, $approve, $actor) {
            $assessment = Assessment::whereKey($question->assessment_id)->lockForUpdate()->firstOrFail();
            $current = Question::whereKey($question->id)->lockForUpdate()->firstOrFail();
            $current->setRelation('assessment', $assessment);
            $rubric = $current->rubricVersions()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($rubric->status === 'approved') {
                if ($approve) return $rubric;
                $this->fail('Versi yang disahkan dikunci. Buat versi baru untuk perubahan.');
            }
            if ($rubric->revision !== $revision) $this->fail('Draf berubah di tab lain. Muat ulang sebelum menyimpan.');
            if (!$rubric->matchesSource($current->rubricSource())) $this->fail('Dasar soal atau pedoman berubah. Buat draf baru dari soal terbaru.');
            $valid = app(RubricValidator::class)->validate($approve ? $rubric->only(['rules','criteria','grading_policy']) : $data, $rubric->source_snapshot);
            $rubric->fill($valid); $rubric->revision++;
            if ($approve) { $rubric->status = 'approved'; $rubric->approved_by = $actor; $rubric->approved_at = now(); }
            $rubric->save(); return $rubric;
        });
    }

    private function fail(string $message): never { throw ValidationException::withMessages(['rubric'=>$message]); }
}
