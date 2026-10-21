@extends('layout')
@section('content')
<a href="{{ route('evaluations.show',[$assessment,$dataset]) }}">← Dataset</a><h1>Mutu acuan manusia dan transkripsi</h1>
<p>{{ $dataset->name }} · {{ $dataset->data_kind }} · kebijakan {{ $dataset->protocol['reference_policy'] }}</p>
<p class="notice">Label A/B dicatat pemilik dari dua penilai berkode yang dinyatakan independen; aplikasi tidak membuktikan blinding/identitas di luar sistem. Dataset sintetis hanya menguji fungsi. Tidak ada penilaian otomatis atau publikasi siswa pada halaman ini.</p>
@if($report)
<section class="card"><h2>Kesepakatan sebelum adjudikasi</h2><p>{{ $report['human_pairs'] }} / {{ $report['total'] }} kasus memiliki pasangan valid · {{ $report['adjudicated'] }} acuan diadjudikasi · {{ $report['invalid'] }} kasus ditahan.</p><p>Total tepat {{ $report['total_exact_percent'] ?? '—' }}% · MAE {{ $report['mae'] ?? '—' }} poin · MAE normal {{ $report['normalized_mae_percent'] ?? '—' }}% · kriteria tepat {{ $report['criterion_exact_percent'] ?? '—' }}%.</p><p>Kesamaan total tidak berarti kesamaan pembagian skor. Ini kesepakatan deskriptif antarmanusia, bukan akurasi AI, uji signifikansi atau jaminan kebenaran acuan.</p></section>
<section class="card"><h2>Transkripsi terpisah dari penilaian</h2><p>{{ $report['ocr_labeled'] }} / {{ $report['photo_cases'] }} kasus foto memiliki acuan transkripsi manusia valid.</p>
@foreach(['ai'=>'Draf AI sebelum konfirmasi','confirmed'=>'Teks sesudah konfirmasi guru'] as $m=>$label)
<h3>{{ $label }}</h3><p>CER gabungan {{ $report[$m]['cer_percent'] ?? '—' }}% · {{ $report[$m]['computed'] }} kasus dihitung · {{ $report[$m]['missing'] }} sumber tidak tersedia · {{ $report[$m]['limited'] }} melampaui batas perhitungan.</p>
@endforeach
<p>CER = jarak edit karakter / karakter acuan × 100. Perhitungan memakai Unicode code point; hanya CRLF/CR menjadi LF, spasi, huruf dan notasi matematika tidak disetarakan. Nilai dapat melebihi 100%. Maksimal 2000 karakter tiap teks dan produk panjang 250.000 per pasangan; kasus terbatas tidak dipotong/dinilai nol. CER gabungan menjumlah edit dan karakter acuan hanya dari kasus yang dihitung; cakupan draf AI dan konfirmasi dapat berbeda.</p><p>CER mengukur salinan teks, bukan kesetaraan matematika atau ketepatan skor. Acuan tidak terbaca/missing tidak menjadi skor nol.</p></section>
<section class="card"><h2>Rincian kasus beku</h2><div style="overflow-x:auto"><table class="evaluation-table"><thead><tr><th>Kasus</th><th>Status</th><th>Total A/B</th><th>Kriteria berbeda</th><th>CER AI / konfirmasi (%)</th></tr></thead><tbody>
@foreach($report['rows'] as $r)
<tr><td>#{{ $r['case_id'] }}</td><td>{{ $r['status'] }}</td><td>{{ $r['human_a_total'] ?? '—' }} / {{ $r['human_b_total'] ?? '—' }}</td><td>{{ $r['criterion_disagreements'] ?? '—' }}</td><td>{{ $r['ai_cer_percent'] ?? '—' }} / {{ $r['confirmed_cer_percent'] ?? '—' }}</td></tr>
@endforeach
</tbody></table></div><p><a class="button" href="{{ route('research.export',[$assessment,$dataset]) }}">Unduh mutu acuan CSV</a></p></section>
@else
<p>Laporan dihitung setelah pembekuan agar versi label dan sumber tetap. Kumpulkan label A/B, adjudikasi, serta acuan foto opsional sebelum membekukan.</p>
@endif
<section class="card"><h2>Halaman pencatatan per kasus</h2>
@foreach($cases as $case)
<p>Kasus #{{ $case->id }} · <a href="{{ route('research.form',[$assessment,$dataset,$case,'human_a']) }}">Label manusia A</a> · <a href="{{ route('research.form',[$assessment,$dataset,$case,'human_b']) }}">Label manusia B</a> · <a href="{{ route('research.form',[$assessment,$dataset,$case,'adjudication']) }}">Adjudikasi</a>
@if($case->answer_transcription_id)
 · <a href="{{ route('research.form',[$assessment,$dataset,$case,'ocr']) }}">Acuan transkripsi foto</a>
@endif
</p>
@endforeach
</section>
@endsection
