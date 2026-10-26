@extends('layout')
@section('content')
<a href="{{ route('assessments.show',$assessment) }}">← {{ $assessment->title }}</a><p class="eyebrow">{{ $assessment->title }}</p><h1>Impor CSV soal #{{ $batch->id }}</h1>
@if($batch->status === 'committed')<p class="notice">{{ $batch->imported_count }} soal sudah diimpor. Konfirmasi ulang tidak menggandakan soal.</p>
@elseif($batch->status !== 'preview' || $batch->expires_at->isPast())<p class="notice">Pratinjau dibatalkan atau kedaluwarsa. Buat pratinjau baru untuk melanjutkan.</p>
@else
<p>{{ $batch->row_count }} baris data. Berlaku hingga {{ $batch->expires_at->timezone(config('app.display_timezone'))->format('d-m-Y H:i T') }}. Belum ada soal yang ditambahkan. Impor hanya menambah soal baru; nomor yang sudah ada pada asesmen ini ditolak.</p>
@if($hasErrors)<p class="error">Ada baris tidak valid. Perbaiki CSV dan unggah ulang; seluruh batch belum dapat dikonfirmasi.</p>@else<p class="notice">Semua baris lolos pemeriksaan awal. Periksa isi soal sebelum konfirmasi.</p>@endif
<section class="card"><h2>Pratinjau seluruh baris</h2>@foreach($rows as $row)<article><h3>Record {{ $row['record'] }} · Soal {{ $row['number'] }} · Maksimum {{ $row['max_score'] }}</h3><p class="preserve">{{ $row['prompt'] }}</p>@foreach($row['errors'] as $error)<p class="error">{{ $error }}</p>@endforeach</article>@endforeach</section>
@if(!$hasErrors && count($rows))<section class="card"><h2>Konfirmasi impor</h2><p>Saat konfirmasi, data diperiksa ulang. Jika nomor soal berubah atau sudah dipakai sejak pratinjau, batch ditahan tanpa impor sebagian.</p><form method="POST" action="{{ route('question-imports.commit',[$assessment,$batch]) }}">@csrf<label class="check"><input type="checkbox" name="confirmation" value="1" required> Saya sudah memeriksa seluruh baris dan menyetujui penambahan soal ini ke asesmen.</label><button>Konfirmasi impor {{ $batch->row_count }} soal</button></form></section>@endif
<form method="POST" action="{{ route('question-imports.cancel',[$assessment,$batch]) }}">@csrf<button class="secondary">Batalkan pratinjau</button></form>
@endif
@endsection
