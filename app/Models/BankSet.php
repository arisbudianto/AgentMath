<?php
namespace App\Models;
class BankSet extends EvalRecord {
    public $timestamps=false;
    public function chapter() { return $this->belongsTo(BankChapter::class,'bank_chapter_id'); }
    public function sourcePages() { return $this->chapter->book->pages()->whereBetween('number',[$this->start_page,$this->end_page])->get(); }
}
