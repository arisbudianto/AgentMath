@extends('layout')
@section('content')
<a href="{{ route('answers.index',$assessment) }}">← Penerimaan jawaban</a><p class="eyebrow">{{ $assessment->title }}</p><h1>Impor CSV #{{ $batch->id }}</h1>
@if($batch->status === 'committed')<p class="notice">{{ $batch->imported_count }} jawaban sudah diimpor. Konfirmasi ulang tidak menggandakan jawaban.</p>
@elseif($batch->status !== 'preview' || $batch->expires_at->isPast())<p class="notice">Pratinjau dibatalkan atau kedaluwarsa. Buat pratinjau baru untuk melanjutkan.</p>
@else
<p>{{ $batch->row_count }} baris data. Berlaku hingga {{ $batch->expires_at->timezone(config('app.display_timezone'))->format('d-m-Y H:i T') }}. Belum ada jawaban yang ditambahkan.</p>
@if($hasErrors)<p class="error">Ada baris tidak valid. Perbaiki CSV dan unggah ulang; seluruh batch belum dapat dikonfirmasi.</p>@else<p class="notice">Semua baris lolos pemeriksaan awal. Periksa peserta, soal, dan isi jawaban sebelum konfirmasi.</p>@endif
<section class="card"><h2>Pratinjau seluruh baris</h2>@foreach($rows as $row)<article><h3>Record {{ $row['record'] }} · {{ $row['participant_code'] }} · Soal {{ $row['question_number'] }}</h3><pre class="answer-text">{{ $row['answer_text'] }}</pre>@foreach($row['errors'] as $error)<p class="error">{{ $error }}</p>@endforeach</article>@endforeach</section>
@if(!$hasErrors && count($rows))<section class="card"><h2>Konfirmasi impor</h2><p>Saat konfirmasi, data diperiksa ulang. Jika peserta, soal, atau jawaban berubah, batch ditahan tanpa impor sebagian.</p><form method="POST" action="{{ route('imports.commit',[$assessment,$batch]) }}">@csrf<label class="check"><input type="checkbox" name="confirmation" value="1" required> Saya sudah memeriksa seluruh baris dan menyetujui pemasukan jawaban ke peserta dan soal yang ditampilkan.</label><button>Konfirmasi impor {{ $batch->row_count }} jawaban</button></form></section>@endif
<form method="POST" action="{{ route('imports.cancel',[$assessment,$batch]) }}">@csrf<button class="secondary">Batalkan pratinjau</button></form>
@endif
@endsection
