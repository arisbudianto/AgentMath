<?php

declare(strict_types=1);

use App\Models\{Assessment, BankBook, User};
use App\Services\{LearningPaths, StudentTasks};
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

if (($argv[1] ?? null) !== '--apply' || count($argv) !== 2) {
    fwrite(STDERR, "Usage: php scripts/seed-learning-path-demo.php --apply\n");
    exit(2);
}

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$emails = [
    'teacher' => 'guru-demo-kelas1@example.test',
    'pretest' => 'siswa-demo-awal@example.test',
    'material' => 'siswa-demo-materi@example.test',
    'posttest' => 'siswa-demo-posttest@example.test',
];

if (User::whereIn('email', array_values($emails))->exists()) {
    fwrite(STDERR, "Akun demo sudah ada. Tidak ada data atau kata sandi yang diubah.\n");
    exit(1);
}

$book = BankBook::where('code', 'matematika-kelas1-2022')->firstOrFail();
if ((int) $book->grade !== 1 || $book->chapters()->whereIn('number', range(1, 8))->count() !== 8) {
    throw new RuntimeException('Bank buku kelas 1 belum lengkap.');
}

$teacherPassword = bin2hex(random_bytes(12));
$studentPassword = bin2hex(random_bytes(12));
$results = DB::transaction(function () use ($book, $emails, $teacherPassword, $studentPassword): array {
    $makeUser = static function (string $name, string $email, string $password, string $role): User {
        $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
        $user->role = $role;
        $user->is_active = true;
        $user->save();
        return $user;
    };

    $teacher = $makeUser('Guru Demo Kelas 1', $emails['teacher'], $teacherPassword, 'teacher');
    $classroom = $teacher->classrooms()->create([
        'name' => 'DEMO - Matematika Kelas 1', 'grade' => 1, 'school_year' => '2026/2027',
    ]);

    $students = [];
    foreach ([
        'pretest' => ['Siswa Demo - Mulai Pretest', 'DEMO-01'],
        'material' => ['Siswa Demo - Materi 1', 'DEMO-02'],
        'posttest' => ['Siswa Demo - Post-test', 'DEMO-03'],
    ] as $stage => [$name, $code]) {
        $user = $makeUser($name, $emails[$stage], $studentPassword, 'student');
        $participant = $classroom->participants()->create(['code' => $code]);
        $user->studentProfile()->create(['participant_id' => $participant->id, 'created_by' => $teacher->id]);
        $students[$stage] = [$user, $participant];
    }

    $assessment = static function (string $title, array $questions) use ($teacher, $classroom): Assessment {
        $item = $teacher->assessments()->make([
            'title' => $title,
            'instructions' => 'Paket simulasi kelas 1. Jawab dengan teks; belum ada penilaian atau publikasi nilai otomatis.',
        ]);
        $item->classroom()->associate($classroom);
        $item->save();
        foreach ($questions as $number => [$prompt, $key]) {
            $item->questions()->create([
                'number' => $number + 1, 'prompt' => $prompt, 'answer_key' => $key,
                'scoring_guidelines' => '2 poin bila jawabannya '.$key.'; 0 poin bila tidak sesuai. Guru tetap memeriksa jawaban.',
                'max_score' => '2.00',
            ]);
        }
        return $item;
    };

    $pre = $assessment('DEMO - Pretest Kelas 1', [
        ['Berapa hasil 2 + 3?', '5'],
        ['Bilangan apa yang datang setelah 6?', '7'],
    ]);
    $post = $assessment('DEMO - Post-test Kelas 1', [
        ['Berapa hasil 10 - 4?', '6'],
        ['Berapa hasil 3 + 5?', '8'],
    ]);

    $paths = app(LearningPaths::class);
    $path = $paths->create($classroom, $teacher, $book->id, $pre->id, $post->id);
    $tasks = app(StudentTasks::class);
    $deadline = now()->addDays(89)->toIso8601String();
    $preTask = $tasks->save($pre, $teacher->id, (string) Str::uuid(), 0, 'open', $deadline, true);
    $tasks->save($post, $teacher->id, (string) Str::uuid(), 0, 'open', $deadline, true);

    foreach (['material', 'posttest'] as $stage) {
        [$user] = $students[$stage];
        foreach ($pre->questions as $question) {
            $tasks->submit($user, $preTask->id, $question->id, (string) Str::uuid(), 0, [
                'content_type' => 'text', 'answer_text' => $question->answer_key,
            ], true);
        }
    }

    $postParticipant = $students['posttest'][1];
    for ($number = 1; $number <= 8; $number++) {
        $chapter = $paths->chapter($path, $number);
        foreach ($paths->pages($chapter) as $page) {
            $paths->markPage($path, $postParticipant, $page);
        }
        $paths->completeMaterial($path, $postParticipant, $number);
    }

    $progress = [];
    foreach ($students as $stage => [, $participant]) {
        $progress[$stage] = $paths->current($path, $participant);
    }
    if ($progress !== ['pretest' => 0, 'material' => 1, 'posttest' => 9]) {
        throw new RuntimeException('Kemajuan demo tidak sesuai skenario.');
    }

    return ['classroom_id' => $classroom->id, 'pre_assessment_id' => $pre->id,
        'post_assessment_id' => $post->id, 'progress' => $progress];
});

echo json_encode([
    'status' => 'DEMO_READY', 'teacher_email' => $emails['teacher'],
    'teacher_password' => $teacherPassword, 'student_password' => $studentPassword,
    'student_emails' => array_intersect_key($emails, array_flip(['pretest', 'material', 'posttest'])),
    'classroom_id' => $results['classroom_id'], 'progress' => $results['progress'],
], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
