<?php
namespace App\Services;

use App\Models\{Answer, AnswerDiagnosis, Assessment};
use Illuminate\Support\Facades\{Cache, DB, Validator};
use Illuminate\Validation\ValidationException;

class MisconceptionDiagnosisWorkflow
{
    /** Criteria eligible for diagnosis: those in the latest APPROVED GradeReview that did not score full marks. */
    public function eligibleCriteria(Answer $answer): array
    {
        $review = $answer->gradeReviews()->where('status', 'approved')->orderByDesc('version')->first();
        if (!$review) return [];
        $rubricMax = collect($review->run->input_snapshot['rubric']['criteria'])->keyBy('criterion_id');
        return collect($review->criteria)
            ->filter(fn ($row) => $rubricMax->has($row['criterion_id']) && (float) $row['score'] < (float) $rubricMax[$row['criterion_id']]['max_score'])
            ->values()->all();
    }

    public function generate(Answer $answer, int $actor, string $key): AnswerDiagnosis
    {
        if (!config('ai.enabled') || !app(AiConfiguration::class)->status()['configured']) {
            $this->fail('Layanan AI belum siap. Minta admin melengkapi konfigurasi server.');
        }
        $lock = Cache::lock('m16-create:'.$answer->id.':'.$key, 180);
        if (!$lock->get()) $this->fail('Pembuatan diagnosis ini masih berjalan. Muat ulang halaman sebelum mencoba lagi.');
        try {
            $existing = AnswerDiagnosis::where('creation_key', $key)->first();
            if ($existing) {
                if ($existing->answer_id !== $answer->id || $existing->created_by !== $actor) $this->fail('Permintaan diagnosis tidak cocok. Muat ulang halaman.');
                return $existing;
            }
            $review = $answer->gradeReviews()->where('status', 'approved')->orderByDesc('version')->first();
            if (!$review) $this->fail('Jawaban ini belum punya nilai akhir yang disahkan guru.');
            $weak = $this->eligibleCriteria($answer);
            if (!$weak) $this->fail('Tidak ada kriteria yang kehilangan nilai pada keputusan ini; diagnosis tidak diperlukan.');
            $source = $review->run->input_snapshot['source'];
            $rubric = $review->run->input_snapshot['rubric'];
            $text = $review->run->input_snapshot['answer_text'];
            $generated = app(AiAudit::class)->within($answer->assessment, 'diagnosis', $answer->id, $key, fn () => app(MisconceptionDiagnosisAi::class)->generate($source, $rubric, $text, $weak));
            $items = $this->validateItems($generated['data']['items'], $weak);
            return DB::transaction(function () use ($answer, $review, $actor, $key, $items) {
                $current = Answer::whereKey($answer->id)->lockForUpdate()->firstOrFail();
                $latestReview = $current->gradeReviews()->where('status', 'approved')->orderByDesc('version')->first();
                if (!$latestReview || $latestReview->id !== $review->id) $this->fail('Keputusan nilai berubah sejak diagnosis dimulai. Muat ulang dan coba lagi.');
                $parent = $current->diagnoses()->first();
                return $current->diagnoses()->create([
                    'grade_review_id' => $review->id, 'created_by' => $actor, 'parent_id' => $parent?->id,
                    'version' => ($parent?->version ?? 0) + 1, 'creation_key' => $key,
                    'status' => 'draft', 'source' => 'ai', 'items' => $items, 'created_at' => now(),
                ]);
            });
        } finally { $lock->release(); }
    }

    public function decide(Answer $answer, int $id, int $expected, string $action, int $actor, array $items = [], ?string $reason = null): AnswerDiagnosis
    {
        Validator::make(['action' => $action], ['action' => 'required|in:approve,reject'])->validate();
        return DB::transaction(function () use ($answer, $id, $expected, $action, $actor, $items, $reason) {
            $current = Answer::whereKey($answer->id)->lockForUpdate()->firstOrFail();
            $draft = $current->diagnoses()->whereKey($id)->lockForUpdate()->firstOrFail();
            if ($draft->status !== 'draft') $this->fail('Diagnosis ini sudah diputuskan. Buat diagnosis baru untuk keputusan berbeda.');
            $latest = $current->diagnoses()->first();
            if (!$latest || $latest->version !== $expected || $latest->id !== $draft->id) $this->fail('Diagnosis berubah di tab lain. Muat ulang sebelum menyimpan.');
            $weak = $this->eligibleCriteria($current);
            $finalItems = $action === 'approve' && !$items ? $draft->items : $this->validateItems($items ?: $draft->items, $weak);
            return $current->diagnoses()->create([
                'grade_review_id' => $draft->grade_review_id, 'created_by' => $actor, 'parent_id' => $draft->id,
                'version' => $draft->version + 1, 'creation_key' => (string) \Illuminate\Support\Str::uuid(),
                'status' => $action === 'approve' ? 'approved' : 'rejected',
                'source' => $items ? 'teacher' : $draft->source,
                'items' => $finalItems, 'reason' => $reason,
                'approved_at' => $action === 'approve' ? now() : null, 'created_at' => now(),
            ]);
        });
    }

    private function validateItems(array $items, array $weakCriteria): array
    {
        $expected = collect($weakCriteria)->pluck('criterion_id')->sort()->values()->all();
        Validator::make(['items' => $items], [
            'items' => 'required|array|min:1|max:20',
            'items.*' => 'array:criterion_id,category,explanation,recommended_action',
            'items.*.criterion_id' => 'required|string|distinct:strict',
            'items.*.category' => 'required|string|in:'.implode(',', \App\Services\MisconceptionDiagnosisAi::CATEGORIES),
            'items.*.explanation' => 'required|string|max:2000',
            'items.*.recommended_action' => 'required|string|max:2000',
        ])->validate();
        $got = collect($items)->pluck('criterion_id')->sort()->values()->all();
        if ($got !== $expected) $this->fail('Diagnosis harus mencakup tepat kriteria yang kehilangan nilai, tidak lebih atau kurang.');
        return $items;
    }

    private function fail(string $message): never { throw ValidationException::withMessages(['diagnosis' => $message]); }
}
