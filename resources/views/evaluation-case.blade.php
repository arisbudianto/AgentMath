@extends('layout')
@section('content')
<a href="{{ route('evaluations.show',[$assessment,$dataset]) }}">← Dataset</a><p class="eyebrow">{{ $dataset->name }} · {{ $case->split }}</p><h1>Acuan manusia · kasus #{{ $case->id }}</h1>
<p class="notice">Halaman anotasi ini tidak menampilkan hasil AI. Jangan menyalin nilai M06 atau menyesuaikan acuan setelah melihat AI. Pernyataan independen adalah deklarasi penilai, bukan jaminan bahwa aplikasi dapat membuktikan seluruh proses penilaian buta di luar halaman ini.</p>
<section class="card"><h2>Snapshot yang dinilai</h2><p>{{ $case->input_snapshot['source']['prompt'] }}</p><p>Kunci: {{ $case->input_snapshot['source']['answer_key'] }}</p><p>{{ $case->input_snapshot['rubric']['grading_policy'] }}</p>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:16px">
@if($case->answer_transcription_id)<div><img class="answer-photo" src="{{ route('answers.photo',[$assessment,$case->answer_id,$case->answer_revision_id]) }}" alt="Foto asli snapshot kasus penelitian"></div>
@endif
<div><pre class="answer-text">{{ $case->input_snapshot['answer_text'] }}</pre></div></div>
@foreach($case->input_snapshot['rubric']['criteria'] as $criterion)<article><h3>{{ $criterion['criterion_id'] }} · {{ $criterion['description'] }} · maksimum {{ $criterion['max_score'] }}</h3><p>Bukti yang diperlukan: {{ $criterion['evidence'] }}</p><p>{{ $criterion['scoring'] }}</p></article>
@endforeach
@foreach($case->input_snapshot['rubric']['rules'] as $rule)<p>{{ $rule['rule_id'] }}: {{ $rule['quote'] }}</p>
@endforeach</section>
@if($dataset->status==='draft')
<section class="card"><h2>Masukkan acuan independen</h2><form method="POST" action="{{ route('evaluations.label',[$assessment,$dataset,$case]) }}">@csrf<input type="hidden" name="creation_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_version" value="{{ $label?->version ?? 0 }}">
@foreach($case->input_snapshot['rubric']['criteria'] as $i=>$criterion) @php($previous=collect($label?->criteria ?? [])->firstWhere('criterion_id',$criterion['criterion_id']))
<input type="hidden" name="criteria[{{ $i }}][criterion_id]" value="{{ $criterion['criterion_id'] }}"><label>Acuan {{ $criterion['criterion_id'] }}<input type="number" name="criteria[{{ $i }}][score]" min="0" max="{{ $criterion['max_score'] }}" step="0.01" required value="{{ $previous['score'] ?? '' }}"></label>
@endforeach
<label>Alasan acuan manusia<textarea name="reason" maxlength="2000" required rows="3">{{ $label?->reason ?? '' }}</textarea></label>
<label>Waktu pemberian label acuan (detik, opsional; bukan waktu tinjauan hasil AI)<input type="number" name="label_seconds" min="1" max="86400" value="{{ $label?->label_seconds }}"></label>
<label class="check"><input type="checkbox" name="independent" value="1" required>Acuan ini berasal dari penilaian manusia independen tanpa menyalin atau melihat hasil AI untuk kasus ini; label bukan persetujuan AI M06 yang didaur ulang.</label><button>Simpan versi acuan</button></form></section>
@else<p class="notice">Dataset beku. Acuan dan snapshot tidak dapat disunting.</p>
@endif
<section class="card"><h2>Riwayat acuan</h2>@forelse($history as $reference)<details><summary>Versi {{ $reference->version }} · total {{ $reference->total_score }}</summary><p>{{ $reference->reason }}</p>@foreach($reference->criteria as $row)<p>{{ $row['criterion_id'] }}: {{ $row['score'] }}</p>
@endforeach</details>
@empty<p>Belum ada acuan. Tidak ada skor nol otomatis.</p>
@endforelse{{ $history->links() }}</section>
@endsection

