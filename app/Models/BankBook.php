<?php
namespace App\Models;
class BankBook extends EvalRecord {
    protected function casts(): array { return ['grade'=>'integer','created_at'=>'datetime']; }
    public function chapters() { return $this->hasMany(BankChapter::class)->orderBy('number'); }
    public function pages() { return $this->hasMany(BankPage::class)->orderBy('number'); }
}
