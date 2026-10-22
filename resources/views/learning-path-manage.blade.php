@extends('layout')
@section('content')
<div class="learning-page">
<p><a href="{{ route('classrooms.show',$classroom) }}">← Kembali ke kelas</a></p>
<p class="eyebrow">JALUR BELAJAR KELAS 1</p><h1>Pretest → Materi 1–8 → Post-test</h1>
<p>Setiap siswa membuka tahap berikutnya setelah menyelesaikan tahap sebelumnya. Materi memakai halaman buku yang tersimpan privat; pretest dan post-test memakai paket asesmen yang Anda siapkan dan buka sebagai tugas.</p>
@if($path)
<section class="card"><h2>Jalur {{ $classroom->name }} sudah disiapkan</h2><p>Buku: <strong>{{ $path->book->title }}</strong></p><div class="learning-teacher-tests"><div><strong>Pretest</strong><p>{{ $path->preAssessment->title }}</p><a href="{{ route('tasks.manage',$path->preAssessment) }}">Atur tugas pretest →</a></div><div><strong>Post-test</strong><p>{{ $path->postAssessment->title }}</p><a href="{{ route('tasks.manage',$path->postAssessment) }}">Atur tugas post-test →</a></div></div><p class="muted">Kedua tes tetap diperiksa guru. Siswa maju setelah semua soal dikirim; nilai tidak terbit otomatis.</p></section>
<section class="card"><h2>Kemajuan peserta</h2><p class="muted">Kode peserta tanpa nama. Tahap 0 = pretest, 1–8 = materi, 9 = post-test, 10 = selesai.</p>@foreach($students as $student)<div class="learning-progress-row"><span>{{ $student->code }}</span><strong>{{ $student->studentProfile ? app(\App\Services\LearningPaths::class)->current($path,$student) : 'Belum punya akun siswa' }}</strong></div>@endforeach</section>
@else
<section class="card"><h2>Siapkan jalur kelas</h2>@if($classroom->grade!==1)<p>Jalur buku ini tersedia untuk kelas 1. Jenjang kelas ini saat ini kelas {{ $classroom->grade }}.</p>@elseif($books->isEmpty())<p>Buku kelas 1 belum tersedia di bank soal.</p>@else
<p>Pilih satu buku kelas 1 dan dua asesmen berbeda dari kelas ini. Keduanya perlu soal, kunci, pedoman, dan skor maksimum yang lengkap. Pilihan dikunci setelah dibuat agar kemajuan siswa tetap konsisten.</p>
<form method="POST" action="{{ route('learning.store',$classroom) }}">@csrf
<label>Buku kelas 1<select name="bank_book_id" required>@foreach($books as $book)<option value="{{ $book->id }}">{{ $book->title }}</option>@endforeach</select></label>
<label>Asesmen pretest<select name="pre_assessment_id" required><option value="">Pilih paket</option>@foreach($assessments as $a)<option value="{{ $a->id }}" @selected(old('pre_assessment_id')==$a->id)>{{ $a->title }} · {{ $a->questions->count() }} soal</option>@endforeach</select></label>
<label>Asesmen post-test<select name="post_assessment_id" required><option value="">Pilih paket</option>@foreach($assessments as $a)<option value="{{ $a->id }}" @selected(old('post_assessment_id')==$a->id)>{{ $a->title }} · {{ $a->questions->count() }} soal</option>@endforeach</select></label>
<button>Siapkan jalur belajar</button></form><p><a href="{{ route('assessments.index') }}">Buat paket pretest / post-test →</a></p>@endif</section>
@endif
</div>
@endsection
