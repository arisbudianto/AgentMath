@extends('layout')
@section('content')
<a href="{{ route('grading.show',[$assessment,$run]) }}">← Hasil AI dan seluruh bukti</a>
<p class="eyebrow">{{ $assessment->title }} · {{ $run->answer->participant->code }} · proses #{{ $run->id }}</p>
<h1>Pemeriksaan dan keputusan guru</h1>
<p class="notice">Usulan AI {{ $proposal['total_score'] }} / {{ $run->input_snapshot['source']['max_score'] }} · pemeriksaan {{ $proposal['effective_label'] }}. High/Low bukan probabilitas kebenaran. Guru memeriksa bukti dan bertanggung jawab atas keputusan nilai.</p>
@if(!$current)<p class="error">Dasar jawaban, transkripsi, soal atau rubrik sudah berubah. Halaman ini hanya riwayat; penyimpanan dan persetujuan ditahan.</p>@endif
@if($approved)<section class="card"><h2>Keputusan terakhir yang disahkan</h2>
<p><strong>{{ $approved->total_score }}</strong> · versi {{ $approved->version }} · proses #{{ $approved->grading_run_id }} · {{ $approvedCurrent?'berlaku untuk dasar terkini':'historis, dasar sudah berubah' }}</p>
<p>{{ $approved->reason }}</p><p>Draf atau penilaian ulang tidak mengganti keputusan ini sampai guru menyetujui keputusan baru. Nilai belum dipublikasikan ke portal siswa atau ekspor; publikasi/rekap merupakan M07.</p></section>@endif
<section class="card"><h2>Periksa jawaban dan dasar penilaian</h2>
<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:20px">
@if($run->revision->content_type==='photo')<div><h3>Foto asli privat · revisi {{ $run->revision->version }}</h3><img class="answer-photo" src="{{ route('answers.photo',[$assessment,$run->answer_id,$run->answer_revision_id]) }}" alt="Foto jawaban yang dinilai"><a href="{{ route('transcriptions.show',[$assessment,$run->answer_id,$run->answer_revision_id]) }}">Buka riwayat transkripsi</a></div>@endif
<div><h3>{{ $run->answer_transcription_id?'Transkripsi yang dikonfirmasi #'.$run->answer_transcription_id:'Teks jawaban yang dinilai' }}</h3><pre class="answer-text">{{ $run->input_snapshot['answer_text'] }}</pre></div></div>
<h3>Soal</h3><p>{{ $run->input_snapshot['source']['prompt'] }}</p><p>Kunci: {{ $run->input_snapshot['source']['answer_key'] }}</p><p>{{ $run->input_snapshot['rubric']['grading_policy'] }}</p>
<p>{{ $proposal['confidence']['reason'] }}</p>
@foreach($proposal['confidence']['concerns'] as $concern)<p class="notice">{{ $concern['criterion_id'] }}: {{ $concern['reason'] }}</p>@endforeach
@foreach($proposal['review_flags'] as $flag)<p class="notice">Penanda: {{ ['marker_disagreement'=>'Marker berbeda; periksa keputusan Arbitrator.','missing_evidence'=>'Bukti hilang pada salah satu Marker.','ai_low_confidence'=>'AI menandai keraguan.'][$flag] ?? $flag }}</p>@endforeach
@php($markerA=$run->attempts->where('stage','marker_a')->where('status','succeeded')->last()?->output['data']['criteria'] ?? [])
@php($markerB=$run->attempts->where('stage','marker_b')->where('status','succeeded')->last()?->output['data']['criteria'] ?? [])
@foreach($run->input_snapshot['rubric']['criteria'] as $i=>$criterion)
<article><h3>{{ $criterion['criterion_id'] }} · {{ $criterion['description'] }} · maksimum {{ $criterion['max_score'] }}</h3>
<p>Bukti yang diperlukan: {{ $criterion['evidence'] }}</p><p>Aturan skor: {{ $criterion['scoring'] }}</p>
@foreach(['Marker A'=>$markerA[$i] ?? null,'Marker B'=>$markerB[$i] ?? null] as $name=>$marker)
@if($marker)<details><summary>{{ $name }} · {{ $marker['score'] }}</summary><p>{{ $marker['reason'] }}</p>@foreach($marker['evidence'] as $quote)<blockquote><pre class="answer-text">{{ $quote }}</pre></blockquote>@endforeach @if($marker['missing_evidence'])<p>Bukti tidak ditemukan.</p>@endif</details>@endif
@endforeach
@foreach($run->input_snapshot['rubric']['rules'] as $rule) @if(in_array($rule['rule_id'],$criterion['rule_ids'],true))<p>{{ $rule['rule_id'] }}: {{ $rule['quote'] }}</p>@endif @endforeach
</article>@endforeach
@php($arbitration=$run->attempts->where('stage','arbitration')->where('status','succeeded')->last()?->output['data'] ?? [])
@if($arbitration['required'] ?? false)<h3>Arbitrase · memilih {{ $arbitration['selected_marker'] }}</h3>@foreach($arbitration['decisions'] as $decision)<p>{{ $decision['criterion_id'] }}: {{ $decision['reason'] }} · {{ implode(', ',$decision['rule_ids']) }}</p>@endforeach @endif
</section>
@if($current)
<section class="card"><h2>1. Simpan draf pemeriksaan</h2><p>Periksa semua kriteria. Setiap skor yang berbeda dari usulan AI memerlukan alasan per kriteria. Nilai akhir belum berubah saat draf disimpan.</p>
<form method="POST" action="{{ route('reviews.store',[$assessment,$run]) }}">@csrf
<input type="hidden" name="creation_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_version" value="{{ $latest?->version ?? 0 }}"><input type="hidden" name="action" value="draft">
@foreach($run->input_snapshot['rubric']['criteria'] as $i=>$criterion)
@php($savedRow=collect($saved?->criteria ?? [])->firstWhere('criterion_id',$criterion['criterion_id']))
<input type="hidden" name="criteria[{{ $i }}][criterion_id]" value="{{ $criterion['criterion_id'] }}">
<label>{{ $criterion['criterion_id'] }} · skor guru (0–{{ $criterion['max_score'] }}, usulan {{ $proposal['criteria'][$i]['score'] }})<input type="number" name="criteria[{{ $i }}][score]" min="0" max="{{ $criterion['max_score'] }}" step="0.01" required value="{{ $savedRow['score'] ?? $proposal['criteria'][$i]['score'] }}"></label>
<label>Alasan koreksi {{ $criterion['criterion_id'] }}<textarea name="criteria[{{ $i }}][reason]" maxlength="2000" rows="2">{{ $savedRow['reason'] ?? '' }}</textarea></label>
@endforeach
<label>Alasan pemeriksaan keseluruhan (wajib)<textarea name="reason" required maxlength="2000" rows="3">{{ $saved?->reason ?? '' }}</textarea></label><button>Simpan draf pemeriksaan</button></form></section>
@if($latest && $latest->status==='draft' && $latest->grading_run_id===$run->id)
<section class="card"><h2>2. Sahkan draf tersimpan</h2><p>Draf versi {{ $latest->version }} · total {{ $latest->total_score }}</p><p>{{ $latest->reason }}</p>
@foreach($latest->criteria as $row)<p>{{ $row['criterion_id'] }}: {{ $row['score'] }} · {{ $row['reason'] ?: 'Skor sesuai usulan AI.' }}</p>@endforeach
<form method="POST" action="{{ route('reviews.store',[$assessment,$run]) }}">@csrf
<input type="hidden" name="creation_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_version" value="{{ $latest->version }}"><input type="hidden" name="action" value="approve">
<label class="check"><input type="checkbox" name="confirmation" value="1" required>Saya telah memeriksa jawaban, foto/transkripsi bila ada, semua bukti, perbedaan Marker dan skor draf ini. Saya menyetujui nilai sebagai keputusan guru.</label><button>Sahkan nilai dari draf ini</button></form></section>
@endif
<section class="card"><h2>Minta penilaian AI ulang</h2><p>Proses baru memakai dasar dan konfigurasi AI terkini serta dapat menimbulkan pemakaian API. Keputusan guru sebelumnya tetap tersimpan.</p>
<form method="POST" action="{{ route('grading.store',$assessment) }}">@csrf<input type="hidden" name="creation_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><input type="hidden" name="answer_id" value="{{ $run->answer_id }}"><label class="check"><input type="checkbox" name="confirmation" value="1" required>Saya meminta proses baru melalui penyedia AI server dari jawaban ini yang bebas identitas pribadi.</label><button class="secondary">Buat proses penilaian ulang</button></form></section>
@endif
<section class="card"><h2>Riwayat pemeriksaan jawaban</h2>
@forelse($history as $entry)<details class="history"><summary>Versi {{ $entry->version }} · {{ $entry->status==='approved'?'Disahkan guru':'Draf' }} · {{ $entry->total_score }} · proses #{{ $entry->grading_run_id }}</summary><p>{{ $entry->actor->name }} · {{ $entry->created_at->timezone(config('app.display_timezone'))->format('d-m-Y H:i:s T') }}</p><p>{{ $entry->reason }}</p>@foreach($entry->criteria as $row)<p>{{ $row['criterion_id'] }}: {{ $row['score'] }} · {{ $row['reason'] }}</p>@endforeach @if($entry->supersedes_approval_id)<p>Menggantikan keputusan #{{ $entry->supersedes_approval_id }}; keputusan lama tetap disimpan.</p>@endif <a href="{{ route('reviews.show',[$assessment,$entry->grading_run_id]) }}">Dasar proses #{{ $entry->grading_run_id }}</a></details>@empty<p>Belum ada pemeriksaan guru.</p>@endforelse{{ $history->links() }}</section>
@endsection
