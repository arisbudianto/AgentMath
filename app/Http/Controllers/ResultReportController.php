<?php
namespace App\Http\Controllers;
use App\Models\Assessment;
use App\Services\ResultReport;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class ResultReportController {
    private function filters(Request $request): array {
        return $request->validate(['participant'=>'nullable|integer|min:1','question'=>'nullable|integer|min:1','page'=>'nullable|integer|min:1','kind'=>'nullable|in:summary,detail']);
    }
    public function index(Request $request,Assessment $assessment,ResultReport $reports) {
        Gate::authorize('view',$assessment); $filters=$this->filters($request);
        $report=$reports->build($assessment,isset($filters['participant'])?(int)$filters['participant']:null,isset($filters['question'])?(int)$filters['question']:null);
        $page=(int)($filters['page'] ?? 1);
        $rows=new LengthAwarePaginator(array_slice($report['summary'],($page-1)*25,25),count($report['summary']),25,$page,['path'=>$request->url(),'query'=>$request->except('page')]);
        return view('results',['assessment'=>$assessment,'report'=>$report,'rows'=>$rows,
            'allParticipants'=>$assessment->classroom?->participants()->get() ?? collect(),'allQuestions'=>$assessment->questions()->get()]);
    }
    public function export(Request $request,Assessment $assessment,ResultReport $reports) {
        Gate::authorize('view',$assessment); $filters=$this->filters($request);
        $report=$reports->build($assessment,isset($filters['participant'])?(int)$filters['participant']:null,isset($filters['question'])?(int)$filters['question']:null);
        abort_if($report['limited'],422,'Pilihan rekap terlalu besar (jumlah sel/soal atau ukuran teks sumber). Pilih peserta atau soal terlebih dahulu.');
        $kind=$filters['kind'] ?? 'summary';
        $fields=$kind==='summary'?['participant_code','questions','answers','proposals','approved','historical','drafts','approved_sum','final_total','max_score','complete']:
            ['participant_code','question_number','max_score','answer_id','answer_revision','answer_status','latest_run_id','latest_run_status','proposal_run_id','proposal_status','proposal_score','confidence','review_status','review_version','grade_status','approved_id','approved_version','approved_run_id','approved_score'];
        $data=$report[$kind==='summary'?'summary':'details'];
        return response()->streamDownload(function() use($data,$fields,$report,$assessment) {
            $out=fopen('php://output','wb'); fwrite($out,"\xEF\xBB\xBF");
            fputcsv($out,array_merge(['assessment_id','generated_at','question_scope'], $fields),',','"','');
            foreach($data as $row) {
                $values=[$assessment->id,$report['generated_at'],$report['question_filter'] ?? 'all'];
                foreach($fields as $key) $values[]=is_bool($row[$key])?($row[$key]?'complete':'incomplete'):($row[$key] ?? '');
                fputcsv($out,array_map([self::class,'safeCell'],$values),',','"','');
            }
            fclose($out);
        },'agentmath-'.$assessment->id.'-'.$kind.'.csv',[
            'Content-Type'=>'text/csv; charset=UTF-8','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff',
        ]);
    }
    public static function safeCell(mixed $value): string {
        $text=(string)$value;
        // Quoting CSV alone does not prevent spreadsheet formula execution.
        return preg_match('/\A[\s\x00-\x20\x7F\p{Cf}]*[=+@-]/u',$text)?"'".$text:$text;
    }
}
