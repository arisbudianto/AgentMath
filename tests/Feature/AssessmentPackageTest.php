<?php

namespace Tests\Feature;

use App\Models\{Assessment, Classroom, Question, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentPackageTest extends TestCase
{
    use RefreshDatabase;

    private function teacher(string $role = 'teacher'): User
    {
        $user = new User(['name' => 'Test teacher', 'email' => uniqid().'@example.test', 'password' => 'Testing123456']);
        $user->role = $role;
        $user->is_active = true;
        $user->save();
        return $user;
    }

    private function classroom(User $owner, string $name = 'Kelas 4A'): Classroom
    {
        return $owner->classrooms()->create(['name' => $name, 'grade' => 4, 'school_year' => '2026/2027']);
    }

    private function questionData(array $overrides = []): array
    {
        return array_replace(['number' => 1, 'prompt' => 'Hitung 1/2 + 1/4.', 'answer_key' => '3/4', 'scoring_guidelines' => 'Skor penuh untuk 3/4; 1 poin untuk penyamaan penyebut yang benar.', 'max_score' => '2.50'], $overrides);
    }

    public function test_teacher_can_build_and_edit_complete_package(): void
    {
        $owner = $this->teacher();
        $this->actingAs($owner)->post('/classrooms', ['name' => 'Pecahan 4A', 'grade' => 4, 'school_year' => '2026/2027'])->assertRedirect();
        $classroom = $owner->classrooms()->firstOrFail();
        $this->get('/classrooms')->assertOk()->assertSee('Pecahan 4A');
        $this->patch('/classrooms/'.$classroom->id, ['name' => 'Pecahan 4B', 'grade' => 4, 'school_year' => '2026/2027'])->assertRedirect();
        $this->post('/classrooms/'.$classroom->id.'/participants', ['code' => 'p001'])->assertRedirect();
        $this->get('/classrooms/'.$classroom->id)->assertOk()->assertSee('P001');
        $this->post('/assessments', ['title' => 'Latihan pecahan', 'classroom_id' => $classroom->id, 'instructions' => 'Tunjukkan langkah.'])->assertRedirect();
        $assessment = $owner->assessments()->firstOrFail();
        $this->get('/assessments/'.$assessment->id)->assertOk()->assertSee('Draf')->assertSee('Tambahkan setidaknya satu soal');
        $this->post('/assessments/'.$assessment->id.'/questions', $this->questionData())->assertRedirect();
        $question = $assessment->questions()->firstOrFail();
        $this->get('/assessments/'.$assessment->id)->assertOk()->assertSee('Paket lengkap')->assertSee('2,50')->assertSee('3/4');
        $this->get('/assessments/'.$assessment->id.'/questions/'.$question->id.'/edit')->assertOk()->assertSee('Ubah soal 1');
        $this->patch('/assessments/'.$assessment->id.'/questions/'.$question->id, $this->questionData(['max_score' => '3.25']))->assertRedirect('/assessments/'.$assessment->id);
        $this->assertDatabaseHas('questions', ['id' => $question->id, 'max_score' => '3.25']);
        $this->get('/assessments')->assertOk()->assertSee('Latihan pecahan')->assertSee('Pecahan 4B');
    }

    public function test_class_and_participant_access_is_isolated_including_nested_identifiers(): void
    {
        $a = $this->teacher(); $b = $this->teacher();
        $own = $this->classroom($a); $foreign = $this->classroom($b, 'PRIVATE CLASS');
        $participant = $foreign->participants()->create(['code' => 'PRIVATE-001']);
        $this->actingAs($a)->get('/classrooms')->assertOk()->assertDontSee('PRIVATE CLASS');
        $this->get('/classrooms/'.$foreign->id)->assertForbidden();
        $this->patch('/classrooms/'.$foreign->id, ['name' => 'Hacked'])->assertForbidden();
        $this->post('/classrooms/'.$foreign->id.'/participants', ['code' => 'HACK'])->assertForbidden();
        $this->patch('/classrooms/'.$foreign->id.'/participants/'.$participant->id, ['code' => 'HACK'])->assertForbidden();
        $this->patch('/classrooms/'.$own->id.'/participants/'.$participant->id, ['code' => 'HACK'])->assertNotFound();
        $this->assertDatabaseHas('participants', ['id' => $participant->id, 'code' => 'PRIVATE-001']);
    }

    public function test_assessment_cannot_be_linked_to_other_teachers_class(): void
    {
        $a = $this->teacher(); $b = $this->teacher();
        $foreign = $this->classroom($b);
        $this->actingAs($a)->post('/assessments', ['title' => 'Invalid', 'classroom_id' => $foreign->id])->assertSessionHasErrors('classroom_id');
        $this->assertDatabaseMissing('assessments', ['title' => 'Invalid']);
        $own = $a->assessments()->create(['title' => 'Own']);
        $this->patch('/assessments/'.$own->id, ['title' => 'Changed', 'classroom_id' => $foreign->id])->assertSessionHasErrors('classroom_id');
        $this->assertNull($own->fresh()->classroom_id);
        $this->assertSame('Own', $own->fresh()->title);
    }

    public function test_nested_question_access_cannot_escape_its_assessment(): void
    {
        $a = $this->teacher(); $b = $this->teacher();
        $own = $a->assessments()->create(['title' => 'Own']);
        $otherOwn = $a->assessments()->create(['title' => 'Other own']);
        $foreign = $b->assessments()->create(['title' => 'Foreign']);
        $question = $foreign->questions()->create($this->questionData());
        $otherQuestion = $otherOwn->questions()->create($this->questionData());
        $this->actingAs($a)->post('/assessments/'.$foreign->id.'/questions', $this->questionData())->assertForbidden();
        $this->get('/assessments/'.$foreign->id.'/questions/'.$question->id.'/edit')->assertForbidden();
        $this->patch('/assessments/'.$foreign->id.'/questions/'.$question->id, $this->questionData())->assertForbidden();
        foreach ([$question, $otherQuestion] as $record) {
            $this->get('/assessments/'.$own->id.'/questions/'.$record->id.'/edit')->assertNotFound();
            $this->patch('/assessments/'.$own->id.'/questions/'.$record->id, $this->questionData())->assertNotFound();
        }
    }

    public function test_owner_and_parent_ids_are_assigned_by_server(): void
    {
        $a = $this->teacher(); $b = $this->teacher();
        $foreign = $this->classroom($b);
        $this->actingAs($a)->post('/classrooms', ['name' => 'Own', 'grade' => 1, 'school_year' => '2026', 'owner_id' => $b->id])->assertRedirect();
        $own = $a->classrooms()->firstOrFail();
        $this->post('/classrooms/'.$own->id.'/participants', ['code' => 'P001', 'classroom_id' => $foreign->id])->assertRedirect();
        $this->assertDatabaseHas('participants', ['code' => 'P001', 'classroom_id' => $own->id]);
        $assessment = $a->assessments()->create(['title' => 'Own']);
        $other = $b->assessments()->create(['title' => 'Foreign']);
        $this->post('/assessments/'.$assessment->id.'/questions', $this->questionData(['assessment_id' => $other->id]))->assertRedirect();
        $this->assertDatabaseHas('questions', ['assessment_id' => $assessment->id, 'number' => 1]);
        $this->assertSame(0, $other->questions()->count());
    }

    public function test_participant_codes_are_normalized_and_unique_per_class(): void
    {
        $a = $this->teacher(); $class = $this->classroom($a); $second = $this->classroom($a, 'Second');
        $path = '/classrooms/'.$class->id.'/participants';
        $this->actingAs($a)->post($path, ['code' => ' p001 '])->assertRedirect();
        $this->post($path, ['code' => 'P001'])->assertSessionHasErrors('code');
        $this->post($path, ['code' => 'Nama Siswa'])->assertSessionHasErrors('code');
        $this->post($path, ['code' => ['invalid']])->assertSessionHasErrors('code');
        $this->post('/classrooms/'.$second->id.'/participants', ['code' => 'p001'])->assertRedirect();
        $record = $class->participants()->firstOrFail();
        $this->patch($path.'/'.$record->id, ['code' => 'P001'])->assertSessionDoesntHaveErrors();
        $this->patch($path.'/'.$record->id, ['code' => 'P002'])->assertRedirect();
        $this->assertDatabaseHas('participants', ['id' => $record->id, 'code' => 'P002']);
    }

    public function test_question_validation_preserves_existing_data_and_enforces_unique_numbers(): void
    {
        $a = $this->teacher(); $assessment = $a->assessments()->create(['title' => 'Math']);
        $path = '/assessments/'.$assessment->id.'/questions';
        $this->actingAs($a)->post($path, $this->questionData())->assertSessionDoesntHaveErrors();
        $question = $assessment->questions()->firstOrFail();
        $this->post($path, $this->questionData())->assertSessionHasErrors('number');
        $this->patch($path.'/'.$question->id, $this->questionData())->assertSessionDoesntHaveErrors();
        foreach (['0', '-1', '10000.01', '1.234', 'NaN'] as $score) {
            $this->patch($path.'/'.$question->id, $this->questionData(['max_score' => $score]))->assertSessionHasErrors('max_score');
        }
        $this->post($path, $this->questionData(['number' => 0, 'prompt' => ' ', 'answer_key' => '', 'scoring_guidelines' => '']))->assertSessionHasErrors(['number', 'prompt', 'answer_key', 'scoring_guidelines']);
        $this->assertSame('2.50', $question->fresh()->max_score);
        $this->assertSame(1, Question::count());
    }

    public function test_package_readiness_requires_class_roster_and_questions_and_is_recomputed(): void
    {
        $a = $this->teacher(); $class = $this->classroom($a);
        $assessment = $a->assessments()->create(['title' => 'Draft']);
        $this->assertNotEmpty($assessment->fresh()->packageIssues());
        $assessment->classroom()->associate($class); $assessment->save();
        $assessment->questions()->create($this->questionData());
        $this->assertNotEmpty($assessment->fresh()->packageIssues());
        $class->participants()->create(['code' => 'P001']);
        $this->assertSame([], $assessment->fresh()->packageIssues());
        $this->actingAs($a)->patch('/assessments/'.$assessment->id, ['title' => 'Draft', 'classroom_id' => ''])->assertRedirect();
        $this->get('/assessments/'.$assessment->id)->assertOk()->assertSee('Draf')->assertDontSee('Paket lengkap');
    }

    public function test_admin_guests_and_disabled_users_cannot_access_m01(): void
    {
        $a = $this->teacher(); $class = $this->classroom($a);
        $assessment = $a->assessments()->create(['title' => 'Math']);
        $this->get('/classrooms')->assertRedirect('/login');
        $this->actingAs($this->teacher('admin'))->get('/classrooms')->assertForbidden();
        $this->post('/classrooms', [])->assertForbidden();
        $this->post('/classrooms/'.$class->id.'/participants', [])->assertForbidden();
        $this->post('/assessments/'.$assessment->id.'/questions', [])->assertForbidden();
        $a->is_active = false; $a->save();
        $this->actingAs($a)->get('/classrooms/'.$class->id)->assertRedirect('/login');
    }

    public function test_primary_school_grade_is_limited_and_markup_is_escaped(): void
    {
        $a = $this->teacher();
        $this->actingAs($a)->post('/classrooms', ['name' => 'Bad', 'grade' => 7, 'school_year' => '2026'])->assertSessionHasErrors('grade');
        $class = $this->classroom($a, '<script>alert(1)</script>');
        $this->get('/classrooms/'.$class->id)->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
        $assessment = $a->assessments()->create(['title' => 'Markup']);
        $assessment->questions()->create($this->questionData(['prompt' => '<script>alert(2)</script>']));
        $this->get('/assessments/'.$assessment->id)->assertOk()->assertDontSee('<script>alert(2)</script>', false)->assertSee('&lt;script&gt;', false);
    }

    public function test_roster_pagination_and_two_owners_can_reuse_question_numbers(): void
    {
        $a = $this->teacher(); $b = $this->teacher(); $class = $this->classroom($a);
        for ($i = 1; $i <= 31; $i++) $class->participants()->create(['code' => sprintf('P%03d', $i)]);
        $this->actingAs($a)->get('/classrooms/'.$class->id)->assertOk()->assertSee('value="P001"', false)->assertDontSee('value="P031"', false)->assertSee('Berikutnya');
        $this->get('/classrooms/'.$class->id.'?page=2')->assertOk()->assertSee('value="P031"', false)->assertDontSee('value="P001"', false);
        foreach ([$a, $b] as $owner) {
            $assessment = $owner->assessments()->create(['title' => 'Math']);
            $this->actingAs($owner)->post('/assessments/'.$assessment->id.'/questions', $this->questionData())->assertSessionDoesntHaveErrors();
        }
        $this->assertSame(2, Question::where('number', 1)->count());
    }

    public function test_teacher_can_delete_an_empty_assessment_but_not_one_with_questions(): void
    {
        $owner = $this->teacher();
        $empty = $owner->assessments()->create(['title' => 'Latihan Bab 2']);
        $withQuestion = $owner->assessments()->create(['title' => 'Latihan Bab 1']);
        $withQuestion->questions()->create($this->questionData());

        $this->actingAs($owner)->delete('/assessments/'.$empty->id)->assertRedirect('/assessments');
        $this->assertDatabaseMissing('assessments', ['id' => $empty->id]);

        $this->delete('/assessments/'.$withQuestion->id)
            ->assertRedirect('/assessments')
            ->assertSessionHasErrors('assessment');
        $this->assertDatabaseHas('assessments', ['id' => $withQuestion->id]);
    }

    public function test_teacher_cannot_delete_another_teachers_assessment(): void
    {
        $a = $this->teacher(); $b = $this->teacher();
        $foreign = $b->assessments()->create(['title' => 'Bukan milikku']);
        $this->actingAs($a)->delete('/assessments/'.$foreign->id)->assertForbidden();
        $this->assertDatabaseHas('assessments', ['id' => $foreign->id]);
    }

    public function test_teacher_can_delete_a_question_then_delete_the_now_empty_assessment(): void
    {
        $owner = $this->teacher();
        $assessment = $owner->assessments()->create(['title' => 'Latihan Bab 1']);
        $question = $assessment->questions()->create($this->questionData());

        $this->actingAs($owner)
            ->delete('/assessments/'.$assessment->id.'/questions/'.$question->id)
            ->assertRedirect('/assessments/'.$assessment->id);
        $this->assertDatabaseMissing('questions', ['id' => $question->id]);

        // Assessment had only that one question, so it can now be deleted too.
        $this->delete('/assessments/'.$assessment->id)->assertRedirect('/assessments');
        $this->assertDatabaseMissing('assessments', ['id' => $assessment->id]);
    }

    public function test_question_with_a_rubric_cannot_be_deleted(): void
    {
        $owner = $this->teacher();
        $assessment = $owner->assessments()->create(['title' => 'Latihan Bab 1']);
        $question = $assessment->questions()->create($this->questionData());
        $question->setRelation('assessment', $assessment);
        app(\App\Services\RubricWorkflow::class)->create($question, $owner->id, (string) \Illuminate\Support\Str::uuid(), 'manual');

        $this->actingAs($owner)
            ->delete('/assessments/'.$assessment->id.'/questions/'.$question->id)
            ->assertRedirect('/assessments/'.$assessment->id)
            ->assertSessionHasErrors('question');
        $this->assertDatabaseHas('questions', ['id' => $question->id]);
    }

    public function test_teacher_cannot_delete_another_teachers_question(): void
    {
        $a = $this->teacher(); $b = $this->teacher();
        $assessment = $b->assessments()->create(['title' => 'Bukan milikku']);
        $question = $assessment->questions()->create($this->questionData());
        $this->actingAs($a)->delete('/assessments/'.$assessment->id.'/questions/'.$question->id)->assertForbidden();
        $this->assertDatabaseHas('questions', ['id' => $question->id]);
    }
}
