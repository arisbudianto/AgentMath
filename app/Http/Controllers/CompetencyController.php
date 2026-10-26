<?php

namespace App\Http\Controllers;

use App\Models\Competency;
use App\Services\CompetencyDashboard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CompetencyController
{
    public function index(Request $request)
    {
        return view('competencies', [
            'competencies' => $request->user()->competencies()->withCount('questions')->orderBy('name')->get(),
            'overview' => app(CompetencyDashboard::class)->overview($request->user()),
        ]);
    }

    public function show(Request $request, Competency $competency)
    {
        Gate::authorize('view', $competency);
        return view('competency', ['competency' => $competency, 'students' => app(CompetencyDashboard::class)->studentsFor($request->user(), $competency)]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        try {
            $request->user()->competencies()->create($data);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000') {
                return back()->withErrors(['name' => 'Kompetensi dengan nama ini sudah ada.'])->withInput();
            }
            throw $e;
        }
        return redirect()->route('competencies.index')->with('status', 'Kompetensi ditambahkan.');
    }

    public function update(Request $request, Competency $competency)
    {
        Gate::authorize('update', $competency);
        $data = $request->validate($this->rules());
        try {
            $competency->update($data);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000') {
                return back()->withErrors(['name' => 'Kompetensi dengan nama ini sudah ada.'])->withInput();
            }
            throw $e;
        }
        return back()->with('status', 'Kompetensi disimpan.');
    }

    public function destroy(Competency $competency)
    {
        Gate::authorize('delete', $competency);
        try {
            $competency->delete();
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000') {
                return back()->withErrors(['competency' => 'Kompetensi tidak dapat dihapus karena masih dipakai pada soal.']);
            }
            throw $e;
        }
        return redirect()->route('competencies.index')->with('status', 'Kompetensi dihapus.');
    }

    private function rules(): array
    {
        return ['name' => 'required|string|max:120', 'description' => 'nullable|string|max:2000'];
    }
}
