@extends('layout')
@section('content')
<h1>Dashboard kompetensi</h1>
<p class="muted">Rata-rata skor dihitung dari keputusan nilai yang sudah disahkan guru, pada soal yang ditautkan ke kompetensi. Tautkan soal ke kompetensi lewat halaman ubah soal.</p>

<section class="card"><h2>Pencapaian per kompetensi</h2>
@forelse($overview as $row)
<p><a href="{{ route('competencies.show', $row['competency_id']) }}"><strong>{{ $row['name'] }}</strong></a> — {{ $row['percent'] === null ? 'belum ada data' : $row['percent'].'%' }} <span class="muted">({{ $row['answers'] }} jawaban disahkan)</span></p>
@empty
<p>Belum ada data. Tautkan soal ke kompetensi dan sahkan nilai jawaban untuk melihat pencapaian di sini.</p>
@endforelse
</section>

<section class="card"><h2>Kelola kompetensi</h2>
@if($errors->any())<p class="assessment-status is-draft">{{ $errors->first() }}</p>@endif
@forelse($competencies as $competency)
<details class="history"><summary>{{ $competency->name }} <span class="muted">({{ $competency->questions_count }} soal)</span></summary>
<form method="POST" action="{{ route('competencies.update', $competency) }}">@csrf @method('PATCH')
<label>Nama<input name="name" required maxlength="120" value="{{ $competency->name }}"></label>
<label>Deskripsi<textarea name="description" maxlength="2000" rows="2">{{ $competency->description }}</textarea></label>
<button>Simpan</button>
</form>
<form method="POST" action="{{ route('competencies.destroy', $competency) }}" onsubmit="return confirm('Hapus kompetensi {{ $competency->name }}?');">@csrf @method('DELETE')
<button class="secondary">Hapus kompetensi</button>
</form>
</details>
@empty
<p>Belum ada kompetensi.</p>
@endforelse
</section>

<section class="card"><h2>Tambah kompetensi</h2>
<form method="POST" action="{{ route('competencies.store') }}">@csrf
<label>Nama<input name="name" required maxlength="120" value="{{ old('name') }}"></label>
<label>Deskripsi (opsional)<textarea name="description" maxlength="2000" rows="2">{{ old('description') }}</textarea></label>
<button>Tambah kompetensi</button>
</form>
</section>
@endsection
