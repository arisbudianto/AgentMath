@extends('layout')
@section('content')
<a href="{{ route('evaluations.show',[$assessment,$dataset]) }}">← Dataset</a><p class="eyebrow">{{ $dataset->name }} · {{ $experiment->split }}</p><h1>Laporan eksperimen #{{ $experiment->id }}</h1>
<p class="notice">{{ $report['paired'] }} / {{ $report['total'] }} kasus valid berpasangan. Metrik kedua metode hanya memakai pasangan valid yang sama. Kegagalan/missing_multi tetap dalam penyebut cakupan, tidak diganti nol. Sampel yang berhasil dapat memiliki bias seleksi; lihat seluruh status sebelum menyimpulkan.</p>
@if($dataset->data_kind==='synthetic')<p class="error">Data sintetis: laporan ini bukan bukti akurasi pada siswa nyata.</p>
@endif
@if($experiment->test_reused)<p class="error">Test digunakan ulang. Ini bukan pengujian akhir baru yang belum pernah dilihat.</p>
@endif
@if($experiment->cancelled_at)<p class="notice">Eksperimen dibatalkan; hasil yang sudah tersimpan tetap menjadi riwayat.</p>
@endif
<section class="card"><h2>Dasar perbandingan</h2><p>{{ $experiment->config_snapshot['provider'] }} · {{ $experiment->config_snapshot['model'] }} · {{ $experiment->protocol_version }} · manifest {{ $dataset->manifest_hash }}</p><p>Sasaran yang didaftarkan: {{ $dataset->protocol['target'] }}</p>
<p>Toleransi {{ number_format($dataset->protocol['tolerance_bps']/100,2) }}% untuk total/setiap kriteria. High/Low adalah label AI, bukan probabilitas terkalibrasi. Laporan deskriptif ini tidak otomatis menyatakan metode layak, unggul atau signifikan secara statistik.</p>
@foreach($report['statuses'] as $status=>$n)<span class="badge">{{ $status }}: {{ $n }}</span> @endforeach @if($report['invalid'])<p class="error">Snapshot/hasil tidak valid: {{ $report['invalid'] }}; ditahan dari metrik.</p>
@endif</section>
<section class="card"><h2>Kesesuaian terhadap acuan manusia</h2><div style="overflow-x:auto"><table class="evaluation-table"><thead><tr><th>Metode</th><th>Pasangan</th><th>Total tepat (%)</th><th>MAE skor</th><th>MAE normal (%)</th><th>Kriteria tepat (%)</th><th>MAE kriteria normal (%)</th><th>Kasus keliru</th></tr></thead><tbody>
@foreach(['multi'=>'Multi-agent','baseline'=>'Satu Marker baru'] as $method=>$name) @php($m=$report['metrics'][$method])
<tr><td>{{ $name }}</td><td>{{ $m['n'] }}</td><td>{{ $m['exact_percent'] ?? '—' }}</td><td>{{ $m['mae'] ?? '—' }}</td><td>{{ $m['normalized_mae_percent'] ?? '—' }}</td><td>{{ $m['criterion_exact_percent'] ?? '—' }}</td><td>{{ $m['criterion_normalized_mae_percent'] ?? '—' }}</td><td>{{ $m['wrong'] }}</td></tr>
@endforeach</tbody></table></div>
<p>MAE adalah rata-rata selisih absolut. Metrik normal membagi selisih dengan skor maksimum tiap kasus/kriteria agar skala soal berbeda terlihat. Kasus keliru berarti total atau setidaknya satu kriteria melewati toleransi; total sama masih dapat memiliki pembagian kriteria yang salah.</p>
@php($multi=$report['metrics']['multi'])
<p>Kesalahan berlabel High: {{ $multi['high_wrong'] }} / {{ $multi['high_n'] }} pasangan High · {{ $multi['high_error_percent'] ?? '—' }}%. Tidak ada penyebut berarti belum dapat dihitung.</p>
</section>
<section class="card"><h2>Waktu dan pemakaian yang tercatat</h2>
@foreach(['multi'=>'Multi-agent','baseline'=>'Satu Marker baru'] as $method=>$name) @php($m=$report['metrics'][$method])
<h3>{{ $name }}</h3><p>Token input/output diketahui: {{ $m['input_tokens'] }} / {{ $m['output_tokens'] }} ({{ $m['usage_n'] }} pasangan memiliki metadata). Rata-rata durasi tahap: {{ $m['mean_duration_ms'] ?? '—' }} ms (n={{ $m['duration_n'] }}). Waktu tinjauan guru dilaporkan: {{ $m['mean_review_seconds'] ?? '—' }} detik (n={{ $m['review_n'] }}).</p>
@foreach($report['categories'][$method] as $category=>$count)<span class="badge">{{ $category }}: {{ $count }}</span> @endforeach
@endforeach
<p>Durasi tidak termasuk antrean, biaya rupiah tidak dihitung. Token/durasi hanya dari hasil berhasil yang diketahui; permintaan gagal atau retry dapat menambah biaya yang tidak tercakup. A1/A2 dan OCR tidak termasuk. Waktu pemberian acuan manusia terpisah: {{ $report['reference_label_seconds_mean'] ?? '—' }} detik (n={{ $report['reference_label_time_n'] }}), bukan waktu tinjauan AI. Waktu manual belum membuktikan efisiensi tanpa pengukuran sebanding.</p></section>
<section class="card"><h2>Kasus, kegagalan dan analisis</h2><p>Muat ulang halaman untuk status worker terbaru. Retry untuk baseline gagal atau antrean tertahan minimal dua menit, maksimal tiga percobaan; tidak mengulang hasil yang berhasil. Perubahan konfigurasi memerlukan pemulihan konfigurasi yang sesuai atau eksperimen baru.</p>
<div style="overflow-x:auto"><table class="evaluation-table"><thead><tr><th>Kasus</th><th>Status</th><th>Acuan</th><th>Multi</th><th>Baseline baru</th><th>Confidence</th></tr></thead><tbody>
@foreach($report['rows'] as $row)<tr><td><a href="{{ route('evaluations.sample',[$assessment,$dataset,$experiment,$row['sample_id']]) }}">#{{ $row['case_id'] }}</a></td><td>{{ $row['status'] }} {{ $row['error_code'] }}</td><td>{{ $row['human_score'] ?? '—' }}</td><td>{{ $row['multi_score'] ?? '—' }}</td><td>{{ $row['baseline_score'] ?? '—' }}</td><td>{{ $row['multi_confidence'] ?? '—' }}</td></tr>
@endforeach
</tbody></table></div>
<p><a class="button" href="{{ route('evaluations.export',[$assessment,$dataset,$experiment]) }}">Unduh pasangan evaluasi CSV</a></p>
<form method="POST" action="{{ route('evaluations.control',[$assessment,$dataset,$experiment]) }}">@csrf<input type="hidden" name="action" value="cancel"><button class="secondary">Batalkan pekerjaan yang belum selesai</button></form>
</section>
@endsection



