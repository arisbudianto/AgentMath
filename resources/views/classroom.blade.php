@extends('layout')
@section('content')
<a href="{{ route('classrooms.index') }}">← Semua kelas</a><p class="eyebrow">KELAS MILIK ANDA</p><h1>{{ $classroom->name }}</h1>
<section class="card"><h2>Pengaturan kelas</h2><form method="POST" action="{{ route('classrooms.update', $classroom) }}">@csrf @method('PATCH')
<label>Nama kelas<input name="name" maxlength="120" required value="{{ old('name', $classroom->name) }}"></label>
<div class="form-grid"><label>Jenjang SD<select name="grade" required>@for($i=1;$i<=6;$i++)<option value="{{ $i }}" @selected(old('grade', $classroom->grade) == $i)>Kelas {{ $i }}</option>@endfor</select></label>
<label>Tahun ajaran / semester<input name="school_year" maxlength="30" required value="{{ old('school_year', $classroom->school_year) }}"></label></div><button>Simpan kelas</button></form></section>
<section class="card"><h2>Peserta berkode</h2><p>Gunakan kode seperti P001. Simpan pemetaan kode ke nama siswa secara terpisah. Jangan masukkan nama, NISN, atau identitas langsung siswa.</p>
<form method="POST" action="{{ route('participants.store', $classroom) }}">@csrf<label>Kode peserta baru<input name="code" maxlength="40" pattern="[A-Za-z0-9][A-Za-z0-9_-]*" required value="{{ old('code') }}" placeholder="P001" aria-describedby="code-help"></label><p class="muted" id="code-help">Huruf, angka, tanda hubung, dan garis bawah. Kode disimpan dengan huruf kapital dan harus unik dalam kelas.</p><button>Tambah peserta</button></form>
@forelse($participants as $participant)<article><form method="POST" action="{{ route('participants.update', [$classroom, $participant]) }}">@csrf @method('PATCH')<label>Kode peserta #{{ $participant->id }}<input name="code" maxlength="40" pattern="[A-Za-z0-9][A-Za-z0-9_-]*" required value="{{ $participant->code }}"></label><button class="secondary">Simpan kode</button></form></article>@empty<p class="muted">Belum ada peserta.</p>@endforelse {{ $participants->links() }}</section>
<section class="card"><h2>Asesmen kelas</h2>@forelse($assessments as $assessment)<p><a href="{{ route('assessments.show', $assessment) }}">{{ $assessment->title }}</a></p>@empty<p>Belum ada asesmen yang terhubung.</p>@endforelse {{ $assessments->links() }}<a class="button" href="{{ route('assessments.index') }}">Kelola asesmen</a></section>
@endsection
