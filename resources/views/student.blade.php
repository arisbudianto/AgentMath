@extends('layout')
@section('content')
<div class="student-page">
    <nav class="student-crumb" aria-label="Jejak navigasi"><a href="{{ route('dashboard') }}">Beranda</a><span aria-hidden="true">/</span><span>Jawaban saya</span></nav>
    <section class="student-hero student-hero-compact"><div><p class="student-kicker">CERITA BELAJARKU</p><h1>Jawaban &amp; nilai saya</h1><p>Lihat kembali jawabanmu. Nilai akan muncul setelah guru menerbitkannya.</p><a class="student-cta" href="{{ route('student.tasks.index') }}">Lihat tugas dari guru <span aria-hidden="true">→</span></a></div><div class="student-hero-art" aria-hidden="true"><span class="student-art-sun">✳</span><span class="student-art-number">A+ ★</span><span class="student-art-shape">●</span></div></section>
    <section class="student-class-card" aria-label="Kelas saya"><span class="student-class-icon" aria-hidden="true">⌂</span><div><p class="student-kicker">KELAS SAYA</p><h2>{{ $participant->classroom->name }}</h2><p>Kelas {{ $participant->classroom->grade }} · Tahun ajaran {{ $participant->classroom->school_year }}</p></div><span class="student-code">Kode {{ $participant->code }}</span></section>
    <div class="student-section-heading"><div><p class="student-kicker">HASIL PEKERJAANKU</p><h2>Jawaban saya</h2></div><p>Hanya jawaban milikmu yang tampil di sini.</p></div>
    <div class="student-answer-list">
    @forelse($answers as $answer)
        @php($revision=$answer->latestRevision)
        @if($revision)
        <article class="student-answer-card">
            <div class="student-answer-head"><div><span class="student-pill">Soal {{ $revision->question_snapshot['number'] }}</span><h3>Jawaban saya</h3></div><span class="student-revision">Revisi {{ $revision->version }}</span></div>
            <p class="student-question preserve">{{ $revision->question_snapshot['prompt'] }}</p>
            @if($revision->content_type==='text')<pre class="student-answer-text">{{ $revision->answer_text }}</pre>
            @else<img class="answer-photo" src="{{ route('student.photo',[$answer,$revision]) }}" alt="Foto jawaban saya"><p class="student-note">{{ $revision->latestTranscription?->status==='confirmed' ? 'Transkripsi telah diperiksa guru.' : 'Transkripsi sedang menunggu pemeriksaan guru.' }}</p>@if($revision->latestTranscription?->status==='confirmed')<pre class="student-answer-text">{{ $revision->latestTranscription->transcript_text }}</pre>@endif
            @endif
            @if($grades[$answer->id] ?? null)
            <div class="student-grade"><div><span class="student-kicker">Nilai diterbitkan · soal {{ $grades[$answer->id]['question_number'] }}</span><strong>{{ $grades[$answer->id]['total_score'] }} <small>/ {{ $grades[$answer->id]['max_score'] }}</small></strong></div>@if($grades[$answer->id]['message'])<p class="preserve">{{ $grades[$answer->id]['message'] }}</p>@endif</div>
            @else<p class="student-pending">Nilai belum diterbitkan guru.</p>@endif
        </article>
        @endif
    @empty
        <section class="student-empty"><span aria-hidden="true">✎</span><h3>Belum ada jawaban</h3><p>Jawabanmu akan tampil di sini setelah kamu mengirim tugas atau guru mencatatnya.</p><a class="student-cta" href="{{ route('student.tasks.index') }}">Lihat tugas saya →</a></section>
    @endforelse
    </div>
    {{ $answers->links() }}
</div>
@endsection
