<?php
namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Services\QuestionImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Gate};

class QuestionImportController
{
    public function template(Assessment $assessment)
    {
        Gate::authorize('view',$assessment);
        return response("\xEF\xBB\xBFnumber,prompt,answer_key,scoring_guidelines,max_score\r\n1,\"Contoh soal; ganti dengan teks soal\",\"Contoh kunci jawaban\",\"Contoh pedoman penilaian\",100\r\n",200,['Content-Type'=>'text/csv; charset=UTF-8','Content-Disposition'=>'attachment; filename="template-soal.csv"']);
    }

    public function preview(Request $request, Assessment $assessment, QuestionImport $import)
    {
        Gate::authorize('update',$assessment);
        $data=$request->validate(['creation_key'=>'required|uuid','csv_file'=>'required|file|max:2048']);
        if (strtolower($request->file('csv_file')->getClientOriginalExtension())!=='csv') throw \Illuminate\Validation\ValidationException::withMessages(['csv_file'=>'Gunakan file dengan ekstensi .csv.']);
        $batch=$import->preview($assessment,$request->user()->id,$data['creation_key'],file_get_contents($request->file('csv_file')->getRealPath()));
        return redirect()->route('question-imports.show',[$assessment,$batch]);
    }

    public function show(Assessment $assessment, int $batch)
    {
        Gate::authorize('view',$assessment);
        $record=$assessment->questionImports()->findOrFail($batch);
        $rows=$record->status==='preview' && $record->expires_at->isFuture()?($record->rows ?? []):[];
        return view('question-import',['assessment'=>$assessment,'batch'=>$record,'rows'=>$rows,'hasErrors'=>collect($rows)->contains(fn($row)=>!empty($row['errors']))]);
    }

    public function commit(Request $request, Assessment $assessment, int $batch, QuestionImport $import)
    {
        Gate::authorize('update',$assessment);
        $request->validate(['confirmation'=>'required|accepted']);
        $record=$import->commit($assessment,$batch,$request->user()->id);
        return redirect()->route('question-imports.show',[$assessment,$record])->with('status',$record->imported_count.' soal berhasil diimpor.');
    }

    public function cancel(Assessment $assessment, int $batch)
    {
        Gate::authorize('update',$assessment);
        DB::transaction(function () use ($assessment,$batch) {
            $record=$assessment->questionImports()->whereKey($batch)->lockForUpdate()->firstOrFail();
            if ($record->status==='committed') throw \Illuminate\Validation\ValidationException::withMessages(['import'=>'Batch sudah diimpor dan tidak dapat dibatalkan melalui pratinjau.']);
            $record->rows=null; $record->status='cancelled'; $record->save();
        });
        return back()->with('status','Pratinjau dibatalkan. Soal tersimpan tidak berubah.');
    }
}
