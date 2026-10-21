<?php
namespace App\Http\Controllers;
use App\Models\{BankBook,BankChapter,BankPage,BankSet};
use App\Services\QuestionBank;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
class QuestionBankController {
    public function index(Request $r) {
        $v=$r->validate(['book'=>'nullable|integer|min:1','chapter'=>'nullable|integer|min:1','kind'=>'nullable|in:latihan,evaluasi,kegiatan,proyek','q'=>'nullable|string|max:80']);
        $books=BankBook::orderBy('grade')->orderBy('title')->get(); $book=isset($v['book'])?$books->firstWhere('id',(int)$v['book']):$books->first();
        abort_if(isset($v['book']) && !$book,404); $chapters=$book?$book->chapters()->withCount('sets')->get():collect();
        $sets=BankSet::with('chapter.book')->whereIn('bank_chapter_id',$chapters->pluck('id'));
        if(!empty($v['chapter'])) { abort_unless($chapters->contains('id',(int)$v['chapter']),404); $sets->where('bank_chapter_id',$v['chapter']); }
        if(!empty($v['kind'])) $sets->where('kind',$v['kind']);
        if(!empty($v['q'])) $sets->where('source_text','like','%'.$v['q'].'%');
        return response()->view('question-bank',['books'=>$books,'book'=>$book,'chapters'=>$chapters,'sourcePages'=>$book?$book->pages()->get(['id','number','pdf_page']):collect(),'sets'=>$sets->orderBy('start_page')->orderBy('id')->paginate(20)->withQueryString(),'kinds'=>QuestionBank::KINDS])->header('Cache-Control','private, no-store');
    }
    public function show(Request $r,int $bankSet,QuestionBank $bank) {
        $s=BankSet::with('chapter.book')->findOrFail($bankSet); $bank->validSet($s);
        return response()->view('question-bank-set',['set'=>$s,'pages'=>$s->sourcePages(),'assessments'=>$r->user()->assessments()->with('classroom')->latest()->limit(200)->get()])->header('Cache-Control','private, no-store');
    }
    public function page(int $bankPage,QuestionBank $bank) {
        $p=BankPage::with('book')->findOrFail($bankPage); $bank->validPage($p);
        return response()->file(Storage::disk('local')->path($p->imagePath()),['Content-Type'=>'image/webp','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);
    }
    public function copy(Request $r,int $bankSet,QuestionBank $bank) {
        $v=$r->validate(['assessment_id'=>'required|integer|min:1','creation_key'=>'required|uuid','reviewed'=>'accepted','number'=>'required|integer|between:1,9999','prompt'=>'required|string|max:19000','answer_key'=>'required|string|max:20000','scoring_guidelines'=>'required|string|max:20000','max_score'=>'required|numeric|decimal:0,2|gt:0|lte:10000']);
        $s=BankSet::with('chapter.book')->findOrFail($bankSet); $q=$bank->copy($s,$r->user()->id,(int)$v['assessment_id'],$v['creation_key'],$v,true);
        return redirect()->route('questions.edit',[$q->assessment_id,$q])->with('status','Soal sumber disalin dengan kunci dan pedoman yang Anda tinjau. Susun dan sahkan rubrik sebelum penilaian.');
    }
}
