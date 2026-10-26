<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Assessment extends Model
{
    protected $fillable = ['title', 'instructions'];
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function classroom() { return $this->belongsTo(Classroom::class); }
    public function questions() { return $this->hasMany(Question::class)->orderBy('number'); }
    public function answers() { return $this->hasMany(Answer::class); }
    public function answerImports() { return $this->hasMany(AnswerImportBatch::class); }
    public function questionImports() { return $this->hasMany(QuestionImportBatch::class); }

    /** Readiness is derived from current data, not a stale saved status. */
    public function packageIssues(): array
    {
        $issues = [];
        if (!$this->classroom || $this->classroom->owner_id !== $this->owner_id) {
            $issues[] = 'Pilih kelas milik Anda.';
        } elseif (!$this->classroom->participants()->exists()) {
            $issues[] = 'Tambahkan setidaknya satu peserta berkode pada kelas.';
        }
        if ($this->questions->isEmpty()) {
            $issues[] = 'Tambahkan setidaknya satu soal beserta kunci, pedoman, dan skor maksimum.';
        }
        foreach ($this->questions as $question) {
            if (trim($question->prompt) === '' || trim($question->answer_key) === '' || trim($question->scoring_guidelines) === '' || (float) $question->max_score <= 0) {
                $issues[] = 'Lengkapi soal nomor '.$question->number.'.';
            }
        }
        return $issues;
    }
}
