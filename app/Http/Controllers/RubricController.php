<?php

namespace App\Http\Controllers;

use App\Models\{Assessment, Question};
use App\Services\{AiConfiguration, RubricWorkflow};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class RubricController
{
    private function question(Assessment $assessment, int $question): Question
    {
        Gate::authorize('update', $assessment);
        $record = $assessment->questions()->findOrFail($question);
        $record->setRelation('assessment', $assessment);
        return $record;
    }

    public function index(Assessment $assessment, int $question)
    {
        $record = $this->question($assessment, $question);
        return view('rubrics', ['assessment'=>$assessment,'question'=>$record,'versions'=>$record->rubricVersions()->with('approver')->get(),'source'=>$record->rubricSource(),'currentApproved'=>$record->currentApprovedRubric(),'ai'=>app(AiConfiguration::class)->status()]);
    }

    public function store(Request $request, Assessment $assessment, int $question, RubricWorkflow $workflow)
    {
        $record = $this->question($assessment, $question);
        $data = $request->validate(['creation_key'=>'required|uuid','origin'=>'required|in:manual,ai,revision','copy_id'=>'nullable|integer']);
        $copy = $data['origin'] === 'revision' ? $record->rubricVersions()->findOrFail($data['copy_id'] ?? 0) : null;
        $rubric = $workflow->create($record, $request->user()->id, $data['creation_key'], $data['origin'], $copy);
        return redirect()->route('rubrics.show', [$assessment,$record,$rubric])->with('status', 'Draf versi '.$rubric->version.' tersedia. Periksa semua aturan dan kriteria sebelum pengesahan.');
    }

    public function show(Assessment $assessment, int $question, int $rubric)
    {
        $record = $this->question($assessment, $question);
        $version = $record->rubricVersions()->with('approver')->findOrFail($rubric);
        return view('rubric', ['assessment'=>$assessment,'question'=>$record,'rubric'=>$version,'stale'=>!$version->matchesSource($record->rubricSource())]);
    }

    public function update(Request $request, Assessment $assessment, int $question, int $rubric, RubricWorkflow $workflow)
    {
        $record = $this->question($assessment, $question);
        $validated = $request->validate(['revision'=>'required|integer|min:1','rules'=>'required|array','criteria'=>'required|array','grading_policy'=>'required|string|max:20000']);
        $data = $request->only(['rules','criteria','grading_policy']);
        // Form references are comma-separated; API clients may supply arrays.
        foreach ($data['criteria'] as &$criterion) {
            if (is_array($criterion) && is_string($criterion['rule_ids'] ?? null)) {
                $criterion['rule_ids'] = array_values(array_filter(array_map('trim', explode(',', $criterion['rule_ids'])), fn($v)=>$v !== ''));
            }
        }
        unset($criterion);
        $workflow->save($record, $rubric, (int) $validated['revision'], $data, false, $request->user()->id);
        return back()->with('status', 'Draf disimpan. Perubahan belum disahkan.');
    }

    public function approve(Request $request, Assessment $assessment, int $question, int $rubric, RubricWorkflow $workflow)
    {
        $record = $this->question($assessment, $question);
        $data = $request->validate(['revision'=>'required|integer|min:1','confirmation'=>'required|accepted']);
        $workflow->save($record, $rubric, (int) $data['revision'], [], true, $request->user()->id);
        return back()->with('status', 'Rubrik disahkan dan dikunci. Perubahan selanjutnya harus menjadi versi baru.');
    }
}
