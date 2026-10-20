<?php
namespace App\Http\Controllers;

use App\Models\Assessment;
use App\Services\AnswerImport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Gate};

class AnswerImportController
{
    public function template(Assessment $assessment)
    {
        Gate::authorize('view',$assessment);
        return response("\xEF\xBB\xBFparticipant_code,question_number,answer_text\r\nP001,1,\"Contoh jawaban; ganti dengan jawaban peserta\"\r\n",200,['Content-Type'=>'text/csv; charset=UTF-8','Content-Disposition'=>'attachment; filename="template-jawaban.csv"']);
    }

    public function preview(Request $request, Assessment $assessment, AnswerImport $import)
    {
        Gate::authorize('update',$assessment);
        $data=$request->validate(['creation_key'=>'required|uuid','csv_file'=>'required|file|max:2048']);
        if (strtolower($request->file('csv_file')->getClientOriginalExtension())!=='csv') throw \Illuminate\Validation\ValidationException::withMessages(['csv_file'=>'Gunakan file dengan ekstensi .csv.']);
        $batch=$import->preview($assessment,$request->user()->id,$data['creation_key'],file_get_contents($request->file('csv_file')->getRealPath()));
        return redirect()->route('imports.show',[$assessment,$batch]);
    }

    public function show(Assessment $assessment, int $batch)
    {
        Gate::authorize('view',$assessment);
        $record=$assessment->answerImports()->findOrFail($batch);
        $rows=$record->status==='preview' && $record->expires_at->isFuture()?($record->rows ?? []):[];
        return view('answer-import',['assessment'=>$assessment,'batch'=>$record,'rows'=>$rows,'hasErrors'=>collect($rows)->contains(fn($row)=>!empty($row['errors']))]);
    }

    public function commit(Request $request, Assessment $assessment, int $batch, AnswerImport $import)
    {
        Gate::authorize('update',$assessment);
        $request->validate(['confirmation'=>'required|accepted']);
        $record=$import->commit($assessment,$batch,$request->user()->id);
        return redirect()->route('imports.show',[$assessment,$record])->with('status',$record->imported_count.' jawaban berhasil diimpor. Tidak ada nilai yang diberikan.');
    }

    public function cancel(Assessment $assessment, int $batch)
    {
        Gate::authorize('update',$assessment);
        DB::transaction(function () use ($assessment,$batch) {
            $record=$assessment->answerImports()->whereKey($batch)->lockForUpdate()->firstOrFail();
            if ($record->status==='committed') throw \Illuminate\Validation\ValidationException::withMessages(['import'=>'Batch sudah diimpor dan tidak dapat dibatalkan melalui pratinjau.']);
            $record->rows=null; $record->status='cancelled'; $record->save();
        });
        return back()->with('status','Pratinjau dibatalkan. Jawaban tersimpan tidak berubah.');
    }
}
