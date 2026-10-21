@extends('layout')
@section('content')
<p><a class="button" href="{{ route('tasks.manage',$assessment) }}">Tugas siswa · buka dan tutup pengiriman</a></p>
<p><a class="button" href="{{ route('publications.index',$assessment) }}">Publikasi nilai siswa · terbit dan cabut</a></p>
<a href="{{ route('assessments.index') }}">← Semua asesmen</a><p class="eyebrow">PAKET ASESMEN MILIK ANDA</p><h1>{{ $assessment->title }}</h1>
<p><a class="button" href="{{ route('answers.index',$assessment) }}">Kelola jawaban · ketikan, CSV, foto</a></p>
<p><a class="button" href="{{ route('grading.index',$assessment) }}">Proses penilaian · antrean dan riwayat</a></p>
<p><a class="button" href="{{ route('results.index',$assessment) }}">Rekap peserta dan ekspor CSV</a></p>
<p><a class="button" href="{{ route('evaluations.index',$assessment) }}">Evaluasi penelitian · acuan manusia dan eksperimen</a></p>
<p><a class="button" href="{{ route('audit.index',$assessment) }}">Operasional dan audit AI · token, durasi, biaya</a></p>
<section class="card"><h2>Kelengkapan paket</h2>
@if($issues)<p class="badge">Draf · belum lengkap</p><ul>@foreach($issues as $issue)<li>{{ $issue }}</li>@endforeach</ul>
@else<p class="badge ready">Paket lengkap</p><p>Kelas, peserta, soal, kunci, pedoman, dan skor maksimum sudah tersedia.</p>@endif
<p>{{ $assessment->questions->count() }} soal · Total skor maksimum: <strong>{{ number_format($assessment->questions->sum('max_score'), 2, ',', '.') }}</strong></p>
<p class="muted">Status ini memeriksa kelengkapan data. Susun dan sahkan rubrik setiap soal melalui bagian Rubrik di bawah. Setelah rubrik disahkan, jalankan penilaian melalui Proses penilaian di atas. Hasil AI berupa usulan yang perlu ditinjau guru.</p>
@if($assessment->classroom)<p>Kelas: <a href="{{ route('classrooms.show', $assessment->classroom) }}">{{ $assessment->classroom->name }}</a></p>@endif</section>
<section class="card"><h2>Pengaturan asesmen</h2><form method="POST" action="{{ route('assessments.update', $assessment) }}">@csrf @method('PATCH')
<label>Judul<input name="title" maxlength="200" required value="{{ old('title', $assessment->title) }}"></label>
<label>Kelas<select name="classroom_id"><option value="">Draf — kelas belum dipilih</option>@foreach($classrooms as $classroom)<option value="{{ $classroom->id }}" @selected(old('classroom_id', $assessment->classroom_id) == $classroom->id)>{{ $classroom->name }} · {{ $classroom->school_year }}</option>@endforeach</select></label>
<label>Petunjuk umum (opsional)<textarea name="instructions" maxlength="20000" rows="3">{{ old('instructions', $assessment->instructions) }}</textarea></label><button>Simpan asesmen</button></form></section>
<section class="card"><h2>Soal dan dasar penilaian</h2>@forelse($assessment->questions as $question)<article><h3>Soal {{ $question->number }} <span class="muted">· Maksimum {{ $question->max_score }}</span></h3><p class="preserve">{{ $question->prompt }}</p><details><summary>Lihat kunci dan pedoman</summary><h3>Kunci jawaban</h3><p class="preserve">{{ $question->answer_key }}</p><h3>Pedoman penilaian</h3><p class="preserve">{{ $question->scoring_guidelines }}</p></details><p><a href="{{ route('questions.edit', [$assessment, $question]) }}">Ubah soal {{ $question->number }}</a></p></article>@empty<p>Belum ada soal. Tambahkan soal beserta dasar penilaiannya.</p>@endforelse</section>
<section class="card"><h2>Rubrik per soal</h2><p>Setiap soal memiliki aturan sumber, kriteria, dan riwayat versi rubrik tersendiri.</p>@forelse($assessment->questions as $item)<p><a href="{{ route('rubrics.index',[$assessment,$item]) }}">Soal {{ $item->number }} — kelola pedoman dan rubrik</a></p>@empty<p>Tambahkan soal terlebih dahulu.</p>@endforelse</section>
<section class="card"><h2>Tambah soal</h2><form method="POST" action="{{ route('questions.store', $assessment) }}">@csrf
@include('question-fields', ['question' => null, 'nextNumber' => ($assessment->questions->max('number') ?? 0) + 1])
<button>Tambah soal</button></form></section>
@endsection
