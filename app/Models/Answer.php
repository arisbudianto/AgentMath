<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Answer extends Model
{
    protected $guarded = ['id'];
    protected $attributes = ['current_version'=>0];
    protected function casts(): array { return ['current_version'=>'integer']; }
    public function assessment() { return $this->belongsTo(Assessment::class); }
    public function question() { return $this->belongsTo(Question::class); }
    public function participant() { return $this->belongsTo(Participant::class); }
    public function revisions() { return $this->hasMany(AnswerRevision::class)->orderByDesc('version'); }
    public function latestRevision() { return $this->hasOne(AnswerRevision::class)->ofMany('version','max'); }
    public function gradeReviews() { return $this->hasMany(GradeReview::class); }
    public function diagnoses() { return $this->hasMany(AnswerDiagnosis::class)->orderByDesc('version'); }
}
