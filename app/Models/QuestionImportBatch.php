<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionImportBatch extends Model
{
    protected $guarded = ['id'];
    protected $attributes = ['status'=>'preview','imported_count'=>0];
    protected function casts(): array { return ['rows'=>'array','expires_at'=>'datetime','committed_at'=>'datetime','row_count'=>'integer','imported_count'=>'integer']; }
    public function assessment() { return $this->belongsTo(Assessment::class); }
}
