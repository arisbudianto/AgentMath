@extends('layout')
@section('content')
<a href="{{ route('competencies.index') }}">← Dashboard kompetensi</a>
<h1>{{ $competency->name }}</h1>
@if($competency->description)<p class="muted">{{ $competency->description }}</p>@endif

@php($categoryLabels = \App\Models\AnswerDiagnosis::categories())

<section class="card"><h2>Per siswa</h2>
<p class="muted">Siswa dengan skor di bawah {{ (int) \App\Services\PracticeRecommendation::WEAK_THRESHOLD }}% mendapat rekomendasi latihan yang dipersonalisasi berdasarkan jawabannya sendiri yang lemah dan kategori kesalahan yang sudah terdiagnosis (jika ada diagnosis M16 yang disahkan).</p>
@forelse($students as $row)
<article class="assessment-question">
<p><strong>{{ $row['code'] }}</strong> · {{ $row['classroom'] }} — {{ $row['percent'] === null ? 'belum ada data' : $row['percent'].'%' }} <span class="muted">({{ $row['answers'] }} jawaban disahkan)</span></p>
@if($row['weak'])
<details>
<summary>Rekomendasi latihan untuk siswa ini</summary>
@if(!empty($row['personalized']['categories']))
<p class="muted">Kategori kesalahan yang sering terdiagnosis pada siswa ini (dari diagnosis yang disahkan):
@foreach($row['personalized']['categories'] as $category => $count){{ $categoryLabels[$category] ?? $category }} ({{ $count }}x){{ !$loop->last ? ', ' : '' }}@endforeach
</p>
@endif
@forelse($row['personalized']['sets'] as $set)
<p><a href="{{ route('bank.show', $set['id']) }}">{{ $set['title'] }}</a> <span class="muted">({{ $set['code'] }} · {{ $set['kind'] }} · {{ $set['book'] }} — {{ $set['chapter'] }})</span></p>
@empty
<p class="muted">Jawaban lemah siswa ini belum berasal dari soal bank, atau semua set terkait sudah pernah Anda pakai.</p>
@endforelse
</details>
@endif
</article>
@empty
<p>Belum ada jawaban yang disahkan pada soal dengan kompetensi ini.</p>
@endforelse
</section>

<section class="card"><h2>Rekomendasi latihan tambahan (seluruh kelas)</h2>
<p class="muted">Soal dari bab buku yang sama dengan soal kompetensi ini, dari bank soal, yang belum pernah Anda pakai di asesmen manapun. Bukan saran otomatis AI — murni berdasarkan kesamaan bab; pilih dan tinjau sendiri sebelum dipakai.</p>
@forelse($recommendations as $set)
<p><a href="{{ route('bank.show', $set['id']) }}">{{ $set['title'] }}</a> <span class="muted">({{ $set['code'] }} · {{ $set['kind'] }} · {{ $set['book'] }} — {{ $set['chapter'] }})</span></p>
@empty
<p>Belum ada rekomendasi. Soal pada kompetensi ini belum berasal dari bank soal, atau semua set terkait sudah pernah Anda pakai.</p>
@endforelse
</section>
@endsection
