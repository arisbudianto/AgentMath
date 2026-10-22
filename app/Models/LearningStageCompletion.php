<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LearningStageCompletion extends Model {
    public const UPDATED_AT=null;
    protected $guarded=['id'];
    protected static function booted(): void { static::updating(fn()=>throw new \LogicException('Kemajuan belajar dikunci.')); static::deleting(fn()=>throw new \LogicException('Kemajuan belajar dikunci.')); }
}
