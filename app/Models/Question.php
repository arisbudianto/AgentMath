<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Question extends Model
{
    protected $fillable = ['number', 'prompt', 'answer_key', 'scoring_guidelines', 'max_score'];

    protected function casts(): array { return ['number' => 'integer', 'max_score' => 'decimal:2']; }
    public function assessment() { return $this->belongsTo(Assessment::class); }
    public function rubricVersions() { return $this->hasMany(RubricVersion::class)->orderByDesc('version'); }
    public function rubricSource(): array
    {
        return ['prompt'=>$this->prompt, 'answer_key'=>$this->answer_key, 'scoring_guidelines'=>$this->scoring_guidelines, 'instructions'=>$this->assessment->instructions ?? '', 'max_score'=>$this->max_score];
    }
    public function currentApprovedRubric(): ?RubricVersion
    {
        $source = $this->rubricSource();
        return $this->rubricVersions()->where('status','approved')->get()->first(fn($version)=>$version->matchesSource($source));
    }
}
