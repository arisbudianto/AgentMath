<?php

namespace App\Services;

use App\Models\{Competency, User};
use Illuminate\Support\Facades\DB;

/**
 * Rule-based (no AI/ML) practice-set suggestions.
 *
 * Two levels:
 *  - forCompetency(): class-wide, "same chapter as this competency, not yet used by this teacher".
 *  - forStudent(): personalized to one student's own weak answers and diagnosed misconception
 *    categories (M16) on this competency — adapts to that student's actual error pattern rather
 *    than giving every student in the class the same list.
 *
 * Deliberately explainable rules, not an opaque ML recommender: the grading pipeline this reads
 * from (M16 diagnosis, approved scores) has no real empirical validation behind it yet, so an
 * automated "smart" ranking that can't be explained would risk steering a teacher on unverified
 * signal. Every suggestion here traces to a concrete fact (this answer, this chapter, this count)
 * a teacher can check and override.
 */
class PracticeRecommendation
{
    /** A student's score on a competency below this percentage counts as "weak" for forStudent(). */
    public const WEAK_THRESHOLD = 70.0;


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

    /**
     * Personalized to one student: chapters come only from THIS student's own answers that scored
     * below max on questions tagged with $competency (not every question the teacher ever tagged),
     * and the result also tallies this student's approved M16 misconception categories on those
     * same weak answers — the "why" behind the suggestion, not a hidden score.
     *
     * @return array{sets: array<int, array{id:int, code:string, title:string, kind:string, book:string, chapter:string}>, categories: array<string, int>}
     */
    public function forStudent(User $teacher, Competency $competency, int $participantId): array
    {
        $latestApprovedReview = DB::table('grade_reviews')
            ->select('answer_id', DB::raw('MAX(id) as id'))
            ->where('status', 'approved')
            ->groupBy('answer_id');

        $weakAnswers = DB::table('grade_reviews as gr')
            ->joinSub($latestApprovedReview, 'latest', fn ($join) => $join->on('gr.id', '=', 'latest.id'))
            ->join('answers', 'answers.id', '=', 'gr.answer_id')
            ->join('questions', 'questions.id', '=', 'answers.question_id')
            ->join('assessments', 'assessments.id', '=', 'answers.assessment_id')
            ->where('assessments.owner_id', $teacher->id)
            ->where('questions.competency_id', $competency->id)
            ->where('answers.participant_id', $participantId)
            ->whereColumn('gr.total_score', '<', 'questions.max_score')
            ->select('answers.id as answer_id', 'questions.id as question_id')
            ->get();

        if ($weakAnswers->isEmpty()) {
            return ['sets' => [], 'categories' => []];
        }

        $chapterIds = DB::table('bank_question_links')
            ->join('bank_sets', 'bank_sets.id', '=', 'bank_question_links.bank_set_id')
            ->whereIn('bank_question_links.question_id', $weakAnswers->pluck('question_id'))
            ->distinct()
            ->pluck('bank_sets.bank_chapter_id');

        $usedSetIds = DB::table('bank_question_links')
            ->where('created_by', $teacher->id)
            ->pluck('bank_set_id');

        $sets = $chapterIds->isEmpty() ? [] : DB::table('bank_sets')
            ->join('bank_chapters', 'bank_chapters.id', '=', 'bank_sets.bank_chapter_id')
            ->join('bank_books', 'bank_books.id', '=', 'bank_chapters.bank_book_id')
            ->whereIn('bank_sets.bank_chapter_id', $chapterIds)
            ->whereNotIn('bank_sets.id', $usedSetIds)
            ->orderBy('bank_sets.start_page')
            ->select('bank_sets.id', 'bank_sets.code', 'bank_sets.title', 'bank_sets.kind', 'bank_books.title as book', 'bank_chapters.title as chapter')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        $latestApprovedDiagnosis = DB::table('answer_diagnoses')
            ->select('answer_id', DB::raw('MAX(id) as id'))
            ->where('status', 'approved')
            ->groupBy('answer_id');

        $categories = [];
        $rows = DB::table('answer_diagnoses as ad')
            ->joinSub($latestApprovedDiagnosis, 'latest', fn ($join) => $join->on('ad.id', '=', 'latest.id'))
            ->whereIn('ad.answer_id', $weakAnswers->pluck('answer_id'))
            ->pluck('ad.items');
        foreach ($rows as $json) {
            foreach (json_decode($json, true) ?? [] as $item) {
                $category = $item['category'] ?? null;
                if ($category) {
                    $categories[$category] = ($categories[$category] ?? 0) + 1;
                }
            }
        }
        arsort($categories);

        return ['sets' => $sets, 'categories' => $categories];
    }
}
