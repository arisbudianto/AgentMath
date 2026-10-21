@extends('layout')
@section('content')
<a href="{{ route('evaluations.experiment',[$assessment,$dataset,$experiment]) }}">← Laporan eksperimen</a><h1>Analisis kasus #{{ $sample->eval_case_id }}</h1>
<p>{{ $sample->status }} · acuan manusia versi {{ $sample->reference->version }} · total {{ $sample->reference->total_score }}</p>
<section class="card"><h2>Snapshot penelitian</h2><p>{{ $sample->case->input_snapshot['source']['prompt'] }}</p><pre class="answer-text">{{ $sample->case->input_snapshot['answer_text'] }}</pre>
<details><summary>Rubrik dan acuan</summary><pre class="answer-text">{{ json_encode(['rubric'=>$sample->case->input_snapshot['rubric'],'reference'=>$sample->reference->criteria],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre><p>{{ $sample->reference->reason }}</p></details>
<details><summary>Hasil multi-agent tersimpan</summary><pre class="answer-text">{{ json_encode($sample->multi_result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details>
<details><summary>Hasil baseline baru</summary><pre class="answer-text">{{ json_encode($baseline,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details></section>
@if(in_array($sample->status,['failed','queued']) && !$experiment->cancelled_at)<section class="card"><h2>Pemulihan baseline</h2><p>Untuk kegagalan atau antrean tertahan minimal dua menit, maksimal tiga percobaan. Retry dapat menambah pemakaian API; input, acuan, hasil multi dan konfigurasi tetap dikunci. Job antrean lama akan diabaikan.</p><form method="POST" action="{{ route('evaluations.control',[$assessment,$dataset,$experiment]) }}">@csrf<input type="hidden" name="action" value="retry"><input type="hidden" name="sample" value="{{ $sample->id }}"><button>Ulangi baseline gagal atau tertahan</button></form></section>
@endif
<section class="card"><h2>Riwayat percobaan baseline</h2>@foreach($sample->attempts as $attempt)<p>Percobaan {{ $attempt->number }} · {{ $attempt->status }} · {{ $attempt->error_code }}</p>
@endforeach</section>
@if($sample->status==='succeeded')
@foreach(['multi'=>'Multi-agent','baseline'=>'Satu Marker baru'] as $method=>$name) @php($latest=$observations->where('method',$method)->sortByDesc('version')->first())
<section class="card"><h2>Catatan tinjauan · {{ $name }}</h2><p>Catatan ini tidak mengubah acuan, hasil AI atau nilai operasional. Waktu diisi dari pengukuran Anda, jangan mengarang waktu.</p>
<form method="POST" action="{{ route('evaluations.observe',[$assessment,$dataset,$experiment,$sample]) }}">@csrf<input type="hidden" name="creation_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_version" value="{{ $latest?->version ?? 0 }}"><input type="hidden" name="method" value="{{ $method }}">
<label>Waktu tinjauan (detik, opsional)<input type="number" name="review_seconds" min="1" max="86400" value="{{ $latest?->review_seconds }}"></label>
<label>Kategori analisis<select name="category" required>@foreach(['none'=>'Tidak ada masalah','arithmetic'=>'Aritmetika','procedure'=>'Langkah/prosedur','transcription'=>'Transkripsi','rubric'=>'Rubrik','ambiguous'=>'Ambigu','other'=>'Lainnya'] as $key=>$label)<option value="{{ $key }}" @selected($latest?->category===$key)>{{ $label }}</option>
@endforeach</select></label>
<label>Catatan berbasis bukti<textarea name="note" maxlength="2000" required rows="3">{{ $latest?->note ?? '' }}</textarea></label><button>Simpan versi catatan</button></form></section>
@endforeach
@endif
<section class="card"><h2>Riwayat catatan analisis</h2>@forelse($observations as $observation)<p>{{ $observation->method }} · versi {{ $observation->version }} · {{ $observation->category }} · {{ $observation->review_seconds ?? 'waktu belum diisi' }} · {{ $observation->note }}</p>
@empty<p>Belum ada catatan tinjauan. Tidak ada waktu tinjauan otomatis.</p>
@endforelse</section>
@endsection

