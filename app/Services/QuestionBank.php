<?php
namespace App\Services;
use App\Models\{BankBook,BankChapter,BankPage,BankSet,BankQuestionLink,Assessment,Question};
use Illuminate\Support\Facades\{DB,Gate,Storage,Validator};
use Illuminate\Validation\ValidationException;
class QuestionBank {
    public const KINDS=['latihan'=>'Ayo Berlatih','evaluasi'=>'Evaluasi','kegiatan'=>'Ayo Mencoba / Bermain','proyek'=>'Ayo Berkarya'];
    public static function hash(array $v): string { return hash('sha256',json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)); }
    private function fail(string $s): never { throw ValidationException::withMessages(['bank'=>$s]); }
    private function pageBody(BankPage $p): array { return ['number'=>(int)$p->number,'pdf_page'=>(int)$p->pdf_page,'source_text'=>$p->source_text,'image_hash'=>$p->image_hash]; }
    private function setBody(BankSet $s): array { return ['code'=>$s->code,'kind'=>$s->kind,'title'=>$s->title,'start_page'=>(int)$s->start_page,'end_page'=>(int)$s->end_page,'source_text'=>$s->source_text]; }
    public function validPage(BankPage $p): void {
        $path=Storage::disk('local')->path($p->imagePath());
        if(!hash_equals($p->content_hash,self::hash($this->pageBody($p))) || !is_file($path) || !hash_equals($p->image_hash,hash_file('sha256',$path))) $this->fail('Halaman sumber hilang atau checksum tidak sesuai. Hubungi pengelola.');
    }
    public function validSet(BankSet $s): void {
        if(!hash_equals($s->content_hash,self::hash($this->setBody($s)))) $this->fail('Paket sumber tidak sesuai checksum.');
        $pages=$s->sourcePages(); if($pages->count()!==$s->end_page-$s->start_page+1) $this->fail('Halaman paket tidak lengkap.');
        foreach($pages as $p) $this->validPage($p);
        if($s->source_text!==implode("\n\n",$pages->pluck('source_text')->all())) $this->fail('Teks paket tidak sesuai halaman sumber.');
    }
    /** Operator-only local import. Images are installed in private storage before this atomic database import. */
    public function install(string $manifest): BankBook {
        $real=realpath($manifest); $root=realpath(Storage::disk('local')->path('bank-books'));
        if(!$real || !$root || !str_starts_with(str_replace('\\','/',$real),rtrim(str_replace('\\','/',$root),'/').'/') || filesize($real)>10000000) $this->fail('Manifest harus berada pada penyimpanan bank privat, maksimum 10 MB.');
        $raw=file_get_contents($real); $v=json_decode($raw,true,64,JSON_THROW_ON_ERROR);
        Validator::make($v,['book'=>'required|array','book.code'=>'required|regex:/^[a-z0-9-]{1,80}$/D','book.title'=>'required|string|max:255','book.authors'=>'required|string|max:255','book.edition'=>'required|string|max:100','book.isbn'=>'required|string|max:40','book.grade'=>'required|integer|between:1,6','book.source_hash'=>'required|regex:/^[a-f0-9]{64}$/D','chapters'=>'required|array|min:1|max:30','pages'=>'required|array|min:1|max:500','sets'=>'required|array|min:1|max:500'])->validate();
        if(dirname($real)!==realpath(Storage::disk('local')->path('bank-books/'.$v['book']['code']))) $this->fail('Folder manifest tidak sesuai kode buku.');
        return DB::transaction(function() use($v,$raw) {
            $bookData=array_intersect_key($v['book'],array_flip(['code','title','authors','edition','isbn','grade','source_hash']));
            $old=BankBook::where('code',$bookData['code'])->lockForUpdate()->first();
            if($old) {
                if($old->manifest_hash!==hash('sha256',$raw) || $old->pages()->count()!==count($v['pages']) || $old->chapters()->count()!==count($v['chapters']) || BankSet::whereIn('bank_chapter_id',$old->chapters()->select('id'))->count()!==count($v['sets'])) $this->fail('Buku sudah ada dengan isi berbeda. Gunakan edisi/kode baru.');
                foreach(BankSet::whereIn('bank_chapter_id',$old->chapters()->select('id'))->get() as $s) $this->validSet($s);
                foreach($old->pages()->get() as $p) $this->validPage($p);
                return $old;
            }
            $book=BankBook::create($bookData+['manifest_hash'=>hash('sha256',$raw),'created_at'=>now()]); $chapters=[]; $seen=[];
            foreach($v['chapters'] as $c) {
                Validator::make($c,['number'=>'required|integer|between:1,30','title'=>'required|string|max:255','start_page'=>'required|integer|between:1,500','end_page'=>'required|integer|between:1,500|gte:start_page','objectives'=>'required|string|max:4000'])->validate();
                if(isset($chapters[$c['number']])) $this->fail('Bab duplikat.');
                $chapters[$c['number']]=$book->chapters()->create(array_intersect_key($c,array_flip(['number','title','start_page','end_page','objectives'])));
            }
            foreach($v['pages'] as $p) {
                Validator::make($p,['number'=>'required|integer|between:1,500','pdf_page'=>'required|integer|between:1,1000','source_text'=>'present|string|max:40000','image_hash'=>'required|regex:/^[a-f0-9]{64}$/D'])->validate();
                if(isset($seen[$p['number']])) $this->fail('Halaman duplikat.'); $seen[$p['number']]=true;
                $body=array_intersect_key($p,array_flip(['number','pdf_page','source_text','image_hash'])); $record=$book->pages()->create($body+['content_hash'=>self::hash($body)]); $record->setRelation('book',$book); $this->validPage($record);
            }
            foreach($chapters as $c) foreach(range($c->start_page,$c->end_page) as $n) if(!isset($seen[$n])) $this->fail('Cakupan halaman bab tidak lengkap.');
            foreach($v['sets'] as $s) {
                Validator::make($s,['chapter'=>'required|integer','code'=>'required|regex:/^[a-z0-9-]{1,100}$/D','kind'=>'required|in:latihan,evaluasi,kegiatan,proyek','title'=>'required|string|max:255','start_page'=>'required|integer','end_page'=>'required|integer|gte:start_page','source_text'=>'present|string|max:200000'])->validate();
                $c=$chapters[$s['chapter']] ?? null; if(!$c || $s['start_page']<$c->start_page || $s['end_page']>$c->end_page || $s['end_page']-$s['start_page']>12) $this->fail('Rentang paket di luar bab atau terlalu panjang.');
                $body=array_intersect_key($s,array_flip(['code','kind','title','start_page','end_page','source_text'])); $record=$c->sets()->create($body+['content_hash'=>self::hash($body)]); $this->validSet($record);
            }
            return $book;
        });
    }
    public function copy(BankSet $set,int $actor,int $assessment,string $key,array $input,bool $reviewed): Question {
        if(!$reviewed) $this->fail('Periksa gambar sumber, teks lengkap, kunci dan pedoman terlebih dahulu.');
        Validator::make(['key'=>$key]+$input,['key'=>'required|uuid','number'=>'required|integer|between:1,9999','prompt'=>'required|string|max:19000','answer_key'=>'required|string|max:20000','scoring_guidelines'=>'required|string|max:20000','max_score'=>'required|numeric|decimal:0,2|gt:0|lte:10000'])->validate();
        $input=array_intersect_key($input,array_flip(['number','prompt','answer_key','scoring_guidelines','max_score']));
        $hash=self::hash([$set->id,$actor,$assessment,$input]);
        return DB::transaction(function() use($set,$actor,$assessment,$key,$input,$hash) {
            $a=Assessment::whereKey($assessment)->lockForUpdate()->firstOrFail(); Gate::authorize('update',$a);
            if($a->owner_id!==$actor || ($a->classroom && $a->classroom->grade!==$set->chapter->book->grade)) $this->fail('Pilih asesmen kelas yang sesuai tingkat buku atau draf tanpa kelas.');
            $old=BankQuestionLink::where('creation_key',$key)->first(); if($old) { if($old->request_hash!==$hash) $this->fail('Kunci salinan sudah dipakai untuk data berbeda.'); return $a->questions()->findOrFail($old->question_id); }
            $this->validSet($set); if($a->questions()->where('number',$input['number'])->exists()) $this->fail('Nomor soal sudah dipakai. Pilih nomor lain.');
            $b=$set->chapter->book; $citation='Sumber: '.$b->title.' ('.$b->edition.'), bab '.$set->chapter->number.', hlm. '.$set->start_page.'–'.$set->end_page.'.';
            $q=$a->questions()->create(array_replace($input,['prompt'=>$input['prompt']."\n\n".$citation]));
            BankQuestionLink::create(['question_id'=>$q->id,'bank_set_id'=>$set->id,'created_by'=>$actor,'creation_key'=>$key,'request_hash'=>$hash,'source_snapshot'=>['book'=>$b->title,'edition'=>$b->edition,'isbn'=>$b->isbn,'book_hash'=>$b->source_hash,'set_code'=>$set->code,'set_hash'=>$set->content_hash,'chapter'=>(int)$set->chapter->number,'pages'=>[$set->start_page,$set->end_page],'prompt_hash'=>hash('sha256',$q->prompt)],'created_at'=>now()]);
            return $q;
        });
    }
}
