@extends('layout')
@section('content')
<p class="eyebrow">ASESMEN</p><h1>Asesmen Anda</h1>
<section class="card"><h2>Buat asesmen</h2>
@if($classrooms->isEmpty())<p>Siapkan kelas dan peserta sebelum melengkapi asesmen.</p><a href="{{ route('classrooms.index') }}">Buat kelas</a>@endif
<form method="POST" action="{{ route('assessments.store') }}">@csrf
<label>Judul<input name="title" required maxlength="200" value="{{ old('title') }}" placeholder="Contoh: Pecahan sederhana"></label>
<label>Kelas<select name="classroom_id"><option value="">Draf — kelas belum dipilih</option>@foreach($classrooms as $classroom)<option value="{{ $classroom->id }}" @selected(old('classroom_id') == $classroom->id)>{{ $classroom->name }} · {{ $classroom->school_year }}</option>@endforeach</select></label>
<label>Petunjuk umum (opsional)<textarea name="instructions" maxlength="20000" rows="3">{{ old('instructions') }}</textarea></label><button>Buat asesmen</button></form></section>
<section class="card"><h2>Daftar asesmen</h2>@forelse($assessments as $assessment)<article><h3><a href="{{ route('assessments.show', $assessment) }}">{{ $assessment->title }}</a></h3><p class="muted">{{ $assessment->classroom?->name ?? 'Kelas belum dipilih' }} · {{ $assessment->questions_count }} soal</p></article>@empty<p>Belum ada asesmen.</p>@endforelse {{ $assessments->links() }}</section>
@endsection
