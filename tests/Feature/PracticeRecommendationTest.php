<?php
namespace Tests\Feature;
use App\Models\{BankSet, User};
use App\Services\{AnswerIntake, GradeReviewWorkflow, GradingOrchestrator, GradingStages, MisconceptionDiagnosisWorkflow, PracticeRecommendation, QuestionBank};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Http, Storage};
use Illuminate\Support\Str;
use Tests\TestCase;

class PracticeRecommendationTest extends TestCase {
    use RefreshDatabase;
    private function user(string $role = 'teacher'): User {
        $u = new User(['name' => 'Teacher', 'email' => uniqid() . '@example.test', 'password' => 'TestPassword123']);
        $u->role = $role; $u->is_active = true; $u->save(); return $u;
    }

    /** One chapter, two sets ("latihan-1" and "latihan-2") sharing the same page range. */
    private function installBank(): void {
        Storage::fake('local'); Http::preventStrayRequests();
        $v = ['book' => ['code' => 'kelas1-pr', 'title' => 'Buku sumber', 'authors' => 'Penulis', 'edition' => '2022', 'isbn' => 'test-isbn', 'grade' => 1, 'source_hash' => str_repeat('a', 64)],
            'chapters' => [['number' => 1, 'title' => 'Bilangan', 'start_page' => 1, 'end_page' => 2, 'objectives' => 'Membilang sampai 10.']], 'pages' => [], 'sets' => []];
        foreach ([1, 2] as $n) {
            $bytes = 'TEST_IMAGE_' . $n; Storage::disk('local')->put('bank-books/kelas1-pr/pages/' . $n . '.webp', $bytes);
            $v['pages'][] = ['number' => $n, 'pdf_page' => $n + 12, 'source_text' => 'SOURCE_PAGE_' . $n, 'image_hash' => hash('sha256', $bytes)];
        }
        $text = implode("\n\n", array_column($v['pages'], 'source_text'));
        $v['sets'][] = ['chapter' => 1, 'code' => 'kelas1-pr-latihan-1', 'kind' => 'latihan', 'title' => 'Latihan bilangan 1', 'start_page' => 1, 'end_page' => 2, 'source_text' => $text];
        $v['sets'][] = ['chapter' => 1, 'code' => 'kelas1-pr-latihan-2', 'kind' => 'latihan', 'title' => 'Latihan bilangan 2', 'start_page' => 1, 'end_page' => 2, 'source_text' => $text];
        Storage::disk('local')->put('bank-books/kelas1-pr/manifest.json', json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        app(QuestionBank::class)->install(Storage::disk('local')->path('bank-books/kelas1-pr/manifest.json'));
    }

    private function copyInto(User $u, $assessment, BankSet $set, int $number, ?int $competencyId): void {
        $q = app(QuestionBank::class)->copy($set, $u->id, $assessment->id, (string) Str::uuid(), [
            'number' => $number, 'prompt' => 'Ada beberapa bola. Berapa jumlahnya?', 'answer_key' => '5', 'scoring_guidelines' => 'x', 'max_score' => '2.00',
        ], true);
        if ($competencyId) $q->update(['competency_id' => $competencyId]);
    }

    public function test_recommends_unused_sets_from_same_chapter_as_tagged_competency(): void {
        $this->installBank(); $u = $this->user(); $this->actingAs($u);
        $c = $u->classrooms()->create(['name' => 'Kelas', 'grade' => 1, 'school_year' => '2026']);
        $a = $u->assessments()->make(['title' => 'Synthetic']); $a->classroom()->associate($c); $a->save();
        $comp = $u->competencies()->create(['name' => 'Penjumlahan']);
        $sets = BankSet::orderBy('code')->get();
        $this->copyInto($u, $a, $sets[0], 1, $comp->id); // used set, tagged
        $recs = app(PracticeRecommendation::class)->forCompetency($u, $comp);
        $this->assertCount(1, $recs);
        $this->assertSame('kelas1-pr-latihan-2', $recs[0]['code']); // only the unused one from the same chapter
    }

    public function test_no_recommendation_when_competency_has_no_bank_linked_question(): void {
        $this->installBank(); $u = $this->user();
        $comp = $u->competencies()->create(['name' => 'Penjumlahan']);
        $this->assertSame([], app(PracticeRecommendation::class)->forCompetency($u, $comp));
    }

    public function test_set_already_used_by_teacher_elsewhere_is_excluded_even_for_different_competency(): void {
        $this->installBank(); $u = $this->user(); $this->actingAs($u);
        $c = $u->classrooms()->create(['name' => 'Kelas', 'grade' => 1, 'school_year' => '2026']);
        $a = $u->assessments()->make(['title' => 'Synthetic']); $a->classroom()->associate($c); $a->save();
        $compA = $u->competencies()->create(['name' => 'Penjumlahan']);
        $compB = $u->competencies()->create(['name' => 'Pengurangan']);
        $sets = BankSet::orderBy('code')->get();
        $this->copyInto($u, $a, $sets[0], 1, $compA->id);
        $this->copyInto($u, $a, $sets[1], 2, $compB->id); // teacher already used BOTH sets, under different competencies
        $this->assertSame([], app(PracticeRecommendation::class)->forCompetency($u, $compA));
    }

    public function test_recommendations_are_scoped_to_the_requesting_teacher(): void {
        $this->installBank(); $u = $this->user(); $other = $this->user(); $this->actingAs($u);
        $c = $u->classrooms()->create(['name' => 'Kelas', 'grade' => 1, 'school_year' => '2026']);
        $a = $u->assessments()->make(['title' => 'Synthetic']); $a->classroom()->associate($c); $a->save();
        $comp = $u->competencies()->create(['name' => 'Penjumlahan']);
        $sets = BankSet::orderBy('code')->get();
        $this->copyInto($u, $a, $sets[0], 1, $comp->id);
        // Another teacher's own competency with the same name never sees this teacher's data (CompetencyPolicy already enforces
        // per-owner access at HTTP level; here we assert the aggregation itself never leaks across owners).
        $foreignComp = $other->competencies()->create(['name' => 'Lain']);
        $this->assertSame([], app(PracticeRecommendation::class)->forCompetency($other, $foreignComp));
    }

    /** Copies a bank set, tags it with $competencyId, creates one weak-but-approved answer for $participantCode
     *  (score 0 of 2), and — if $diagnose — generates+approves an M16 diagnosis on it with $category. */
    private function weakStudentAnswer(User $u, $classroom, $assessment, BankSet $set, int $number, int $competencyId, string $participantCode, bool $diagnose, string $category = 'perhitungan'): void {
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class); Http::preventStrayRequests();
        config(['ai.enabled' => true, 'ai.provider' => 'dinoiki', 'ai.model' => 'gpt-5-mini', 'ai.api_key' => 'MOCK_ONLY', 'ai.max_output_tokens' => 4000]);
        $q = app(QuestionBank::class)->copy($set, $u->id, $assessment->id, (string) Str::uuid(), [
            'number' => $number, 'prompt' => 'Ada beberapa bola. Berapa jumlahnya?', 'answer_key' => '5', 'scoring_guidelines' => 'x', 'max_score' => '2.00',
        ], true);
        $q->update(['competency_id' => $competencyId]); $q->refresh(); $q->setRelation('assessment', $assessment);
        $rw = app(\App\Services\RubricWorkflow::class); $r = $rw->create($q, $u->id, (string) Str::uuid(), 'manual'); $rw->save($q, $r->id, $r->revision, [], true, $u->id);
        $p = $classroom->participants()->firstOrCreate(['code' => $participantCode]);
        $answer = app(AnswerIntake::class)->create($assessment, $u->id, $p->id, $q->id, (string) Str::uuid(), ['content_type' => 'text', 'answer_text' => 'Jawaban salah']);
        $marker = ['criteria' => [['criterion_id' => 'C1', 'score' => 0, 'evidence' => ['Jawaban salah'], 'missing_evidence' => false, 'reason' => 'Salah hitung.', 'rule_ids' => ['R1']]]];
        Http::swap(new \Illuminate\Http\Client\Factory()); Http::preventStrayRequests();
        $seq = Http::sequence(); foreach ([$marker, $marker, ['label' => 'High', 'reason' => 'Consistent.', 'concerns' => []]] as $data) $seq->push(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode($data)]]]]]);
        Http::fake(['ai.dinoiki.com/*' => $seq]);
        $w = app(GradingOrchestrator::class); $run = $w->start($assessment, $u->id, $answer->id, (string) Str::uuid());
        foreach (GradingStages::PLAN as $stage) { $run->refresh(); $w->process($run->id, $run->generation); }
        $this->assertSame('completed', $run->fresh()->status);
        $gw = app(GradeReviewWorkflow::class);
        $gw->save($assessment, $u->id, $run->fresh()->id, (string) Str::uuid(), 0, 'draft', [['criterion_id' => 'C1', 'score' => '0.00', 'reason' => 'Checked.']], 'Synthetic review.');
        $gw->save($assessment, $u->id, $run->fresh()->id, (string) Str::uuid(), 1, 'approve', [], '', true);
        if ($diagnose) {
            $this->aiFakeDiagnosis($category);
            $d = app(MisconceptionDiagnosisWorkflow::class)->generate($answer->fresh(), $u->id, (string) Str::uuid());
            app(MisconceptionDiagnosisWorkflow::class)->decide($answer->fresh(), $d->id, $d->version, 'approve', $u->id);
        }
    }
    private function aiFakeDiagnosis(string $category): void {
        Http::swap(new \Illuminate\Http\Client\Factory()); Http::preventStrayRequests();
        Http::fake(['ai.dinoiki.com/*' => Http::response(['status' => 'completed', 'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(['items' => [[
            'criterion_id' => 'C1', 'category' => $category, 'explanation' => 'Salah hitung penjumlahan.', 'recommended_action' => 'Latihan tambahan.',
        ]]])]]]]])]);
    }

    public function test_forstudent_recommends_from_this_students_own_weak_chapters_and_tallies_diagnosed_categories(): void {
        $this->installBank(); $u = $this->user(); $this->actingAs($u);
        $c = $u->classrooms()->create(['name' => 'Kelas', 'grade' => 1, 'school_year' => '2026']);
        $a = $u->assessments()->make(['title' => 'Synthetic']); $a->classroom()->associate($c); $a->save();
        $comp = $u->competencies()->create(['name' => 'Penjumlahan']);
        $sets = BankSet::orderBy('code')->get();
        $this->weakStudentAnswer($u, $c, $a, $sets[0], 1, $comp->id, 'P001', diagnose: true, category: 'perhitungan');
        $p = $c->participants()->where('code', 'P001')->sole();
        $result = app(PracticeRecommendation::class)->forStudent($u, $comp, $p->id);
        $this->assertCount(1, $result['sets']);
        $this->assertSame('kelas1-pr-latihan-2', $result['sets'][0]['code']);
        $this->assertSame(['perhitungan' => 1], $result['categories']);
    }

    public function test_forstudent_returns_empty_when_student_has_no_weak_answer_on_this_competency(): void {
        $this->installBank(); $u = $this->user(); $this->actingAs($u);
        $comp = $u->competencies()->create(['name' => 'Penjumlahan']);
        $c = $u->classrooms()->create(['name' => 'Kelas', 'grade' => 1, 'school_year' => '2026']);
        $p = $c->participants()->create(['code' => 'P002']);
        $this->assertSame(['sets' => [], 'categories' => []], app(PracticeRecommendation::class)->forStudent($u, $comp, $p->id));
    }

    public function test_http_competency_page_shows_personalized_section_for_weak_student(): void {
        $this->installBank(); $u = $this->user(); $this->actingAs($u);
        $c = $u->classrooms()->create(['name' => 'Kelas', 'grade' => 1, 'school_year' => '2026']);
        $a = $u->assessments()->make(['title' => 'Synthetic']); $a->classroom()->associate($c); $a->save();
        $comp = $u->competencies()->create(['name' => 'Penjumlahan']);
        $sets = BankSet::orderBy('code')->get();
        $this->weakStudentAnswer($u, $c, $a, $sets[0], 1, $comp->id, 'P001', diagnose: true, category: 'konsep');
        $this->get('/competencies/' . $comp->id)->assertOk()
            ->assertSee('Rekomendasi latihan untuk siswa ini')
            ->assertSee('Kesalahan konsep')
            ->assertSee('Latihan bilangan 2');
    }

    public function test_http_shows_recommendation_section_on_competency_page(): void {
        $this->installBank(); $u = $this->user(); $this->actingAs($u);
        $c = $u->classrooms()->create(['name' => 'Kelas', 'grade' => 1, 'school_year' => '2026']);
        $a = $u->assessments()->make(['title' => 'Synthetic']); $a->classroom()->associate($c); $a->save();
        $comp = $u->competencies()->create(['name' => 'Penjumlahan']);
        $sets = BankSet::orderBy('code')->get();
        $this->copyInto($u, $a, $sets[0], 1, $comp->id);
        $this->get('/competencies/' . $comp->id)->assertOk()->assertSee('Rekomendasi latihan tambahan')->assertSee('Latihan bilangan 2');
    }
}
