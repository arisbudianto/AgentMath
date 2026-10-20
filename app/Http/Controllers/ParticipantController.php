<?php

namespace App\Http\Controllers;

use App\Models\Classroom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ParticipantController
{
    public function store(Request $request, Classroom $classroom)
    {
        Gate::authorize('update', $classroom);
        $classroom->participants()->create($this->validatedCode($request, $classroom));
        return back()->with('status', 'Peserta berkode ditambahkan.');
    }

    public function update(Request $request, Classroom $classroom, int $participant)
    {
        Gate::authorize('update', $classroom);
        $record = $classroom->participants()->findOrFail($participant);
        $record->update($this->validatedCode($request, $classroom, $record->id));
        return back()->with('status', 'Kode peserta disimpan.');
    }

    private function validatedCode(Request $request, Classroom $classroom, ?int $ignore = null): array
    {
        // Canonical ASCII codes avoid case-sensitive SQLite vs MariaDB differences.
        if (is_string($request->input('code'))) {
            $request->merge(['code' => strtoupper(trim($request->input('code')))]);
        }
        return $request->validate(['code' => [
            'required', 'string', 'max:40', 'regex:/\A[A-Z0-9][A-Z0-9_-]*\z/',
            Rule::unique('participants', 'code')->where('classroom_id', $classroom->id)->ignore($ignore),
        ]]);
    }
}
