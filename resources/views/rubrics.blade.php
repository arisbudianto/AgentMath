@extends('layout')
@section('content')
<a href="{{ route('assessments.show',$assessment) }}">← Kembali ke asesmen</a>
<p class="eyebrow">SOAL {{ $question->number }} · {{ $assessment->title }}</p><h1>Pedoman dan rubrik</h1>
@if($currentApproved)<p class="notice">Rubrik berlaku: versi {{ $currentApproved->version }} (disahkan dengan dasar soal terkini).</p>@else<p class="badge">Belum ada rubrik disahkan yang sesuai dengan dasar soal terkini.</p>@endif
<section class="card"><h2>Dasar soal terkini</h2><p class="preserve">{{ $question->prompt }}</p><p><strong>Kunci:</strong> {{ $question->answer_key }}</p><h3>Pedoman penilaian</h3><p class="preserve">{{ $source['scoring_guidelines'] }}</p>
@if($source['instructions'])<h3>Petunjuk asesmen</h3><p class="preserve">{{ $source['instructions'] }}</p>@endif<p>Skor maksimum: <strong>{{ $source['max_score'] }}</strong></p></section>
<section class="card"><h2>Siapkan draf rubrik</h2><p>Draf manual menyalin pedoman menjadi aturan awal. Periksa dan uraikan kriteria sesuai kebutuhan soal.</p>
<form method="POST" action="{{ route('rubrics.store',[$assessment,$question]) }}">@csrf<input type="hidden" name="creation_key" value="{{ Illuminate\Support\Str::uuid() }}"><input type="hidden" name="origin" value="manual"><button>Buat draf manual</button></form>
<h3>Bantuan AI: ekstraksi aturan → penyusunan rubrik</h3><p>AI hanya menerima soal, kunci, pedoman, dan petunjuk umum. Pastikan teks tersebut tidak memuat identitas siswa. Kutipan dan jumlah skor diperiksa server; isi draf tetap harus ditinjau pengajar.</p>
@if($ai['enabled'] && $ai['configured'])<form method="POST" action="{{ route('rubrics.store',[$assessment,$question]) }}">@csrf<input type="hidden" name="creation_key" value="{{ Illuminate\Support\Str::uuid() }}"><input type="hidden" name="origin" value="ai"><button>Buat draf dengan AI</button><p class="muted">Dua permintaan AI; proses dapat memerlukan hingga sekitar 120 detik. Jangan kirim ulang saat proses berjalan.</p></form>
@else<p class="notice">Bantuan AI belum aktif atau konfigurasinya belum lengkap. Draf manual tetap dapat disusun dan disahkan.</p>@endif</section>
<section class="card"><h2>Riwayat versi</h2>@forelse($versions as $version)<article><h3><a href="{{ route('rubrics.show',[$assessment,$question,$version]) }}">Versi {{ $version->version }}</a></h3><p>{{ $version->status === 'approved' ? 'Disahkan · terkunci' : 'Draf · belum disahkan' }} · {{ $version->origin === 'ai' ? 'Draf awal AI' : ($version->origin === 'revision' ? 'Salinan versi sebelumnya' : 'Manual') }}</p>
@if(!$version->matchesSource($source))<p class="error">Dasar soal atau pedoman telah berubah. Versi ini hanya untuk riwayat.</p>@endif
@if($version->approved_at)<p>Disahkan {{ $version->approved_at->timezone(config('app.display_timezone'))->format('d-m-Y H:i T') }} oleh {{ $version->approver?->name }}.</p>@endif</article>@empty<p>Belum ada versi rubrik. Buat draf pertama untuk soal ini.</p>@endforelse</section>
@endsection
