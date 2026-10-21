<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class GradingEvent extends Model {
    public $timestamps=false;
    protected $guarded=['id'];
    protected function casts(): array { return ['created_at'=>'datetime']; }
    protected static function booted(): void {
        static::updating(fn()=>throw new \LogicException('Peristiwa proses dikunci.'));
        static::deleting(fn()=>throw new \LogicException('Peristiwa proses dikunci.'));
    }
}
