<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AiCall extends Model {
    public $timestamps=false;
    protected $guarded=['id'];
    protected function casts(): array { return ['tariff'=>'array','started_at'=>'datetime','finished_at'=>'datetime','duration_ms'=>'integer','input_tokens'=>'integer','output_tokens'=>'integer','cached_tokens'=>'integer','http_status'=>'integer','estimated_cost'=>'decimal:12']; }
    protected static function booted(): void {
        static::updating(function(self $c) {
            if($c->getOriginal('outcome')!=='pending' || $c->isDirty(['assessment_id','kind','source_id','correlation_id','stage','provider','model','prompt_version','tariff','currency','started_at'])) throw new \LogicException('Dasar audit dikunci.');
            if($c->getOriginal('state')!=='in_flight' && array_diff(array_keys($c->getDirty()),['outcome'])) throw new \LogicException('Metadata respons dikunci.');
        });
        static::deleting(fn()=>throw new \LogicException('Audit tidak dapat dihapus.'));
    }
}
