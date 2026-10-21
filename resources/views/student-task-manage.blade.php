@extends('layout')
@section('content')
<a href="{{ route('assessments.show',$assessment) }}">← Asesmen</a><h1>Tugas siswa</h1>
<p class="notice">Periksa pratinjau teks soal, judul dan petunjuk sebelum membuka akses seluruh siswa yang ditautkan pada kelas ini. Jangan menuliskan kunci atau data pribadi dalam teks publik tersebut. Kunci dan rubrik internal tidak disertakan. Perubahan sumber menahan akses sampai tugas dibuka ulang. Menutup tugas tidak menarik kembali soal yang sudah dibaca siswa.</p>
<section class="card"><h2>Status versi terakhir</h2><p>Versi {{ $head?->version ?? 0 }} · {{ $head?->action ?? 'belum dibuka' }} · batas {{ $head?->closes_at?->timezone(config('app.display_timezone'))->format('d-m-Y H:i T') ?? '—' }}</p>
@if($preview)
<h2>Pratinjau yang akan dibuka</h2><h3>{{ $preview['title'] }}</h3><p class="preserve">{{ $preview['instructions'] }}</p>
@foreach($preview['questions'] as $q)
<p>Soal {{ $q['number'] }} · maksimum {{ $q['max_score'] }}</p><p class="preserve">{{ $q['prompt'] }}</p>
@endforeach
<form method="POST" action="{{ route('tasks.control',$assessment) }}">@csrf<input type="hidden" name="creation_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_version" value="{{ $head?->version ?? 0 }}"><input type="hidden" name="action" value="open"><label>Batas waktu ({{ config('app.display_timezone') }}, maksimal 90 hari)<input type="datetime-local" name="closes_at" required></label><label class="check"><input type="checkbox" name="confirmation" value="1" required>Saya memeriksa pratinjau dan membuka tugas untuk siswa kelas ini.</label><button>Buka versi tugas</button></form>
@else
<p>Lengkapi kelas dan 1–100 soal sebelum membuka tugas.</p>
@endif
@if($head?->action==='open')
<form method="POST" action="{{ route('tasks.control',$assessment) }}">@csrf<input type="hidden" name="creation_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_version" value="{{ $head->version }}"><input type="hidden" name="action" value="close"><label class="check"><input type="checkbox" name="confirmation" value="1" required>Saya ingin menutup akses/pengiriman tugas ini.</label><button class="secondary">Tutup tugas</button></form>
@endif
<p>Setiap soal menerima maksimal tiga pengiriman siswa per versi tugas. Jawaban yang dicatat atau sudah diperiksa guru hanya dapat dikoreksi pengajar. Pengiriman tidak memanggil AI.</p></section>
<section class="card"><h2>20 versi terakhir</h2>
@foreach($history as $event)
<p>Versi {{ $event->version }} · {{ $event->action }} · {{ $event->created_at }}</p>
@endforeach
</section>
@endsection
