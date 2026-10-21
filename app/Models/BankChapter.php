<?php
namespace App\Models;
class BankChapter extends EvalRecord {
    public $timestamps=false;
    public function book() { return $this->belongsTo(BankBook::class,'bank_book_id'); }
    public function sets() { return $this->hasMany(BankSet::class); }
}
