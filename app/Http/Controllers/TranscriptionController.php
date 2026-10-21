<?php
namespace App\Http\Controllers;
use App\Models\{AnswerTranscription, Assessment};
use App\Services\{AiConfiguration, TranscriptionAi, TranscriptionWorkflow};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Cache, Gate};
use Illuminate\Validation\ValidationException;

class TranscriptionController
{
    private function target(Assessment $assessment, int $answer, int $revision): array {
        $record=$assessment->answers()->with(['participant','question'])->findOrFail($answer);
        $photo=$record->revisions()->findOrFail($revision);
        abort_unless($photo->content_type==='photo',404);
        return [$record,$photo];
    }
    public function show(Assessment $assessment, int $answer, int $revision, AiConfiguration $ai, TranscriptionWorkflow $workflow) {
        Gate::authorize('view',$assessment);
        [$record,$photo]=$this->target($assessment,$answer,$revision);
        return view('transcription',['assessment'=>$assessment,'answer'=>$record,'photo'=>$photo,'latest'=>$photo->transcriptions()->first(),'history'=>$photo->transcriptions()->with('creator')->paginate(20),'current'=>$photo->version===$record->current_version,'ready'=>$workflow->readyText($record)!==null,'aiReady'=>config('ai.transcription_enabled') && $ai->status()['enabled'] && $ai->status()['configured'],'aiRecipient'=>$ai->label()]);
    }
    public function store(Request $request, Assessment $assessment, int $answer, int $revision, TranscriptionWorkflow $workflow, TranscriptionAi $ai) {
        Gate::authorize('update',$assessment);
        [$record,$photo]=$this->target($assessment,$answer,$revision);
        $data=$request->validate(['creation_key'=>'required|uuid','expected_version'=>'required|integer|min:0','action'=>'required|in:draft,confirm,ai','reason'=>'required|string|max:500','transcript_text'=>'required_unless:action,ai|nullable|string|max:20000','reviewed'=>'accepted_if:action,confirm','privacy_checked'=>'accepted_if:action,ai']);
        $key=$data['creation_key']; $expected=(int)$data['expected_version']; $reason=$data['reason'];
        if ($data['action']==='ai') {
            $lock=Cache::lock('transcription-request:'.$key,90);
            if (!$lock->get()) throw ValidationException::withMessages(['transcription'=>'Permintaan ini sedang diproses. Tunggu lalu muat ulang.']);
            try {
                $existing=AnswerTranscription::where('creation_key',$key)->first();
                if ($existing) {
                    if ($existing->answer_revision_id!==$photo->id || $existing->created_by!==$request->user()->id || $existing->origin!=='ai' || $existing->version!==$expected+1 || $existing->reason!==$reason) throw ValidationException::withMessages(['transcription'=>'Kunci permintaan sudah digunakan.']);
                } else {
                    if ($photo->version!==$record->current_version || ($photo->transcriptions()->first()?->version ?? 0)!==$expected) throw ValidationException::withMessages(['transcription'=>'Foto atau transkripsi berubah. Muat ulang sebelum meminta draf AI.']);
                    $generated=$ai->generate($photo);
                    $workflow->save($assessment,$request->user()->id,$answer,$revision,$key,$expected,'ai',$generated['text'],$reason,$generated);
                }
            } finally { $lock->release(); }
        } else {
            $workflow->save($assessment,$request->user()->id,$answer,$revision,$key,$expected,$data['action'],$data['transcript_text'],$reason,[], $request->boolean('reviewed'));
        }
        return redirect()->route('transcriptions.show',[$assessment,$answer,$revision])->with('status',$data['action']==='confirm'?'Transkripsi dikonfirmasi pengajar. Belum ada nilai yang diberikan.':'Draf transkripsi tersimpan. Periksa foto dan teks sebelum konfirmasi.');
    }
}
