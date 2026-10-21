@extends('layout')
@section('content')
<p><a class="button" href="{{ route('research.index',[$assessment,$dataset]) }}">Mutu acuan · dua penilai dan transkripsi foto</a></p>
<p>Kebijakan acuan: {{ $dataset->protocol['reference_policy'] }}</p>
<a href="{{ route('evaluations.index',$assessment) }}">← Dataset evaluasi</a><p class="eyebrow">{{ $assessment->title }} · {{ $dataset->data_kind }}</p><h1>{{ $dataset->name }}</h1>
@if($dataset->data_kind==='synthetic')<p class="notice">Dataset sintetis hanya membuktikan fungsi perhitungan/alur, bukan kualitas penilaian pada siswa nyata.</p>
@endif
<section class="card"><h2>Protokol {{ $dataset->status==='frozen'?'dibekukan':'draf' }}</h2><p>{{ $dataset->protocol['goal'] }}</p><p>Sasaran: {{ $dataset->protocol['target'] }}</p><p>Toleransi {{ number_format($dataset->protocol['tolerance_bps']/100,2) }}% · split per soal · pembanding satu panggilan Marker baru.</p>
@if($dataset->status==='frozen')<p>Manifest: <code>{{ $dataset->manifest_hash }}</code>. Snapshot dan acuan tetap; perubahan jawaban operasional tidak mengubah dataset penelitian ini.</p>
@endif</section>
@if($dataset->status==='draft')
<section class="card"><h2>Tambahkan snapshot jawaban</h2><p>Memerlukan rubrik disahkan, jawaban terkini dan transkripsi foto confirmed. Satu soal hanya boleh berada pada tuning atau test, termasuk seluruh peserta/revisi soal tersebut. Maksimal 100 kasus, 200 KB snapshot per kasus.</p>
<form method="POST" action="{{ route('evaluations.add',[$assessment,$dataset]) }}">@csrf<input type="hidden" name="creation_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
<label>ID jawaban<input type="number" name="answer_id" min="1" required></label>
<label>Pembagian data<select name="split" required><option value="">Pilih pembagian</option><option value="tuning">Tuning — penyetelan</option><option value="test">Test — pengujian akhir</option></select></label><button>Tambahkan kasus</button></form>
<details><summary>ID dari 200 jawaban terbaru</summary>@foreach($answers as $answer)<p>#{{ $answer->id }} · {{ $answer->participant->code }} · soal {{ $answer->question->number }} · <a href="{{ route('answers.show',[$assessment,$answer]) }}">Buka jawaban</a></p>
@endforeach</details>
<p>Masukkan ID dari URL detail jawaban bila tidak terlihat pada daftar. Halaman anotasi berikutnya tidak menampilkan hasil AI; gunakan acuan manusia independen yang telah dikumpulkan sebelum melihat hasil AI.</p></section>
<section class="card"><h2>Bekukan dataset</h2><p>Semua kasus wajib memiliki acuan. Label dapat direvisi sebagai versi baru sebelum pembekuan. Setelah dibekukan, kasus, label terpilih, protokol dan split tidak dapat diganti; buat dataset baru untuk perubahan.</p>
<form method="POST" action="{{ route('evaluations.freeze',[$assessment,$dataset]) }}">@csrf<label class="check"><input type="checkbox" name="confirmation" value="1" required>Saya telah memeriksa sasaran, pembagian soal, snapshot dan seluruh acuan manusia.</label><button>Bekukan protokol dan acuan</button></form></section>
@else
<section class="card"><h2>Jalankan eksperimen berpasangan</h2><p>Jalankan M05 dari snapshot yang sama terlebih dahulu. Sistem menyalin hasil M05 lengkap yang cocok dengan model/configuration aktif, lalu mengantrekan satu panggilan Marker baru per kasus yang memiliki pasangan. Kasus tanpa M05 cocok ditandai missing_multi, bukan diberi skor nol.</p>
<p>Baseline tidak menerima label manusia atau hasil multi-agent. Maksimal 100 permintaan per eksperimen; ada pemakaian API. Test yang diulang tidak lagi dianggap pengujian baru yang belum pernah dilihat.</p>
<form method="POST" action="{{ route('evaluations.start',[$assessment,$dataset]) }}">@csrf<input type="hidden" name="creation_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
<label>Split eksperimen<select name="split" required><option value="tuning">Tuning</option><option value="test">Test</option></select></label>
<label class="check"><input type="checkbox" name="confirmation" value="1" required>Saya memastikan snapshot bebas identitas pribadi dan meminta baseline baru melalui penyedia AI server yang telah dikonfigurasi.</label>
<label class="check"><input type="checkbox" name="reuse" value="1">Bila test pernah dipakai, saya mengakui penggunaan ulang dan tidak akan menganggapnya test baru.</label><button>Antrekan baseline eksperimen</button></form></section>
@endif
<section class="card"><h2>Kasus dan acuan manusia</h2>@forelse($cases as $case)<p><a href="{{ route('evaluations.case',[$assessment,$dataset,$case]) }}">Kasus #{{ $case->id }}</a> · {{ $case->split }} · {{ $case->latestReference?'acuan versi '.$case->latestReference->version:'belum ada acuan' }}</p>
@empty<p>Belum ada kasus.</p>
@endforelse{{ $cases->links() }}</section>
<section class="card"><h2>30 eksperimen terakhir</h2>@forelse($experiments as $experiment)<p><a href="{{ route('evaluations.experiment',[$assessment,$dataset,$experiment]) }}">Eksperimen #{{ $experiment->id }}</a> · {{ $experiment->split }} · {{ $experiment->samples_count }} kasus · {{ $experiment->config_snapshot['provider'] }} / {{ $experiment->config_snapshot['model'] }}@if($experiment->test_reused) · test digunakan ulang
@endif</p>
@empty<p>Belum ada eksperimen. Belum ada kesimpulan kualitas yang dapat dibuat.</p>
@endforelse</section>
@endsection

