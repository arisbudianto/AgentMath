<?php
namespace App\Http\Controllers;
use App\Models\Assessment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
class AssessmentController
{
    public function index(Request $request) { return view('assessments', ['assessments' => $request->user()->assessments()->latest()->paginate(20)]); }
    public function store(Request $request)
    {
        $data = $request->validate(['title' => 'required|string|max:200']);
        $assessment = $request->user()->assessments()->create($data);
        return redirect()->route('assessments.show', $assessment);
    }
    public function show(Assessment $assessment)
    {
        Gate::authorize('view', $assessment);
        return view('assessment', compact('assessment'));
    }
    public function update(Request $request, Assessment $assessment)
    {
        Gate::authorize('update', $assessment);
        $assessment->update($request->validate(['title' => 'required|string|max:200']));
        return back()->with('status', 'Judul disimpan.');
    }
}
