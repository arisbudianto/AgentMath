@extends('layout')
@section('content')
<a href="{{ route('bank.index',['book'=>$set->chapter->bank_book_id,'chapter'=>$set->bank_chapter_id]) }}">← Bank soal bab {{ $set->chapter->number }}</a>
<h1>{{ $set->title }}</h1><p>{{ $set->chapter->book->title }} · {{ $set->chapter->book->edition }} · bab {{ $set->chapter->number }} · hlm. {{ $set->start_page }}–{{ $set->end_page }}</p>
<p class="notice">Paket mempertahankan halaman asli; satu halaman dapat memuat beberapa soal, contoh, atau awal materi berikutnya. Gunakan instruksi soal yang dipilih. Gambar ini hanya referensi guru dan tidak otomatis dikirim ke siswa/AI. Untuk soal visual, tuliskan deskripsi lengkap yang setara atau gunakan lembar asli saat tatap muka; jangan memakai teks ekstraksi tanpa memeriksa gambar.</p>
@foreach($pages as $p)<section class="card"><h2>Halaman buku {{ $p->number }} · PDF {{ $p->pdf_page }}</h2><a href="{{ route('bank.page',$p) }}" target="_blank" rel="noopener">Buka gambar ukuran penuh</a><img style="display:block;width:100%;max-width:1000px;height:auto" loading="lazy" src="{{ route('bank.page',$p) }}" alt="Halaman sumber buku {{ $p->number }}"><details><summary>Teks ekstraksi untuk pencarian — periksa gambar asli</summary><pre class="answer-text">{{ $p->source_text }}</pre></details></section>@endforeach
<section class="card"><h2>Salin soal terpilih ke asesmen Anda</h2><p>Isi satu soal atau kumpulan subsoal dengan lengkap. Kunci dan pedoman berikut adalah hasil tinjauan Anda, bukan kunci resmi otomatis dari buku. Untuk jawaban terbuka, jelaskan alternatif sah. Jika kelas sudah dipilih, tingkat kelas harus sesuai buku.</p>
<form method="POST" action="{{ route('bank.copy',$set) }}">@csrf
<input type="hidden" name="creation_key" value="{{ old('creation_key',(string)\Illuminate\Support\Str::uuid()) }}">
<label>Asesmen<select name="assessment_id" required><option value="">Pilih asesmen milik Anda</option>@foreach($assessments as $a)<option value="{{ $a->id }}" @selected(old('assessment_id')==$a->id)>{{ $a->title }} · {{ $a->classroom ? 'kelas '.$a->classroom->grade : 'draf tanpa kelas' }}</option>@endforeach</select></label>
<label>Nomor soal dalam asesmen<input type="number" name="number" min="1" max="9999" required value="{{ old('number') }}"></label>
<label>Teks soal lengkap<textarea name="prompt" rows="5" maxlength="19000" required>{{ old('prompt') }}</textarea></label>
<label>Kunci / alternatif jawaban sah<textarea name="answer_key" rows="3" maxlength="20000" required>{{ old('answer_key') }}</textarea></label>
<label>Pedoman penilaian<textarea name="scoring_guidelines" rows="3" maxlength="20000" required>{{ old('scoring_guidelines') }}</textarea></label>
<label>Skor maksimum<input type="number" name="max_score" min="0.01" max="10000" step="0.01" required value="{{ old('max_score') }}"></label>
<label><input type="checkbox" name="reviewed" value="1" required> Saya memeriksa halaman sumber dan memastikan soal lengkap dapat dikerjakan dari teks yang disalin atau lembar asli yang saya sediakan; kunci dan pedoman sudah saya tinjau.</label><button>Salin soal dan sumbernya</button></form>
<p><a href="{{ route('assessments.index') }}">Buat asesmen dahulu bila daftar kosong</a></p></section>
@endsection
