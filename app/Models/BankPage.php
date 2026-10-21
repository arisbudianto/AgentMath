<?php
namespace App\Models;
class BankPage extends EvalRecord {
    public $timestamps=false;
    public function book() { return $this->belongsTo(BankBook::class,'bank_book_id'); }
    public function imagePath(): string { return 'bank-books/'.$this->book->code.'/pages/'.$this->number.'.webp'; }
}
