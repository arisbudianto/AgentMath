<?php
namespace App\Http\Controllers;

use App\Models\{Assessment, Answer};
use App\Services\MisconceptionDiagnosisWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DiagnosisController
{
    public function show(Assessment $assessment, int $answer)
    {
        Gate::authorize('view', $assessment);
        $record = $assessment->answers()->with('question')->findOrFail($answer);
        $weak = app(MisconceptionDiagnosisWorkflow::class)->eligibleCriteria($record);
        return view('diagnosis', [
            'assessment' => $assessment, 'answer' => $record,
            'weak' => $weak, 'latest' => $record->diagnoses()->first(),
            'categories' => \App\Models\AnswerDiagnosis::categories(),
        ]);
    }

    public function generate(Request $request, Assessment $assessment, int $answer)
    {
        Gate::authorize('update', $assessment);
        $data = $request->validate(['creation_key' => 'required|uuid']);
        $record = $assessment->answers()->findOrFail($answer);
        app(MisconceptionDiagnosisWorkflow::class)->generate($record, $request->user()->id, $data['creation_key']);
        return redirect()->route('diagnosis.show', [$assessment, $answer])->with('status', 'Draf diagnosis dibuat. Periksa sebelum disahkan.');
    }

    public function decide(Request $request, Assessment $assessment, int $answer, int $diagnosis)
    {
        Gate::authorize('update', $assessment);
        $data = $request->validate([
            'expected' => 'required|integer|min:1', 'action' => 'required|in:approve,reject',
            'reason' => 'nullable|string|max:2000',
            'items' => 'nullable|array', 'items.*.criterion_id' => 'required_with:items|string',
            'items.*.category' => 'required_with:items|string', 'items.*.explanation' => 'required_with:items|string',
            'items.*.recommended_action' => 'required_with:items|string',
        ]);
        $record = $assessment->answers()->findOrFail($answer);
        app(MisconceptionDiagnosisWorkflow::class)->decide($record, $diagnosis, $data['expected'], $data['action'], $request->user()->id, $data['items'] ?? [], $data['reason'] ?? null);
        return redirect()->route('diagnosis.show', [$assessment, $answer])->with('status', $data['action'] === 'approve' ? 'Diagnosis disahkan.' : 'Diagnosis ditolak.');
    }
}
