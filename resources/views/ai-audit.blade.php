@extends('layout')
@section('content')
<a href="{{ route('assessments.show',$assessment) }}">← Kembali ke asesmen</a>
<p class="eyebrow">{{ $assessment->title }}</p><h1>Operasional dan audit AI</h1>
<p class="notice">Audit dimulai sejak M09 dipasang. Proses lama tetap tersedia pada riwayat rubrik, transkripsi, penilaian dan eksperimen; metadata yang hilang tidak direkonstruksi. Angka ini bukan tagihan penyedia.</p>
<section class="card"><h2>Filter panggilan</h2>
<form method="GET" action="{{ route('audit.index',$assessment) }}">
<label>Dari tanggal (UTC)<input type="date" name="from" value="{{ $filters['from'] }}" required></label>
<label>Sampai tanggal (UTC)<input type="date" name="to" value="{{ $filters['to'] }}" required></label>
<label>Jenis proses<select name="kind"><option value="">Semua jenis</option>
@foreach(['rubric'=>'Rubrik A1/A2','transcription'=>'Transkripsi foto','grading'=>'Penilaian M05','baseline'=>'Baseline M08'] as $value=>$label)
<option value="{{ $value }}" @selected($filters['kind']===$value)>{{ $label }}</option>
@endforeach
</select></label>
<label>Validasi keluaran tahap<select name="outcome"><option value="">Semua status</option>
@foreach(['pending'=>'Belum diketahui','validated'=>'Tervalidasi','rejected'=>'Gagal / ditolak'] as $value=>$label)
<option value="{{ $value }}" @selected($filters['outcome']===$value)>{{ $label }}</option>
@endforeach
</select></label>
<label>ID proses M05 (opsional)<input type="number" min="1" name="run" value="{{ $filters['run'] }}"></label>
<button>Terapkan filter</button></form><p>Maksimal 93 hari per tampilan; ringkasan mencakup seluruh hasil filter, halaman berisi 25 panggilan. Filter proses M05 mencakup semua percobaan, termasuk retry gagal yang tercatat.</p></section>
<section class="card"><h2>Ringkasan seluruh hasil filter</h2>
<p>{{ $summary->calls }} panggilan · {{ $summary->rejected ?? 0 }} gagal/ditolak · {{ $summary->pending ?? 0 }} belum diketahui · {{ $summary->usage_known ?? 0 }} memiliki token input/output lengkap.</p>
<p>Jumlah token yang diketahui: input {{ $summary->input_sum }}, output {{ $summary->output_sum }}. Jumlah ini dapat parsial; metadata kosong tidak berarti nol pemakaian.</p>
@forelse($costs as $cost)
<p>Estimasi tercatat {{ $cost->currency }} {{ number_format((float)$cost->amount,12,'.','') }} dari {{ $cost->known }} panggilan bertarif dan bermetadata memadai.</p>
@empty
<p>Belum ada estimasi biaya yang dapat dihitung pada hasil filter ini.</p>
@endforelse
<p>Tarif disalin dari konfigurasi operator saat permintaan dimulai; perubahan tarif tidak menghitung ulang riwayat. Mata uang berbeda tidak dijumlahkan. Biaya panggilan tanpa usage/tarif, kredit, pajak, kurs, biaya lain dan penyesuaian provider tidak tercakup.</p>
</section>
<section class="card"><h2>Riwayat panggilan</h2>
<p>Tervalidasi berarti keluaran tahap lolos pemeriksaan pada saat diproses, bukan persetujuan nilai guru. Hasil dapat dibuang karena pembatalan/perubahan sumber; buka proses asal untuk status akhirnya. Pending yang lama menunjukkan penyelesaian tidak tercatat, bukan keberhasilan atau pemakaian nol. Tidak ada panggilan AI untuk membuka laporan ini.</p>
<div style="overflow-x:auto"><table class="evaluation-table"><thead><tr><th>Panggilan / asal</th><th>Model / prompt</th><th>Status</th><th>Durasi / token</th><th>Estimasi</th></tr></thead><tbody>
@forelse($calls as $call)
<tr><td>#{{ $call->id }} · {{ $call->kind }}<br>{{ $call->started_at->format('Y-m-d H:i:s') }} UTC<br>{{ $call->stage }}<br>
@if($links[$call->id])
<a href="{{ $links[$call->id]['url'] }}">{{ $links[$call->id]['label'] }}</a>
@else
Sumber #{{ $call->source_id }}
@endif
<details><summary>ID korelasi</summary><code>{{ $call->correlation_id }}</code></details></td>
<td>{{ $call->provider }} / {{ $call->model }}<br>{{ $call->prompt_version }}</td>
<td>{{ $call->state }}<br>{{ $call->outcome }}<br>HTTP {{ $call->http_status ?? '—' }}</td>
<td>{{ $call->duration_ms ?? '—' }} ms<br>Input {{ $call->input_tokens ?? '—' }}<br>Output {{ $call->output_tokens ?? '—' }}<br>Cache {{ $call->cached_tokens ?? '—' }}</td>
<td>{{ $call->estimated_cost===null?'Belum diketahui':$call->currency.' '.$call->estimated_cost }}<br>Tarif {{ $call->tariff['version'] ?? 'belum tersedia / tidak cocok' }}</td></tr>
@empty
<tr><td colspan="5">Belum ada panggilan tercatat pada rentang/filter ini.</td></tr>
@endforelse
</tbody></table></div>{{ $calls->links() }}
<p><a class="button" href="{{ route('audit.export',array_merge([$assessment],$filters)) }}">Unduh audit CSV</a></p><p>CSV hanya metadata; tidak memuat nama/kode peserta, teks jawaban, foto, prompt lengkap atau kunci API. Maksimal 10.000 baris per ekspor.</p>
</section>
@endsection
