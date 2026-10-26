<?php

namespace App\Services;

use App\Models\{Competency, User};
use Illuminate\Support\Facades\DB;

/**
 * Rule-based (no AI) practice-set suggestions for a competency the teacher is reviewing on the
 * M17 dashboard: other bank sets from the same chapter(s) as the questions already tagged with
 * this competency, that this teacher has not yet copied into any assessment of their own.
 *
 * Deliberately simple and explainable — "same chapter, not yet used" — rather than an adaptive
 * or ML-driven recommender. P2 scope per the source roadmap: adaptive practice was explicitly
 * deferred pending real validation data, so this stays a transparent, read-only aggregation a
 * teacher can trust and override, not an automated decision.
 */
class PracticeRecommendation
{
    /** @return array<int, array{id:int, code:string, title:string, kind:string, book:string, chapter:string}> */
    public function forCompetency(User $teacher, Competency $competency): array
    {
        $chapterIds = DB::table('questions')
            ->join('bank_question_links', 'bank_question_links.question_id', '=', 'questions.id')
            ->join('assessments', 'assessments.id', '=', 'questions.assessment_id')
            ->join('bank_sets', 'bank_sets.id', '=', 'bank_question_links.bank_set_id')
            ->where('assessments.owner_id', $teacher->id)
            ->where('questions.competency_id', $competency->id)
            ->distinct()
            ->pluck('bank_sets.bank_chapter_id');

        if ($chapterIds->isEmpty()) {
            return [];
        }

        $usedSetIds = DB::table('bank_question_links')
            ->where('created_by', $teacher->id)
            ->pluck('bank_set_id');

        return DB::table('bank_sets')
            ->join('bank_chapters', 'bank_chapters.id', '=', 'bank_sets.bank_chapter_id')
            ->join('bank_books', 'bank_books.id', '=', 'bank_chapters.bank_book_id')
            ->whereIn('bank_sets.bank_chapter_id', $chapterIds)
            ->whereNotIn('bank_sets.id', $usedSetIds)
            ->orderBy('bank_sets.start_page')
            ->select('bank_sets.id', 'bank_sets.code', 'bank_sets.title', 'bank_sets.kind', 'bank_books.title as book', 'bank_chapters.title as chapter')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();
    }
}
