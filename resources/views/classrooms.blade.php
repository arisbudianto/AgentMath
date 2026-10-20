@extends('layout')
@section('content')
<p class="eyebrow">MATEMATIKA SEKOLAH DASAR</p><h1>Kelas Anda</h1>
<section class="card"><h2>Buat kelas</h2>
<form method="POST" action="{{ route('classrooms.store') }}">@csrf
<label>Nama kelas<input name="name" required maxlength="120" value="{{ old('name') }}" placeholder="Contoh: Matematika 4A"></label>
<div class="form-grid"><label>Jenjang SD<select name="grade" required><option value="">Pilih jenjang</option>@for($i=1;$i<=6;$i++)<option value="{{ $i }}" @selected(old('grade') == $i)>Kelas {{ $i }}</option>@endfor</select></label>
<label>Tahun ajaran / semester<input name="school_year" required maxlength="30" value="{{ old('school_year') }}" placeholder="Contoh: 2026/2027 · Ganjil"></label></div>
<button>Buat kelas</button></form></section>
<section class="card"><h2>Daftar kelas</h2>
@forelse($classrooms as $classroom)<article><h3><a href="{{ route('classrooms.show', $classroom) }}">{{ $classroom->name }}</a></h3><p class="muted">Kelas {{ $classroom->grade }} · {{ $classroom->school_year }} · {{ $classroom->participants_count }} peserta · {{ $classroom->assessments_count }} asesmen</p></article>
@empty<p>Belum ada kelas. Buat kelas pertama untuk menyiapkan peserta dan asesmen.</p>@endforelse
{{ $classrooms->links() }}</section>
@endsection
