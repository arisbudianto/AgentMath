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
        $assessment->load(['classroom', 'questions']);
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
        if (array_key_exists('classroom_id', $data)) {
            $assessment->classroom_id = $data['classroom_id'];
        }
        unset($data['classroom_id']);
        $assessment->fill($data)->save();
        return back()->with('status', 'Asesmen disimpan.');
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
