@extends('layout')
@section('content')
<div class="student-page">
<nav class="student-crumb" aria-label="Jejak navigasi"><a href="{{ route('student.tasks.index') }}">Tugas saya</a><span aria-hidden="true">/</span><span>{{ $visible['title'] }}</span></nav>
<section class="student-task-intro"><p class="student-kicker">TUGAS DARI GURU</p><h1>{{ $visible['title'] }}</h1>@if($visible['instructions'])<p class="preserve">{{ $visible['instructions'] }}</p>@endif<div class="student-task-meta"><span>{{ count($visible['questions']) }} soal</span><span>Batas {{ $visible['closes_at']->timezone(config('app.display_timezone'))->format('d M Y, H:i') }} WIB</span></div></section>
<p class="student-help-note">Kirim setiap soal secara terpisah. Jawaban tersimpan setelah tombol kirim ditekan. Guru akan memeriksa hasilnya.</p>
@foreach($visible['questions'] as $q)
@php($row=$rows[$q['id']])
<section class="student-question-card"><div class="student-answer-head"><div><span class="student-pill">SOAL {{ $q['number'] }}</span><h2>Ayo, coba kerjakan</h2></div><span class="student-revision">Maksimum {{ $q['max_score'] }} poin</span></div><p class="student-question preserve">{{ $q['prompt'] }}</p><p class="student-note">{{ $row['sent'] }} dari 3 kesempatan kirim · revisi jawaban {{ $row['version'] }}</p>
@if($row['editable'] && $row['sent']<3)
<form class="student-answer-form" method="POST" enctype="multipart/form-data" action="{{ route('student.tasks.submit',[$visible['id'],$q['id']]) }}">@csrf<input type="hidden" name="creation_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_version" value="{{ $row['version'] }}">
<label>Jenis jawaban<select name="content_type"><option value="text">Ketikan</option><option value="photo">Foto tulisan tangan</option></select></label><label>Jawaban ketikan<textarea name="answer_text" rows="5" maxlength="20000">{{ $row['text'] }}</textarea></label><label>Foto JPG/PNG (maksimal 5 MB, satu jawaban untuk soal ini)<input type="file" name="photo" accept="image/jpeg,image/png"></label><label class="check"><input type="checkbox" name="confirmation" value="1" required>Saya memeriksa jawaban yang akan dikirim. Foto hanya berisi jawaban, tanpa identitas pribadi.</label><button>Kirim jawaban soal {{ $q['number'] }}</button></form>
@else
<p>Pengiriman tidak tersedia: batas pengiriman tercapai atau jawaban sudah dikelola/diperiksa guru. Hubungi pengajar untuk koreksi.</p>
@endif
</section>
@endforeach
</div>
@endsection
