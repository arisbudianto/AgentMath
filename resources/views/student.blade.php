@extends('layout')
@section('content')
<p><a class="button" href="{{ route('student.tasks.index') }}">Buka tugas dan kirim jawaban</a></p>
<p class="eyebrow">RUANG SISWA</p><h1>Jawaban saya</h1><section class="card"><h2>{{ $participant->classroom->name }}</h2><p>Kelas {{ $participant->classroom->grade }} · {{ $participant->classroom->school_year }} · Kode peserta: <strong>{{ $participant->code }}</strong></p><p>Jawaban berasal dari catatan pengajar atau pengiriman Anda pada tugas yang dibuka. Halaman ini hanya menampilkan jawaban Anda sendiri. Nilai hanya tampil bila diterbitkan pengajar dan masih berlaku.</p></section>
@forelse($answers as $answer) @php($revision=$answer->latestRevision)
@if($grades[$answer->id] ?? null)
<section class="card"><h2>Nilai diterbitkan · soal {{ $grades[$answer->id]['question_number'] }}</h2><p>{{ $grades[$answer->id]['total_score'] }} / {{ $grades[$answer->id]['max_score'] }}</p><p class="preserve">{{ $grades[$answer->id]['message'] }}</p></section>
@endif
@if($revision)<section class="card"><h2>Soal {{ $revision->question_snapshot['number'] }} · revisi {{ $revision->version }}</h2><p class="preserve">{{ $revision->question_snapshot['prompt'] }}</p>
@if($revision->content_type==='text')<pre class="answer-text">{{ $revision->answer_text }}</pre>@else<img class="answer-photo" src="{{ route('student.photo',[$answer,$revision]) }}" alt="Foto jawaban saya">@if($revision->latestTranscription?->status==='confirmed')<p>Transkripsi telah diperiksa pengajar.</p><pre class="answer-text">{{ $revision->latestTranscription->transcript_text }}</pre>@else<p>Transkripsi sedang menunggu pemeriksaan pengajar.</p>@endif @endif</section>@endif
@empty<section class="card"><h2>Belum ada jawaban</h2><p>Jawaban akan tampil setelah pengajar mencatatnya untuk kode peserta Anda.</p></section>@endforelse {{ $answers->links() }}
@endsection
