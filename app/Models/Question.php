<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = ['number', 'prompt', 'answer_key', 'scoring_guidelines', 'max_score'];

    protected function casts(): array { return ['number' => 'integer', 'max_score' => 'decimal:2']; }
    public function assessment() { return $this->belongsTo(Assessment::class); }
}
