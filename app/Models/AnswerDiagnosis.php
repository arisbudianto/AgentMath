<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class AnswerDiagnosis extends Model
{
    public const UPDATED_AT = null;
    protected $guarded = ['id'];
    protected function casts(): array { return ['items' => 'array', 'version' => 'integer', 'created_at' => 'datetime', 'approved_at' => 'datetime']; }
    protected static function booted(): void {
        static::updating(fn () => throw new \LogicException('Diagnosis dikunci; buat versi baru.'));
        static::deleting(fn () => throw new \LogicException('Riwayat diagnosis tidak dapat dihapus.'));
    }
    public function answer() { return $this->belongsTo(Answer::class); }
    public function gradeReview() { return $this->belongsTo(GradeReview::class, 'grade_review_id'); }
    public function creator() { return $this->belongsTo(User::class, 'created_by'); }
    public static function categories(): array
    {
        return ['konsep' => 'Kesalahan konsep', 'prosedur' => 'Kesalahan prosedur', 'perhitungan' => 'Kesalahan perhitungan', 'tidak_ada' => 'Tidak ada kesalahan berarti'];
    }
}
