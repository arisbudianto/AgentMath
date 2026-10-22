<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ClassroomController
{
    public function index(Request $request)
    {
        return view('classrooms', ['classrooms' => $request->user()->classrooms()->withCount(['participants', 'assessments'])->latest()->paginate(20)]);
    }

    public function store(Request $request)
    {
        $classroom = $request->user()->classrooms()->create($request->validate($this->rules()));
        return redirect()->route('classrooms.show', $classroom)->with('status', 'Kelas dibuat. Tambahkan kode peserta.');
    }

    public function show(Classroom $classroom)
    {
        Gate::authorize('view', $classroom);
        return view('classroom', [
            'classroom' => $classroom,
            'participants' => $classroom->participants()->paginate(30),
            'assessments' => $classroom->assessments()->latest()->paginate(15, ['*'], 'assessments_page'),
        ]);
    }

    public function update(Request $request, Classroom $classroom)
    {
        Gate::authorize('update', $classroom);
        $v=$request->validate($this->rules());
        if($classroom->grade!==(int)$v['grade'] && \App\Models\LearningPath::where('classroom_id',$classroom->id)->exists())
            throw \Illuminate\Validation\ValidationException::withMessages(['grade'=>'Jenjang kelas tidak dapat diubah setelah jalur belajar dibuat.']);
        $classroom->update($v);
        return back()->with('status', 'Kelas disimpan.');
    }

    private function rules(): array
    {
        return ['name' => 'required|string|max:120', 'grade' => 'required|integer|between:1,6', 'school_year' => 'required|string|max:30'];
    }
}
