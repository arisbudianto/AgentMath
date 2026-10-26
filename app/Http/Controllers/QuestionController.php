<?php

namespace App\Http\Controllers;

use App\Models\Assessment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class QuestionController
{
    public function store(Request $request, Assessment $assessment)
    {
        Gate::authorize('update', $assessment);
        $assessment->questions()->create($request->validate($this->rules($assessment)));
        return back()->with('status', 'Soal ditambahkan.');
    }

    public function edit(Assessment $assessment, int $question)
    {
        Gate::authorize('update', $assessment);
        return view('question', ['assessment' => $assessment, 'question' => $assessment->questions()->findOrFail($question)]);
    }

    public function update(Request $request, Assessment $assessment, int $question)
    {
        Gate::authorize('update', $assessment);
        $record = $assessment->questions()->findOrFail($question);
        $record->update($request->validate($this->rules($assessment, $record->id)));
        return redirect()->route('assessments.show', $assessment)->with('status', 'Soal disimpan.');
    }

    public function destroy(Request $request, Assessment $assessment, int $question)
    {
        Gate::authorize('update', $assessment);
        $record = $assessment->questions()->findOrFail($question);
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($assessment, $question) {
                $assessment->questions()->whereKey($question)->lockForUpdate()->firstOrFail()->delete();
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                return redirect()->route('assessments.show', $assessment)->withErrors([
                    'question' => 'Soal nomor '.$record->number.' tidak dapat dihapus karena sudah punya jawaban, rubrik, hasil penilaian, atau dipakai di tugas siswa. Hapus/lepaskan data tersebut terlebih dahulu.',
                ]);
            }
            throw $e;
        }
        return redirect()->route('assessments.show', $assessment)->with('status', 'Soal nomor '.$record->number.' dihapus.');
    }

    private function rules(Assessment $assessment, ?int $ignore = null): array
    {
        return [
            'number' => ['required', 'integer', 'between:1,9999', Rule::unique('questions', 'number')->where('assessment_id', $assessment->id)->ignore($ignore)],
            'prompt' => 'required|string|max:20000',
            'answer_key' => 'required|string|max:20000',
            'scoring_guidelines' => 'required|string|max:20000',
            'max_score' => 'required|numeric|decimal:0,2|gt:0|lte:10000',
        ];
    }
}
