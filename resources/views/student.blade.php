@extends('layout')
@section('content')
<p class="eyebrow">RUANG SISWA</p><h1>Jawaban saya</h1><section class="card"><h2>{{ $participant->classroom->name }}</h2><p>Kelas {{ $participant->classroom->grade }} · {{ $participant->classroom->school_year }} · Kode peserta: <strong>{{ $participant->code }}</strong></p><p>Jawaban dicatat oleh pengajar. Halaman ini hanya menampilkan jawaban Anda sendiri. Belum ada nilai akhir yang diterbitkan.</p></section>
@forelse($answers as $answer) @php($revision=$answer->latestRevision)
@if($revision)<section class="card"><h2>Soal {{ $revision->question_snapshot['number'] }} · revisi {{ $revision->version }}</h2><p class="preserve">{{ $revision->question_snapshot['prompt'] }}</p>
@if($revision->content_type==='text')<pre class="answer-text">{{ $revision->answer_text }}</pre>@else<img class="answer-photo" src="{{ route('student.photo',[$answer,$revision]) }}" alt="Foto jawaban saya">@if($revision->latestTranscription?->status==='confirmed')<p>Transkripsi telah diperiksa pengajar.</p><pre class="answer-text">{{ $revision->latestTranscription->transcript_text }}</pre>@else<p>Transkripsi sedang menunggu pemeriksaan pengajar.</p>@endif @endif</section>@endif
@empty<section class="card"><h2>Belum ada jawaban</h2><p>Jawaban akan tampil setelah pengajar mencatatnya untuk kode peserta Anda.</p></section>@endforelse {{ $answers->links() }}
@endsection
