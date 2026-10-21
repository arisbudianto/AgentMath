<?php
namespace App\Services;
use App\Models\{Assessment,EvalAttempt,EvalCase,EvalDataset,EvalExperiment,EvalObservation,EvalReference,EvalSample,GradingRun};
use App\Jobs\ProcessEvaluationSample;
use Illuminate\Support\Facades\{DB,Queue,Validator};
use Illuminate\Support\Str;

class EvaluationWorkflow {
    public function configuration(): array { return ['pipeline_version'=>'m05_v1','provider'=>config('ai.provider'),'model'=>config('ai.model'),'enabled'=>(bool)config('ai.enabled'),'max_output_tokens'=>(int)config('ai.max_output_tokens',4000)]; }
    private function same(array $a,array $b): bool { ksort($a); ksort($b); return $a===$b; }
    public function start(Assessment $a,int $actor,int $dataset,string $split,string $key,bool $confirmed,bool $reuse): EvalExperiment {
        Validator::make(compact('key','split'),['key'=>'required|uuid','split'=>'required|in:tuning,test'])->validate();
        $data=app(EvaluationData::class); if(!$confirmed) $data->fail('Konfirmasi pengiriman snapshot bebas identitas pribadi untuk baseline diperlukan.');
        $hash=EvaluationData::hash([$a->id,$actor,$dataset,$split,$confirmed,$reuse]);
        return $data->locked($a,$actor,$dataset,function($a,$d) use($actor,$split,$key,$hash,$reuse,$data) {
            $old=EvalExperiment::where('creation_key',$key)->first(); if($old) { if($old->request_hash!==$hash) $data->fail('Kunci eksperimen sudah dipakai.'); return $old; }
            $manifest=$data->manifest($d); $configuration=$this->configuration();
            try { app(GradingAi::class)->ready($configuration); } catch(GradingConfigurationException) { $data->fail('Konfigurasi AI belum siap untuk eksperimen. Minta admin memeriksa provider/model/key/flag server.'); }
            $reused=$split==='test' && $d->experiments()->where('split','test')->exists();
            if($reused && !$reuse) $data->fail('Test sudah pernah dipakai. Akui penggunaan ulang; hasil berikutnya bukan test baru yang belum dilihat.');
            $entries=array_values(array_filter($manifest,fn($entry)=>$entry['split']===$split));
            if(!$entries) $data->fail('Tidak ada kasus pada split pilihan.');
            $experiment=$d->experiments()->create(['created_by'=>$actor,'split'=>$split,'protocol_version'=>'m08_v1','config_snapshot'=>$configuration,
                'test_reused'=>$reused,'creation_key'=>$key,'request_hash'=>$hash]);
            $queued=0;
            foreach($entries as $entry) {
                $case=$d->cases()->findOrFail($entry['case_id']); $reference=$case->references()->findOrFail($entry['reference_id']);
                $this->pinned($d,$case,$reference); $multi=null; $result=null; $metadata=null;
                foreach(GradingRun::where('assessment_id',$a->id)->where('answer_id',$case->answer_id)->where('input_hash',$case->input_hash)->where('status','completed')->orderByDesc('id')->limit(20)->get() as $candidate) {
                    if(!$this->same($candidate->config_snapshot,$configuration) || $candidate->answer_revision_id!==$case->answer_revision_id || $candidate->answer_transcription_id!==$case->answer_transcription_id || $candidate->rubric_version_id!==$case->rubric_version_id) continue;
                    try { $checked=app(GradeReviewWorkflow::class)->proposal($candidate); } catch(\Throwable) { continue; }
                    if($candidate->input_snapshot!==$case->input_snapshot) continue;
                    $multi=$candidate; $result=$checked['data']; $metadata=['provider'=>$configuration['provider'],'model'=>$configuration['model'],'usage'=>[],'duration_ms'=>0,'prompt_versions'=>[]];
                    $completeUsage=true;
                    foreach($candidate->attempts()->where('status','succeeded')->get() as $attempt) {
                        if($attempt->stage==='preflight') continue;
                        $m=$attempt->output['metadata'] ?? [];
                        if(($m['skipped'] ?? false)===true) continue;
                        foreach(['input_tokens','output_tokens'] as $k) {
                            if(is_int($m['usage'][$k] ?? null) && $m['usage'][$k]>=0) $metadata['usage'][$k]=($metadata['usage'][$k] ?? 0)+$m['usage'][$k];
                            else $completeUsage=false;
                        }
                        if(isset($m['prompt_version'])) $metadata['prompt_versions'][$attempt->stage]=$m['prompt_version'];
                        if($attempt->finished_at) $metadata['duration_ms']+=max(0,(int)$attempt->started_at->diffInMilliseconds($attempt->finished_at));
                    }
                    if(!$completeUsage) $metadata['usage']=[];
                    break;
                }
                $sample=$experiment->samples()->create(['eval_case_id'=>$case->id,'eval_reference_id'=>$reference->id,'multi_run_id'=>$multi?->id,
                    'multi_result'=>$result,'multi_metadata'=>$metadata,'status'=>$multi?'queued':'missing_multi','generation'=>1,'queued_at'=>$multi?now():null]);
                if($multi) { $this->enqueue($sample); $queued++; }
            }
            if(!$queued) $data->fail('Belum ada hasil M05 lengkap yang cocok dengan snapshot dan konfigurasi aktif. Jalankan M05 terlebih dahulu.');
            return $experiment;
        });
    }
    public function pinned(EvalDataset $d,EvalCase $c,EvalReference $r): void {
        $data=app(EvaluationData::class); $entries=$data->manifest($d);
        $entry=collect($entries)->firstWhere('case_id',$c->id);
        if(!$entry || $r->eval_case_id!==$c->id || $c->eval_dataset_id!==$d->id || $entry['reference_id']!==$r->id || $entry['input_hash']!==$c->input_hash || $entry['reference_hash']!==$r->reference_hash || $entry['split']!==$c->split) $data->fail('Snapshot eksperimen tidak sesuai manifest.');
        $data->validCase($c); $data->validReference($r,$c);
    }
    private function locked(int $sampleId,callable $fn): mixed {
        $s=EvalSample::findOrFail($sampleId); $assessmentId=$s->experiment->dataset->assessment_id;
        return DB::transaction(function() use($assessmentId,$sampleId,$fn) {
            $a=Assessment::lockForUpdate()->findOrFail($assessmentId);
            $s=EvalSample::lockForUpdate()->findOrFail($sampleId); return $fn($s,$a);
        });
    }
    public function process(int $id,int $generation): void {
        $claim=$this->locked($id,function($s,$a) use($generation) {
            if($s->generation!==$generation || $s->status!=='queued') return null;
            if($s->experiment->cancelled_at || !$a->owner->is_active || $a->owner->role!=='teacher') { $this->stop($s,'cancelled'); return null; }
            $number=$s->attempts()->count()+1;
            if($number>3) { $this->stop($s,'failed'); return null; }
            $token=(string)Str::uuid(); $attempt=$s->attempts()->create(['number'=>$number,'lease_token'=>$token,'status'=>'running','started_at'=>now()]);
            $s->update(['status'=>'running','lease_token'=>$token,'lease_until'=>now()->addSeconds(120)]); return [$s,$attempt];
        });
        if(!$claim) return;
        [$s,$attempt]=$claim; $output=null; $error=null; $start=microtime(true);
        try {
            $this->pinned($s->experiment->dataset,$s->case,$s->reference); app(GradingAi::class)->ready($s->experiment->config_snapshot);
            // One NEW independent request; no human labels, M05 outputs, participant IDs or observations enter this call.
            $output=app(GradingStages::class)->execute('marker_a',$s->case->input_snapshot,[],$s->experiment->config_snapshot);
            $output=app(GradingStages::class)->validate('marker_a',$output);
            app(GradingAi::class)->ready($s->experiment->config_snapshot);
            $output['metadata']['duration_ms']=max(0,(int)round((microtime(true)-$start)*1000));
            $output['metadata']['evaluation_protocol']='m08_single_marker_v1';
            if(strlen(json_encode($output,JSON_THROW_ON_ERROR))>1000000) throw new \UnexpectedValueException();
        } catch(\Throwable $e) { $error=$e instanceof GradingConfigurationException?'configuration_changed':($e instanceof \UnexpectedValueException?'invalid_output':'baseline_failed'); }
        $this->locked($id,function($current,$a) use($generation,$attempt,$output,$error) {
            if($current->generation!==$generation || $current->status!=='running' || $current->lease_token!==$attempt->lease_token) return;
            $entry=$current->attempts()->findOrFail($attempt->id);
            if($current->lease_until?->isPast()) $error='worker_interrupted';
            if(!$a->owner->is_active || $a->owner->role!=='teacher' || $current->experiment->cancelled_at) $error='cancelled';
            $entry->update(['status'=>$error?'failed':'succeeded','error_code'=>$error,'output'=>$error?null:$output,'finished_at'=>now()]);
            $this->stop($current,$error==='cancelled'?'cancelled':($error?'failed':'succeeded'));
        });
    }
    private function stop(EvalSample $s,string $status): void { $s->update(['status'=>$status,'generation'=>$s->generation+1,'lease_token'=>null,'lease_until'=>null,'finished_at'=>now()]); }
    private function enqueue(EvalSample $s): void { Queue::connection('grading')->push(new ProcessEvaluationSample($s->id,$s->generation),'','grading'); }
    public function retry(Assessment $a,int $actor,int $dataset,int $experiment,int $sample): void {
        app(EvaluationData::class)->locked($a,$actor,$dataset,function($a,$d) use($experiment,$sample) {
            $e=$d->experiments()->findOrFail($experiment); $s=$e->samples()->lockForUpdate()->findOrFail($sample);
            $stalled=$s->status==='queued' && $s->queued_at && $s->queued_at->lte(now()->subMinutes(2));
            if($e->cancelled_at || ($s->status!=='failed' && !$stalled) || $s->attempts()->count()>=3) app(EvaluationData::class)->fail('Ulangi hanya baseline gagal atau antrean tertahan minimal dua menit, maksimal tiga percobaan. Buat eksperimen baru setelah pembatalan.');
            $this->pinned($d,$s->case,$s->reference);
            try { app(GradingAi::class)->ready($e->config_snapshot); } catch(GradingConfigurationException) { app(EvaluationData::class)->fail('Konfigurasi server berbeda dari eksperimen. Pulihkan konfigurasi atau buat eksperimen baru.'); }
            $s->update(['status'=>'queued','generation'=>$s->generation+1,'queued_at'=>now(),'finished_at'=>null]); $this->enqueue($s);
        });
    }
    public function cancel(Assessment $a,int $actor,int $dataset,int $experiment): void {
        app(EvaluationData::class)->locked($a,$actor,$dataset,function($a,$d) use($experiment) {
            $e=$d->experiments()->findOrFail($experiment); if(!$e->cancelled_at) $e->update(['cancelled_at'=>now()]);
            foreach($e->samples()->whereIn('status',['queued','running','failed'])->lockForUpdate()->get() as $s) {
                $s->attempts()->where('status','running')->get()->each(fn($attempt)=>$attempt->update(['status'=>'failed','error_code'=>'cancelled','finished_at'=>now()]));
                $this->stop($s,'cancelled');
            }
        });
    }
    public function recoverExpired(): int {
        $count=0;
        foreach(EvalSample::where('status','running')->where('lease_until','<=',now())->limit(100)->pluck('id') as $id) $count+=$this->locked($id,function($s,$a) {
            if($s->status!=='running' || !$s->lease_until?->isPast()) return 0;
            $s->attempts()->where('status','running')->get()->each(fn($attempt)=>$attempt->update(['status'=>'failed','error_code'=>'worker_interrupted','finished_at'=>now()]));
            $this->stop($s,'failed'); return 1;
        });
        return $count;
    }
    public function observe(Assessment $a,int $actor,int $dataset,int $experiment,int $sample,string $key,int $expected,string $method,string $category,string $note,?int $seconds): EvalObservation {
        Validator::make(compact('key','expected','method','category','note','seconds'),['key'=>'required|uuid','expected'=>'required|integer|min:0','method'=>'required|in:multi,baseline',
            'category'=>'required|in:none,arithmetic,procedure,transcription,rubric,ambiguous,other','note'=>'required|string|max:2000','seconds'=>'nullable|integer|between:1,86400'])->validate();
        if(trim($note)==='') app(EvaluationData::class)->fail('Catatan analisis diperlukan.');
        $hash=EvaluationData::hash([$a->id,$actor,$dataset,$experiment,$sample,$expected,$method,$category,$note,$seconds]);
        return app(EvaluationData::class)->locked($a,$actor,$dataset,function($a,$d) use($actor,$experiment,$sample,$key,$expected,$method,$category,$note,$seconds,$hash) {
            $s=$d->experiments()->findOrFail($experiment)->samples()->findOrFail($sample);
            $this->pinned($d,$s->case,$s->reference);
            $old=EvalObservation::where('creation_key',$key)->first(); if($old) { if($old->request_hash!==$hash) app(EvaluationData::class)->fail('Kunci catatan sudah dipakai.'); return $old; }
            if($s->status!=='succeeded') app(EvaluationData::class)->fail('Analisis berpasangan tersedia setelah baseline berhasil.');
            $latest=$s->observations()->where('method',$method)->orderByDesc('version')->first();
            if(($latest?->version ?? 0)!==$expected) app(EvaluationData::class)->fail('Catatan berubah di tab lain.');
            return $s->observations()->create(['created_by'=>$actor,'method'=>$method,'version'=>$expected+1,'review_seconds'=>$seconds,'category'=>$category,'note'=>$note,'creation_key'=>$key,'request_hash'=>$hash]);
        });
    }
}
