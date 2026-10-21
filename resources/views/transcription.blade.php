@extends('layout')
@section('content')
<a href="{{ route('answers.show',[$assessment,$answer]) }}">← Kembali ke jawaban</a>
<p class="eyebrow">{{ $assessment->title }} · {{ $photo->participant_code_snapshot }} · Soal {{ $photo->question_snapshot['number'] }}</p>
<h1>Transkripsi foto · revisi {{ $photo->version }}</h1>
@if(!$current)<p class="error">Foto ini adalah riwayat lama. Transkripsi tidak dapat diubah atau digunakan untuk jawaban terkini.</p>@endif
<p class="notice">{{ $ready && $current ? 'Teks telah dikonfirmasi dan cocok dengan dasar soal terkini. Belum dinilai.' : 'Belum tersedia teks terkonfirmasi untuk penilaian terkini.' }}</p>
<div class="transcription-grid">
<section class="card"><h2>Foto asli privat</h2><p>Salin tulisan apa adanya, termasuk kesalahan siswa, pecahan, satuan dan langkah pengerjaan.</p><a target="_blank" rel="noopener" href="{{ route('answers.photo',[$assessment,$answer,$photo]) }}">Perbesar foto di tab baru</a><img class="answer-photo" src="{{ route('answers.photo',[$assessment,$answer,$photo]) }}" alt="Foto jawaban yang perlu ditranskripsikan"></section>
<section class="card"><h2>Teks dan pemeriksaan</h2>
<p>Status: <strong>{{ $latest ? ($latest->status==='confirmed'?'Dikonfirmasi pengajar':'Draf, wajib diperiksa') : 'Belum ditranskripsikan' }}</strong> · versi {{ $latest?->version ?? 0 }}</p>
@if($latest?->uncertainties)<h3>Bagian meragukan dari AI</h3><p>Daftar ini dipertahankan untuk pemeriksaan, termasuk setelah koreksi manual.</p><ul>@foreach($latest->uncertainties as $item)<li><strong>{{ $item['location'] }}</strong>: {{ $item['description'] }}</li>@endforeach</ul>@endif
@if($current)
<form method="POST" action="{{ route('transcriptions.store',[$assessment,$answer,$photo]) }}">@csrf
<input type="hidden" name="creation_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_version" value="{{ $latest?->version ?? 0 }}"><input type="hidden" name="action" value="draft">
<label>Teks transkripsi<textarea name="transcript_text" required rows="12" maxlength="20000">{{ is_string(old('transcript_text')) ? old('transcript_text') : ($latest?->transcript_text ?? '') }}</textarea></label>
<label>Alasan pencatatan atau koreksi<input name="reason" required maxlength="500" value="{{ is_string(old('reason')) ? old('reason') : '' }}"></label><button>Simpan draf transkripsi</button></form>
@if($latest?->status==='draft')
<hr><form method="POST" action="{{ route('transcriptions.store',[$assessment,$answer,$photo]) }}">@csrf<input type="hidden" name="creation_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_version" value="{{ $latest->version }}"><input type="hidden" name="action" value="confirm"><input type="hidden" name="transcript_text" value="{{ $latest->transcript_text }}"><input type="hidden" name="reason" value="Pemeriksaan dan konfirmasi transkripsi oleh pengajar">
<p>Konfirmasi berlaku untuk draf tersimpan di bawah ini. Simpan koreksi terlebih dahulu.</p><pre class="answer-text">{{ $latest->transcript_text }}</pre>
<label class="check"><input type="checkbox" name="reviewed" value="1" required>Saya sudah membandingkan seluruh teks dengan foto dan menyelesaikan semua bagian meragukan. Teks tidak mengubah jawaban siswa.</label><button>Konfirmasi transkripsi tersimpan</button></form>
@endif
@else<pre class="answer-text">{{ $latest?->transcript_text }}</pre>@endif
</section></div>
@if($current)<section class="card"><h2>Draf bantuan AI (opsional)</h2><p>AI dapat keliru membaca tulisan, simbol atau pecahan. Foto akan dikirim ke {{ $aiRecipient }} hanya setelah permintaan ini. Kunci jawaban, identitas akun dan kode peserta tidak ikut dikirim sebagai teks. Pastikan foto tidak memuat nama, wajah, atau identitas pribadi.</p>
@if($aiReady)<form method="POST" action="{{ route('transcriptions.store',[$assessment,$answer,$photo]) }}">@csrf<input type="hidden" name="creation_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_version" value="{{ $latest?->version ?? 0 }}"><input type="hidden" name="action" value="ai"><input type="hidden" name="reason" value="Permintaan draf transkripsi AI oleh pengajar"><label class="check"><input type="checkbox" name="privacy_checked" value="1" required>Saya telah memastikan foto tidak berisi identitas pribadi dan menyetujui pengiriman foto untuk membuat draf.</label><button>Minta draf AI dari foto</button></form>@else<p>AI transkripsi belum aktif. Anda tetap dapat mengetik dan mengonfirmasi transkripsi manual.</p>@endif</section>@endif
<section class="card"><h2>Riwayat transkripsi foto ini</h2>@forelse($history as $entry)<details class="history"><summary>Versi {{ $entry->version }} · {{ $entry->status==='confirmed'?'Dikonfirmasi':'Draf' }} · {{ $entry->origin==='ai'?'AI':'Pengajar' }}</summary><p>{{ $entry->creator->name }} · {{ $entry->created_at->timezone(config('app.display_timezone'))->format('d-m-Y H:i T') }}</p><p>Alasan: {{ $entry->reason }}</p><pre class="answer-text">{{ $entry->transcript_text }}</pre>@if($entry->confirmed_at)<p>Dikonfirmasi: {{ $entry->confirmed_at->timezone(config('app.display_timezone'))->format('d-m-Y H:i T') }}</p>@endif</details>@empty<p>Belum ada versi transkripsi.</p>@endforelse{{ $history->links() }}</section>
@endsection
