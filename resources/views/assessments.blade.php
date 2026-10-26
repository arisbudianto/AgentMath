@extends('layout')
@section('content')
<div class="assessment-page assessment-index">
<div class="assessment-hero"><div><p class="eyebrow">RUANG GURU</p><h1>Asesmen Anda</h1><p class="assessment-hero-meta">Siapkan paket soal, kelola jawaban, dan tinjau hasil belajar dalam satu tempat.</p></div><span class="assessment-status is-ready">{{ $assessments->total() }} asesmen</span></div>
<div class="assessment-index-grid">
<section class="card assessment-panel" aria-labelledby="assessment-list-title"><p class="eyebrow">PAKET TERSIMPAN</p><h2 id="assessment-list-title">Daftar asesmen</h2>
@if($errors->has('assessment'))<p class="assessment-status is-draft">{{ $errors->first('assessment') }}</p>@endif
@forelse($assessments as $assessment)<article class="assessment-list-item"><div><h3><a href="{{ route('assessments.show', $assessment) }}">{{ $assessment->title }}</a></h3><p>{{ $assessment->classroom?->name ?? 'Kelas belum dipilih' }} <span aria-hidden="true">·</span> {{ $assessment->questions_count }} soal</p></div><div class="assessment-list-actions"><a class="assessment-list-open" href="{{ route('assessments.show', $assessment) }}" aria-label="Buka asesmen {{ $assessment->title }}">Buka <span aria-hidden="true">↗</span></a><form method="POST" action="{{ route('assessments.destroy', $assessment) }}" onsubmit="return confirm('Hapus asesmen &quot;{{ $assessment->title }}&quot;? Tindakan ini tidak dapat dibatalkan.');">@csrf @method('DELETE')<button type="submit" class="assessment-list-delete" aria-label="Hapus asesmen {{ $assessment->title }}">Hapus</button></form></div></article>@empty<p>Belum ada asesmen. Buat paket pertama melalui formulir di samping.</p>@endforelse
{{ $assessments->links() }}</section>
<section class="card assessment-panel" aria-labelledby="create-assessment-title"><p class="eyebrow">PAKET BARU</p><h2 id="create-assessment-title">Buat asesmen</h2><p class="muted">Mulai dari judul dan kelas. Soal serta rubrik dapat ditambahkan setelah paket dibuat.</p>
@if($classrooms->isEmpty())<p>Siapkan kelas dan peserta sebelum melengkapi asesmen.</p><a class="assessment-inline-link" href="{{ route('classrooms.index') }}">Buat kelas <span aria-hidden="true">↗</span></a>@endif
<form method="POST" action="{{ route('assessments.store') }}">@csrf
<label>Judul<input name="title" required maxlength="200" value="{{ old('title') }}" placeholder="Contoh: Pecahan sederhana"></label>
<label>Kelas<select name="classroom_id"><option value="">Draf — kelas belum dipilih</option>@foreach($classrooms as $classroom)<option value="{{ $classroom->id }}" @selected(old('classroom_id') == $classroom->id)>{{ $classroom->name }} · {{ $classroom->school_year }}</option>@endforeach</select></label>
<label>Petunjuk umum (opsional)<textarea name="instructions" maxlength="20000" rows="3">{{ old('instructions') }}</textarea></label><button>Buat asesmen</button></form></section>
</div></div>
@endsection
