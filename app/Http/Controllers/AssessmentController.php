<?php
namespace App\Http\Controllers;
use App\Models\Assessment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
class AssessmentController
{
    public function index(Request $request)
    {
        return view('assessments', [
            'assessments' => $request->user()->assessments()->with('classroom')->withCount('questions')->latest()->paginate(20),
            'classrooms' => $request->user()->classrooms()->orderBy('name')->get(),
        ]);
    }
    public function store(Request $request)
    {
        $data = $request->validate($this->rules($request));
        $classroomId = $data['classroom_id'] ?? null;
        unset($data['classroom_id']);
        $assessment = $request->user()->assessments()->make($data);
        $assessment->classroom_id = $classroomId;
        $assessment->save();
        return redirect()->route('assessments.show', $assessment);
    }
    public function show(Request $request, Assessment $assessment)
    {
        Gate::authorize('view', $assessment);
        $assessment->load(['classroom', 'questions.bankSource']);
        return view('assessment', [
            'assessment' => $assessment,
            'classrooms' => $request->user()->classrooms()->orderBy('name')->get(),
            'issues' => $assessment->packageIssues(),
        ]);
    }
    public function update(Request $request, Assessment $assessment)
    {
        Gate::authorize('update', $assessment);
        $data = $request->validate($this->rules($request));
        \Illuminate\Support\Facades\DB::transaction(function () use ($assessment,$data) {
            $current=Assessment::whereKey($assessment->id)->lockForUpdate()->firstOrFail();
            if (array_key_exists('classroom_id',$data)) {
                $id=$data['classroom_id'] === null ? null : (int)$data['classroom_id'];
                if ($current->classroom_id !== $id && ($current->answers()->exists() || \App\Models\LearningPath::where('pre_assessment_id',$current->id)->orWhere('post_assessment_id',$current->id)->exists())) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['classroom_id'=>'Kelas tidak dapat diganti setelah asesmen dipakai pada jalur belajar atau memiliki jawaban. Buat asesmen baru untuk kelas lain.']);
                }
                $current->classroom_id=$id;
            }
            unset($data['classroom_id']);
            $current->fill($data)->save();
        });
        return back()->with('status', 'Asesmen disimpan.');
    }

    public function destroy(Request $request, Assessment $assessment)
    {
        Gate::authorize('delete', $assessment);
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($assessment) {
                $current = Assessment::whereKey($assessment->id)->lockForUpdate()->firstOrFail();
                $current->delete();
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if ((string) $e->getCode() === '23000') {
                return redirect()->route('assessments.index')->withErrors([
                    'assessment' => 'Asesmen tidak dapat dihapus karena sudah memiliki soal, jawaban, hasil penilaian, atau dipakai pada jalur belajar. Hapus/lepaskan data tersebut terlebih dahulu.',
                ]);
            }
            throw $e;
        }
        return redirect()->route('assessments.index')->with('status', 'Asesmen "'.$assessment->title.'" dihapus.');
    }

    private function rules(Request $request): array
    {
        return [
            'title' => 'required|string|max:200',
            'instructions' => 'nullable|string|max:20000',
            'classroom_id' => ['nullable', 'integer', Rule::exists('classrooms', 'id')->where('owner_id', $request->user()->id)],
        ];
    }
}
