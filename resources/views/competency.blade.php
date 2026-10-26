@extends('layout')
@section('content')
<a href="{{ route('competencies.index') }}">← Dashboard kompetensi</a>
<h1>{{ $competency->name }}</h1>
@if($competency->description)<p class="muted">{{ $competency->description }}</p>@endif

<section class="card"><h2>Per siswa</h2>
@forelse($students as $row)
<p><strong>{{ $row['code'] }}</strong> · {{ $row['classroom'] }} — {{ $row['percent'] === null ? 'belum ada data' : $row['percent'].'%' }} <span class="muted">({{ $row['answers'] }} jawaban disahkan)</span></p>
@empty
<p>Belum ada jawaban yang disahkan pada soal dengan kompetensi ini.</p>
@endforelse
</section>

<section class="card"><h2>Rekomendasi latihan tambahan</h2>
<p class="muted">Soal dari bab buku yang sama dengan soal kompetensi ini, dari bank soal, yang belum pernah Anda pakai di asesmen manapun. Bukan saran otomatis AI — murni berdasarkan kesamaan bab; pilih dan tinjau sendiri sebelum dipakai.</p>
@forelse($recommendations as $set)
<p><a href="{{ route('bank.show', $set['id']) }}">{{ $set['title'] }}</a> <span class="muted">({{ $set['code'] }} · {{ $set['kind'] }} · {{ $set['book'] }} — {{ $set['chapter'] }})</span></p>
@empty
<p>Belum ada rekomendasi. Soal pada kompetensi ini belum berasal dari bank soal, atau semua set terkait sudah pernah Anda pakai.</p>
@endforelse
</section>
@endsection
