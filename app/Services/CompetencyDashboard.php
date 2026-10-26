<?php

namespace App\Services;

use App\Models\{Competency, User};
use Illuminate\Support\Facades\DB;

/**
 * Read-only aggregation (no AI) of approved grade decisions, grouped by the competency a
 * question is tagged with. Only the current approved GradeReview per answer counts, and
 * only answers whose question still belongs to a competency owned by this teacher.
 */
class CompetencyDashboard
{
    /** Per-competency score % averaged across all approved, current answers tagged with it. */
    public function overview(User $teacher): array
    {
        $rows = $this->approvedRows($teacher->id);
        $byCompetency = [];
        foreach ($rows as $row) {
            $byCompetency[$row->competency_id] ??= ['name' => $row->competency_name, 'score' => 0.0, 'max' => 0.0, 'answers' => 0];
            $byCompetency[$row->competency_id]['score'] += (float) $row->total_score;
            $byCompetency[$row->competency_id]['max'] += (float) $row->max_score;
            $byCompetency[$row->competency_id]['answers']++;
        }
        $result = [];
        foreach ($byCompetency as $id => $data) {
            $result[] = ['competency_id' => $id, 'name' => $data['name'], 'answers' => $data['answers'],
                'percent' => $data['max'] > 0 ? round($data['score'] / $data['max'] * 100, 1) : null];
        }
        usort($result, fn ($a, $b) => $a['name'] <=> $b['name']);
        return $result;
    }

    /** Per-student breakdown within one competency, for the teacher's own classrooms. */
    public function studentsFor(User $teacher, Competency $competency): array
    {
        $rows = $this->approvedRows($teacher->id, $competency->id);
        $byStudent = [];
        foreach ($rows as $row) {
            $byStudent[$row->participant_id] ??= ['code' => $row->participant_code, 'classroom' => $row->classroom_name, 'score' => 0.0, 'max' => 0.0, 'answers' => 0];
            $byStudent[$row->participant_id]['score'] += (float) $row->total_score;
            $byStudent[$row->participant_id]['max'] += (float) $row->max_score;
            $byStudent[$row->participant_id]['answers']++;
        }
        $result = [];
        foreach ($byStudent as $id => $data) {
            $result[] = ['participant_id' => $id, 'code' => $data['code'], 'classroom' => $data['classroom'], 'answers' => $data['answers'],
                'percent' => $data['max'] > 0 ? round($data['score'] / $data['max'] * 100, 1) : null];
        }
        usort($result, fn ($a, $b) => $a['code'] <=> $b['code']);
        return $result;
    }

    /** One row per answer: its latest APPROVED review's score, next to the question's tagged competency and max_score. */
    private function approvedRows(int $teacherId, ?int $competencyId = null): \Illuminate\Support\Collection
    {
        $latestApprovedIds = DB::table('grade_reviews')
            ->select('answer_id', DB::raw('MAX(id) as id'))
            ->where('status', 'approved')
            ->groupBy('answer_id');

        $query = DB::table('grade_reviews as gr')
            ->joinSub($latestApprovedIds, 'latest', fn ($join) => $join->on('gr.id', '=', 'latest.id'))
            ->join('answers', 'answers.id', '=', 'gr.answer_id')
            ->join('questions', 'questions.id', '=', 'answers.question_id')
            ->join('competencies', 'competencies.id', '=', 'questions.competency_id')
            ->join('assessments', 'assessments.id', '=', 'answers.assessment_id')
            ->join('participants', 'participants.id', '=', 'answers.participant_id')
            ->join('classrooms', 'classrooms.id', '=', 'participants.classroom_id')
            ->where('competencies.owner_id', $teacherId)
            ->where('assessments.owner_id', $teacherId)
            ->select(
                'gr.total_score', 'questions.max_score', 'competencies.id as competency_id', 'competencies.name as competency_name',
                'participants.id as participant_id', 'participants.code as participant_code', 'classrooms.name as classroom_name'
            );
        if ($competencyId !== null) {
            $query->where('competencies.id', $competencyId);
        }
        return $query->get();
    }
}
