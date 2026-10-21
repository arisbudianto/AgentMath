@extends('layout')
@section('content')
<a href="{{ route('research.index',[$assessment,$dataset]) }}">← Mutu acuan penelitian</a><h1>Anotasi {{ $mode }} · kasus #{{ $case->id }}</h1>
<p class="notice">Pemilik asesmen mencatat hasil manusia yang dikumpulkan independen di luar aplikasi. Ini deklarasi prosedur, bukan bukti identitas/keaslian dua penilai. Gunakan kode samaran yang berbeda; jangan menyalin hasil AI. Tidak ada pemberian akses kepada pengguna lain. Halaman human_a/human_b tidak menampilkan skor penilai lain atau usulan AI.</p>
@if($mode==='ocr')
<section class="card"><h2>Foto asli untuk acuan transkripsi</h2><img class="answer-photo" src="{{ route('answers.photo',[$assessment,$case->answer_id,$case->answer_revision_id]) }}" alt="Foto asli kasus penelitian"><p>Tulis acuan dari foto tanpa melihat transkripsi AI/konfirmasi sebelumnya. Bila tidak dapat dipastikan, biarkan kasus tanpa label; jangan menebak. Halaman ini tidak menampilkan transkripsi yang akan diuji.</p></section>
@else
<section class="card"><h2>Dasar penilaian manusia</h2><p>{{ $case->input_snapshot['source']['prompt'] }}</p><p>Kunci: {{ $case->input_snapshot['source']['answer_key'] }}</p>
@if($case->answer_transcription_id)
<img class="answer-photo" src="{{ route('answers.photo',[$assessment,$case->answer_id,$case->answer_revision_id]) }}" alt="Foto asli">
@endif
<pre class="answer-text">{{ $case->input_snapshot['answer_text'] }}</pre>
@foreach($case->input_snapshot['rubric']['criteria'] as $criterion)
<p>{{ $criterion['criterion_id'] }} · {{ $criterion['description'] }} · {{ $criterion['scoring'] }} · maksimum {{ $criterion['max_score'] }}</p>
@endforeach
</section>
@endif
@if($mode==='adjudication')
<section class="card"><h2>Dua label untuk adjudikasi</h2>
@foreach($pair as $slot=>$human)
<h3>{{ $slot }} · {{ $human?->rater_code ?? 'belum tersedia' }} · versi {{ $human?->version ?? 0 }}</h3>
@foreach($human?->data['criteria'] ?? [] as $score)
<p>{{ $score['criterion_id'] }}: {{ $score['score'] }}</p>
@endforeach
<p>{{ $human?->data['reason'] ?? '' }}</p>
@endforeach
<p>Putuskan acuan per kriteria berdasarkan sumber dan kedua penilaian. Aplikasi tidak merata-ratakan atau memilih skor secara otomatis. Menyimpan adjudikasi menambah versi acuan final M08.</p></section>
@endif
@if($dataset->status==='draft')
<section class="card"><h2>Simpan versi {{ ($latest?->version ?? 0)+1 }}</h2><form method="POST" action="{{ route('research.store',[$assessment,$dataset,$case,$mode]) }}">@csrf<input type="hidden" name="creation_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_version" value="{{ $latest?->version ?? 0 }}">
@if(in_array($mode,['human_a','human_b']))
<label>Kode penilai samaran<input name="rater_code" maxlength="40" pattern="[A-Za-z0-9_-]+" required value="{{ $latest?->rater_code }}"></label>
@endif
@if($mode==='ocr')
<label>Acuan transkripsi manusia<textarea name="reference_text" rows="6" maxlength="20000" required>{{ $latest?->data['text'] ?? '' }}</textarea></label>
@else
@foreach($case->input_snapshot['rubric']['criteria'] as $i=>$criterion)
@php($previous=collect($latest?->data['criteria'] ?? [])->firstWhere('criterion_id',$criterion['criterion_id']))
<input type="hidden" name="criteria[{{ $i }}][criterion_id]" value="{{ $criterion['criterion_id'] }}"><label>Skor {{ $criterion['criterion_id'] }}<input type="number" name="criteria[{{ $i }}][score]" min="0" max="{{ $criterion['max_score'] }}" step="0.01" required value="{{ $previous['score'] ?? '' }}"></label>
@endforeach
@endif
@if($mode==='adjudication')
<input type="hidden" name="a_id" value="{{ $pair['a']?->id }}"><input type="hidden" name="b_id" value="{{ $pair['b']?->id }}"><input type="hidden" name="reference_version" value="{{ $reference?->version ?? 0 }}">
@endif
<label>Alasan/prosedur acuan manusia<textarea name="reason" rows="3" required maxlength="2000">{{ $latest?->data['reason'] ?? '' }}</textarea></label><label class="check"><input type="checkbox" name="independent" value="1" required>Saya menyatakan acuan/dua penilaian dikumpulkan manusia secara independen tanpa menyalin AI; adjudikasi memakai kedua label manusia dan sumber.</label><button>Simpan anotasi manusia</button></form></section>
@else
<p class="notice">Dataset dibekukan; anotasi tidak dapat diubah. Versi tersimpan {{ $latest?->version ?? 0 }}. Lihat laporan untuk hasil perbandingan.</p>
@endif
@endsection
