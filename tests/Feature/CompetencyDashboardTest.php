<?php
namespace Tests\Feature;
use App\Models\{Competency, User};
use App\Services\{AnswerIntake, CompetencyDashboard, GradeReviewWorkflow, GradingOrchestrator, GradingStages, RubricWorkflow};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class CompetencyDashboardTest extends TestCase {
    use RefreshDatabase;
    private function user(string $role = 'teacher'): User {
        $u = new User(['name' => 'Synthetic ' . $role, 'email' => uniqid() . '@example.test', 'password' => 'Password12345']);
        $u->role = $role; $u->is_active = true; $u->save(); return $u;
    }

    /** One approved answer, score $score out of 2.00, for question tagged with $competency (or none). */
    private function approvedAnswer(User $u, $classroom, ?Competency $competency, string $code, string $score): void {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class); Http::preventStrayRequests();
        config(['ai.enabled' => true, 'ai.provider' => 'dinoiki', 'ai.model' => 'gpt-5-mini', 'ai.api_key' => 'MOCK_ONLY', 'ai.max_output_tokens' => 4000]);
        $p = $classroom->participants()->firstOrCreate(['code' => $code]);
        $a = $u->assessments()->make(['title' => 'Synthetic ' . uniqid()]); $a->classroom()->associate($classroom); $a->save();
        $q = $a->questions()->create(['number' => 1, 'prompt' => '1+1?', 'answer_key' => '2', 'scoring_guidelines' => 'Two points for correct result.', 'max_score' => '2.00', 'competency_id' => $competency?->id]);
        $q->setRelation('assessment', $a);
        $rw = app(RubricWorkflow::class); $r = $rw->create($q, $u->id, (string) Str::uuid(), 'manual'); $rw->save($q, $r->id, $r->revision, [], true, $u->id);
        $answer = app(AnswerIntake::class)->create($a, $u->id, $p->id, $q->id, (string) Str::uuid(), ['content_type' => 'text', 'answer_text' => '1 + 1 = 2']);
        $marker = ['criteria' => [['criterion_id' => 'C1', 'score' => 2, 'evidence' => ['1 + 1 = 2'], 'missing_evidence' => false, 'reason' => 'Match.', 'rule_ids' => ['R1']]]];
        Http::swap(new \Illuminate\Http\Client\Factory()); Http::preventStrayRequests();
        $seq = Http::sequence(); foreach ([$marker, $marker, ['label' => 'High', 'reason' => 'Consistent.', 'concerns' => []]] as $data) $seq->push(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode($data)]]]]]);
        Http::fake(['ai.dinoiki.com/*' => $seq]);
        $w = app(GradingOrchestrator::class); $run = $w->start($a, $u->id, $answer->id, (string) Str::uuid());
        foreach (GradingStages::PLAN as $stage) { $run->refresh(); $w->process($run->id, $run->generation); }
        $this->assertSame('completed', $run->fresh()->status);
        $gw = app(GradeReviewWorkflow::class);
        $gw->save($a, $u->id, $run->fresh()->id, (string) Str::uuid(), 0, 'draft', [['criterion_id' => 'C1', 'score' => $score, 'reason' => 'Checked.']], 'Synthetic review.');
        $gw->save($a, $u->id, $run->fresh()->id, (string) Str::uuid(), 1, 'approve', [], '', true);
    }

    public function test_overview_averages_only_approved_answers_tagged_with_a_competency(): void {
        $u = $this->user(); $c = $u->classrooms()->create(['name' => 'Kelas', 'grade' => 4, 'school_year' => '2026']);
        $comp = $u->competencies()->create(['name' => 'Penjumlahan']);
        $this->approvedAnswer($u, $c, $comp, 'P001', '2.00');
        $this->approvedAnswer($u, $c, $comp, 'P002', '0.00');
        $this->approvedAnswer($u, $c, null, 'P003', '0.00'); // untagged question, must not count
        $overview = app(CompetencyDashboard::class)->overview($u);
        $this->assertCount(1, $overview);
        $this->assertSame('Penjumlahan', $overview[0]['name']);
        $this->assertSame(2, $overview[0]['answers']);
        $this->assertSame(50.0, $overview[0]['percent']); // (2+0)/(2+2) = 50%
    }

    public function test_students_for_breaks_down_by_participant_within_one_competency(): void {
        $u = $this->user(); $c = $u->classrooms()->create(['name' => 'Kelas', 'grade' => 4, 'school_year' => '2026']);
        $comp = $u->competencies()->create(['name' => 'Penjumlahan']);
        $this->approvedAnswer($u, $c, $comp, 'P001', '2.00');
        $this->approvedAnswer($u, $c, $comp, 'P002', '1.00');
        $students = app(CompetencyDashboard::class)->studentsFor($u, $comp);
        $this->assertCount(2, $students);
        $byCode = collect($students)->keyBy('code');
        $this->assertSame(100.0, $byCode['P001']['percent']);
        $this->assertSame(50.0, $byCode['P002']['percent']);
    }

    public function test_dashboard_is_scoped_to_the_owning_teacher(): void {
        $u = $this->user(); $other = $this->user();
        $c = $u->classrooms()->create(['name' => 'Kelas', 'grade' => 4, 'school_year' => '2026']);
        $comp = $u->competencies()->create(['name' => 'Penjumlahan']);
        $this->approvedAnswer($u, $c, $comp, 'P001', '2.00');
        $this->assertSame([], app(CompetencyDashboard::class)->overview($other));
    }

    public function test_http_crud_is_owner_scoped_and_name_is_unique_per_teacher(): void {
        $u = $this->user(); $this->actingAs($u);
        $this->post('/competencies', ['name' => 'Penjumlahan'])->assertRedirect('/competencies');
        $comp = Competency::first();
        $this->post('/competencies', ['name' => 'Penjumlahan'])->assertSessionHasErrors('name');
        $this->get('/competencies')->assertOk()->assertSee('Penjumlahan');
        $this->get('/competencies/' . $comp->id)->assertOk();
        foreach (['teacher', 'admin', 'student'] as $role) {
            $actor = $this->user($role);
            $this->actingAs($actor)->get('/competencies/' . $comp->id)->assertForbidden();
            $this->actingAs($actor)->patch('/competencies/' . $comp->id, ['name' => 'Hijack'])->assertForbidden();
        }
        $this->actingAs($u)->delete('/competencies/' . $comp->id)->assertRedirect('/competencies');
        $this->assertSame(0, Competency::count());
    }

    public function test_question_can_be_tagged_with_own_competency_only(): void {
        $u = $this->user(); $other = $this->user(); $this->actingAs($u);
        $c = $u->classrooms()->create(['name' => 'Kelas', 'grade' => 4, 'school_year' => '2026']);
        $a = $u->assessments()->make(['title' => 'Synthetic']); $a->classroom()->associate($c); $a->save();
        $mine = $u->competencies()->create(['name' => 'Penjumlahan']);
        $foreign = $other->competencies()->create(['name' => 'Milik Lain']);
        $this->post('/assessments/' . $a->id . '/questions', ['number' => 1, 'prompt' => '1+1?', 'answer_key' => '2', 'scoring_guidelines' => 'x', 'max_score' => '2.00', 'competency_id' => $foreign->id])->assertSessionHasErrors('competency_id');
        $this->post('/assessments/' . $a->id . '/questions', ['number' => 1, 'prompt' => '1+1?', 'answer_key' => '2', 'scoring_guidelines' => 'x', 'max_score' => '2.00', 'competency_id' => $mine->id])->assertSessionDoesntHaveErrors();
        $this->assertSame($mine->id, $a->questions()->first()->competency_id);
    }
}
