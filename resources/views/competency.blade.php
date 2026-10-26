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
@endsection
