@extends('layout')
@section('content')
<a href="{{ route('student.tasks.index') }}">← Tugas saya</a><h1>{{ $visible['title'] }}</h1><p class="preserve">{{ $visible['instructions'] }}</p>
<p>Batas waktu {{ $visible['closes_at']->timezone(config('app.display_timezone'))->format('d-m-Y H:i T') }}. Kirim setiap soal secara terpisah. Jawaban tidak tersimpan sebelum tombol kirim ditekan. Penilaian dilakukan setelah pengajar memeriksa jawaban.</p>
@foreach($visible['questions'] as $q)
@php($row=$rows[$q['id']])
<section class="card"><h2>Soal {{ $q['number'] }} · maksimum {{ $q['max_score'] }}</h2><p class="preserve">{{ $q['prompt'] }}</p><p>{{ $row['sent'] }} / 3 pengiriman pada versi tugas ini · revisi jawaban {{ $row['version'] }}</p>
@if($row['editable'] && $row['sent']<3)
<form method="POST" enctype="multipart/form-data" action="{{ route('student.tasks.submit',[$visible['id'],$q['id']]) }}">@csrf<input type="hidden" name="creation_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_version" value="{{ $row['version'] }}">
<label>Jenis jawaban<select name="content_type"><option value="text">Ketikan</option><option value="photo">Foto tulisan tangan</option></select></label><label>Jawaban ketikan<textarea name="answer_text" rows="5" maxlength="20000">{{ $row['text'] }}</textarea></label><label>Foto JPG/PNG (maksimal 5 MB, satu jawaban untuk soal ini)<input type="file" name="photo" accept="image/jpeg,image/png"></label><label class="check"><input type="checkbox" name="confirmation" value="1" required>Saya memeriksa jawaban yang akan dikirim. Foto hanya berisi jawaban, tanpa identitas pribadi.</label><button>Kirim jawaban soal {{ $q['number'] }}</button></form>
@else
<p>Pengiriman tidak tersedia: batas pengiriman tercapai atau jawaban sudah dikelola/diperiksa guru. Hubungi pengajar untuk koreksi.</p>
@endif
</section>
@endforeach
@endsection
