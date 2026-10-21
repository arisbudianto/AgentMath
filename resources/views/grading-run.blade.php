@extends('layout')
@section('content')
<p><a href="{{ route('audit.index',[$assessment,'run'=>$run->id]) }}">Lihat token, biaya dan audit proses ini</a></p>
<a href="{{ route('grading.index',$assessment) }}">← Semua proses</a><p class="eyebrow">{{ $assessment->title }} · {{ $run->answer->participant->code }}</p><h1>Proses #{{ $run->id }}</h1><p class="notice">{{ $run->label() }} · tahap {{ $run->stage }}. Nilai akhir ditetapkan melalui pemeriksaan guru.</p>
@if(!$current)<p class="error">Versi jawaban, transkripsi, soal atau rubrik terkini sudah berbeda. Proses ini hanya menjadi riwayat; buat proses baru dari dasar terkini.</p>@endif
<section class="card"><h2>Status dan tindakan</h2><p>Revisi jawaban {{ $run->revision->version }} · rubrik versi {{ $run->rubric->version }}@if($run->answer_transcription_id) · transkripsi #{{ $run->answer_transcription_id }}@endif</p><p>Worker terakhir selesai: {{ $heartbeat ? \Illuminate\Support\Carbon::parse($heartbeat)->timezone(config('app.display_timezone'))->format('d-m-Y H:i:s T') : 'Belum tercatat' }}. Muat ulang untuk status terbaru.</p>
@if($run->status==='awaiting_agent')<p>Persiapan berhasil. Proses ditahan pada kontrak agent saat dibuat. Periksa versi pipeline di bawah; proses lama perlu dibatalkan dan dibuat ulang. Proses ini tidak menghasilkan nilai nol.</p>@endif
@if($run->status==='failed')<p>Periksa rekaman percobaan. Melanjutkan proses hanya mengulang tahap yang belum berhasil, maksimal tiga percobaan per tahap.</p>@endif
@if(($run->config_snapshot['pipeline_version'] ?? '')!=='m05_v1')<p class="notice">Proses ini memakai kontrak lama sebelum M05. Batalkan proses lama dan buat proses baru; konfigurasi serta riwayat lama tetap dikunci.</p>@endif
@if($run->attempts->contains('error_code','configuration_changed'))<p class="error">Konfigurasi AI belum siap atau berbeda dari snapshot. Minta admin memeriksa konfigurasi, lalu batalkan proses lama dan buat proses baru. Retry tidak mengganti konfigurasi yang dikunci.</p>@endif
@if($current && $canResume && in_array($run->status,['queued','running','failed','awaiting_agent']))<form method="POST" action="{{ route('grading.control',[$assessment,$run]) }}">@csrf<input type="hidden" name="action" value="resume"><button>Lanjutkan / pulihkan antrean</button></form>@endif
@if(in_array($run->status,['queued','running','failed','awaiting_agent']))<form method="POST" action="{{ route('grading.control',[$assessment,$run]) }}">@csrf<input type="hidden" name="action" value="cancel"><button class="secondary">Batalkan proses</button></form>@endif
<a href="{{ route('answers.show',[$assessment,$run->answer_id]) }}">Buka jawaban dan buat proses baru bila diperlukan</a></section>
@if($proposal)
<a class="button" href="{{ route('reviews.show',[$assessment,$run]) }}">Periksa dan putuskan nilai sebagai guru</a>
<section class="card"><h2>Usulan skor AI — perlu tinjauan guru</h2>
@if(!$current)<p class="error">Usulan historis ini tidak berlaku untuk dasar jawaban terkini.</p>@endif
<p><strong>{{ $proposal['total_score'] }} / {{ $run->input_snapshot['source']['max_score'] }}</strong> · pilihan {{ $proposal['selected_marker'] }} · pemeriksaan {{ $proposal['effective_label'] }}</p>
<p>High/Low adalah pemeriksaan AI, bukan probabilitas kebenaran. Usulan ini terpisah dari keputusan guru. Buka pemeriksaan guru untuk koreksi dan persetujuan; publikasi ke siswa belum tersedia.</p>
<p>{{ $proposal['confidence']['reason'] }}</p>
@foreach($proposal['confidence']['concerns'] as $concern)<p>{{ $concern['criterion_id'] }}: {{ $concern['reason'] }}</p>@endforeach
@foreach($proposal['review_flags'] as $flag)<p class="notice">Perlu pemeriksaan: {{ ['marker_disagreement'=>'Skor Marker berbeda per kriteria; periksa keputusan arbitrase.','missing_evidence'=>'Ada bukti yang tidak ditemukan; label efektif ditahan Low.','ai_low_confidence'=>'AI menandai keraguan.'][$flag] ?? $flag }}</p>@endforeach
<div style="overflow-x:auto"><table><thead><tr><th>Kriteria</th><th>Marker A</th><th>Marker B</th><th>Usulan</th></tr></thead><tbody>
@php($markerA=$run->attempts->where('stage','marker_a')->where('status','succeeded')->last()?->output['data']['criteria'] ?? [])
@php($markerB=$run->attempts->where('stage','marker_b')->where('status','succeeded')->last()?->output['data']['criteria'] ?? [])
@foreach($proposal['criteria'] as $i=>$row)<tr><td>{{ $row['criterion_id'] }}</td><td>{{ $markerA[$i]['score'] ?? '—' }}</td><td>{{ $markerB[$i]['score'] ?? '—' }}</td><td>{{ $row['score'] }}</td></tr>@endforeach
</tbody></table></div>
@foreach($proposal['criteria'] as $row)<article><h3>{{ $row['criterion_id'] }} · {{ $row['score'] }}</h3>
<p>{{ $row['reason'] }} · aturan {{ implode(', ',$row['rule_ids']) }}</p>
@foreach($row['evidence'] as $quote)<blockquote><pre class="answer-text">{{ $quote }}</pre></blockquote>@endforeach
@if($row['missing_evidence'])<p>Bukti tidak ditemukan dalam jawaban.</p>@endif</article>@endforeach
@php($arbitration=$run->attempts->where('stage','arbitration')->where('status','succeeded')->last()?->output['data'] ?? [])
@if($arbitration['required'] ?? false)<h3>Keputusan arbitrase</h3><p>Satu hasil Marker dipilih utuh; skor tidak dirata-rata.</p>
@foreach($arbitration['decisions'] as $decision)<p>{{ $decision['criterion_id'] }}: {{ $decision['reason'] }} · {{ implode(', ',$decision['rule_ids']) }}</p>@endforeach
@else<p>Skor per kriteria sama; tidak ada panggilan Arbitrator.</p>@endif
</section>
@endif
<section class="card"><h2>Dasar proses yang dikunci</h2><details><summary>Teks jawaban yang diproses</summary><pre class="answer-text">{{ $run->input_snapshot['answer_text'] }}</pre></details><details><summary>Soal dan rubrik saat proses dibuat</summary><pre class="answer-text">{{ json_encode(['source'=>$run->input_snapshot['source'],'rubric'=>$run->input_snapshot['rubric']],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre></details><p>Pipeline: {{ $run->config_snapshot['pipeline_version'] }} · provider {{ $run->config_snapshot['provider'] }} · model {{ $run->config_snapshot['model'] ?: 'belum dikonfigurasi' }} · AI {{ $run->config_snapshot['enabled']?'aktif saat proses dibuat':'nonaktif saat proses dibuat' }}</p></section>
<section class="card"><h2>Rencana tahap dan percobaan</h2><p>Pemeriksaan awal → Marker A → Marker B (konteks terpisah) → Arbitrase bila diperlukan → Confidence. A1/A2 memakai rubrik yang sudah disahkan.</p>@forelse($run->attempts as $attempt)<details class="history"><summary>{{ $attempt->stage }} · percobaan {{ $attempt->number }} · {{ $attempt->status }}</summary><p>{{ $attempt->started_at->timezone(config('app.display_timezone'))->format('d-m-Y H:i:s T') }}@if($attempt->finished_at) → {{ $attempt->finished_at->timezone(config('app.display_timezone'))->format('H:i:s T') }}@endif</p>@if($attempt->error_code)<p>Kode: {{ $attempt->error_code }}. Detail rahasia layanan tidak ditampilkan.</p>@endif @if($attempt->output)<pre class="answer-text">{{ json_encode($attempt->output,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE) }}</pre>@endif</details>@empty<p>Worker belum menjalankan tahap pertama.</p>@endforelse</section>
<section class="card"><h2>Peristiwa proses</h2>@foreach($events as $event)<p>{{ $event->created_at->timezone(config('app.display_timezone'))->format('d-m-Y H:i:s T') }} · {{ $event->stage }} · {{ $event->code }} · {{ $event->actor_id ? 'pengajar' : 'worker' }}</p>@endforeach{{ $events->links() }}</section>
@endsection
