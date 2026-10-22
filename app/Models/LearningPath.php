<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LearningPath extends Model {
    protected $guarded=['id'];
    public function classroom() { return $this->belongsTo(Classroom::class); }
    public function book() { return $this->belongsTo(BankBook::class,'bank_book_id'); }
    public function preAssessment() { return $this->belongsTo(Assessment::class,'pre_assessment_id'); }
    public function postAssessment() { return $this->belongsTo(Assessment::class,'post_assessment_id'); }
    public function completions() { return $this->hasMany(LearningStageCompletion::class); }
}
