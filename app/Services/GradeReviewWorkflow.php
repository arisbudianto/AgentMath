<?php
namespace App\Services;
use App\Models\{Assessment,GradeReview,GradingRun};
use Illuminate\Support\Facades\{DB,Validator};
use Illuminate\Validation\ValidationException;

class GradeReviewWorkflow {
    public function proposal(GradingRun $run): array {
        if($run->status!=='completed' || $run->stage!=='confidence' || ($run->config_snapshot['pipeline_version'] ?? '')!=='m05_v1') $this->fail('Proses belum memiliki usulan M05 yang lengkap.');
        $hash=hash('sha256',json_encode($run->input_snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
        if(!hash_equals($run->input_hash,$hash)) $this->fail('Snapshot proses tidak valid.');
        $attempt=$run->attempts()->where('stage','confidence')->where('status','succeeded')->first();
        $out=$attempt?->output; $data=$out['data'] ?? null;
        if(($out['schema_version'] ?? null)!=='m05_v1' || ($out['stage'] ?? null)!=='confidence' || !is_array($data) || ($data['teacher_review_required'] ?? null)!==true || ($data['final_grade'] ?? null)!==false || !in_array($data['selected_marker'] ?? null,['marker_a','marker_b'],true)) $this->fail('Usulan belum dapat diperiksa.');
        try {
            app(RubricValidator::class)->validate($run->input_snapshot['rubric'],$run->input_snapshot['source']);
            $rows=$data['criteria'];
            foreach($rows as &$row) $row['score']=RubricValidator::cents($row['score'])/100;
            unset($row);
            $valid=app(GradingResultValidator::class)->marker(['criteria'=>$rows],$run->input_snapshot);
            if($valid['criteria']!==$data['criteria'] || $valid['total_score']!==$data['total_score']) throw new \UnexpectedValueException();
        } catch(\Throwable) { $this->fail('Skor atau bukti usulan tidak valid.'); }
        return ['data'=>$data,'attempt'=>$attempt];
    }
    public function save(Assessment $assessment,int $actor,int $runId,string $key,int $expected,string $action,array $criteria=[],string $reason='',bool $confirmed=false): GradeReview {
        Validator::make(['key'=>$key,'expected'=>$expected,'action'=>$action],['key'=>'required|uuid','expected'=>'required|integer|min:0','action'=>'required|in:draft,approve'])->validate();
        $requestHash=hash('sha256',json_encode([$assessment->id,$actor,$runId,$expected,$action,$criteria,$reason,$confirmed],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
        return DB::transaction(function() use($assessment,$actor,$runId,$key,$expected,$action,$criteria,$reason,$confirmed,$requestHash) {
            $a=Assessment::lockForUpdate()->findOrFail($assessment->id);
            abort_unless($a->owner_id===$actor && $a->owner->role==='teacher' && $a->owner->is_active,403);
            $run=GradingRun::where('assessment_id',$a->id)->lockForUpdate()->findOrFail($runId);
            $answer=$a->answers()->lockForUpdate()->findOrFail($run->answer_id);
            $existing=GradeReview::where('creation_key',$key)->first();
            if($existing) {
                if(!hash_equals($existing->request_hash,$requestHash)) $this->fail('Kunci permintaan sudah dipakai untuk keputusan lain.');
                return $existing;
            }
            $proposal=$this->proposal($run);
            if(!app(GradingOrchestrator::class)->isCurrent($run)) $this->fail('Dasar jawaban, transkripsi, soal atau rubrik berubah. Buat penilaian dari dasar terkini.');
            $latest=GradeReview::where('answer_id',$answer->id)->orderByDesc('version')->first();
            if(($latest?->version ?? 0)!==$expected) $this->fail('Pemeriksaan berubah di tab lain. Muat ulang sebelum menyimpan.');
            if($action==='approve') {
                if(!$confirmed) $this->fail('Konfirmasi pemeriksaan bukti dan skor diperlukan.');
                if(!$latest || $latest->status!=='draft' || $latest->grading_run_id!==$run->id) $this->fail('Simpan dan periksa draf dari proses ini sebelum menyetujui.');
                if($criteria!==[] || $reason!=='') $this->fail('Persetujuan memakai draf tersimpan; simpan koreksi terlebih dahulu.');
                $valid=$this->criteria($latest->criteria,$proposal['data'],$run->input_snapshot);
                $reason=$latest->reason;
            } else {
                if(trim($reason)==='' || mb_strlen($reason)>2000) $this->fail('Alasan pemeriksaan wajib diisi, maksimal 2000 karakter.');
                $valid=$this->criteria($criteria,$proposal['data'],$run->input_snapshot);
            }
            $approved=GradeReview::where('answer_id',$answer->id)->where('status','approved')->orderByDesc('version')->first();
            return GradeReview::create([
                'answer_id'=>$answer->id,'grading_run_id'=>$run->id,'confidence_attempt_id'=>$proposal['attempt']->id,
                'created_by'=>$actor,'parent_id'=>$latest?->id,'supersedes_approval_id'=>$action==='approve'?$approved?->id:null,
                'version'=>$expected+1,'creation_key'=>$key,'request_hash'=>$requestHash,'status'=>$action==='approve'?'approved':'draft',
                'criteria'=>$valid['criteria'],'total_score'=>$valid['total_score'],'reason'=>$reason,
                'approved_at'=>$action==='approve'?now():null,
            ]);
        });
    }
    private function criteria(array $rows,array $proposal,array $input): array {
        Validator::make(['criteria'=>$rows],[
            'criteria'=>'required|array|min:1|max:20','criteria.*'=>'array:criterion_id,score,reason',
            'criteria.*.criterion_id'=>'required|string|distinct:strict','criteria.*.score'=>'required',
            'criteria.*.reason'=>'present|nullable|string|max:2000',
        ])->validate();
        if(count($rows)!==count($input['rubric']['criteria'])) $this->fail('Semua kriteria wajib diperiksa.');
        $map=[]; $total=0;
        foreach($rows as $row) {
            $row['reason']=$row['reason'] ?? '';
            $criterion=collect($input['rubric']['criteria'])->firstWhere('criterion_id',$row['criterion_id']);
            if(!$criterion) $this->fail('Kriteria tidak dikenal.');
            $points=RubricValidator::cents($row['score']);
            if($points>RubricValidator::cents($criterion['max_score'])) $this->fail('Skor melebihi maksimum kriteria.');
            $original=collect($proposal['criteria'])->firstWhere('criterion_id',$row['criterion_id']);
            if($points!==RubricValidator::cents($original['score']) && trim($row['reason'])==='') $this->fail('Setiap koreksi skor memerlukan alasan per kriteria.');
            $row['score']=number_format($points/100,2,'.',''); $map[$row['criterion_id']]=$row; $total+=$points;
        }
        return ['criteria'=>array_map(fn($c)=>$map[$c['criterion_id']],$input['rubric']['criteria']),'total_score'=>number_format($total/100,2,'.','')];
    }
    private function fail(string $message): never { throw ValidationException::withMessages(['review'=>$message]); }
}
