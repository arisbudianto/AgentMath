@extends('layout')
@section('content')
<div class="student-page">
    <nav class="student-crumb" aria-label="Jejak navigasi"><a href="{{ route('dashboard') }}">Beranda</a><span aria-hidden="true">/</span><span>Tugas saya</span></nav>
    <section class="student-hero student-hero-compact"><div><p class="student-kicker">SAATNYA MENCOBA</p><h1>Tugas dari guru</h1><p>Pilih tugas yang masih terbuka. Baca soalnya dengan tenang, lalu kirim jawabanmu.</p><a class="student-cta" href="{{ route('student.index') }}">Lihat jawaban saya <span aria-hidden="true">→</span></a></div><div class="student-hero-art" aria-hidden="true"><span class="student-art-sun">✳</span><span class="student-art-number">2 + 3</span><span class="student-art-shape">●</span></div></section>
    <div class="student-section-heading"><div><p class="student-kicker">PILIH PETUALANGANMU</p><h2>Tugas yang dapat dikerjakan</h2></div><p>{{ count($available) }} tugas pada halaman ini</p></div>
    <div class="student-task-grid">
    @forelse($available as $task)
        <a class="student-task-card" href="{{ route('student.tasks.show',$task['id']) }}"><span class="student-task-icon" aria-hidden="true">✎</span><span class="student-pill">{{ count($task['questions']) }} soal</span><strong>{{ $task['title'] }}</strong><span>Batas: {{ $task['closes_at']->timezone(config('app.display_timezone'))->format('d M Y, H:i') }} WIB</span><b>Kerjakan tugas <span aria-hidden="true">→</span></b></a>
    @empty
        <section class="student-empty"><span aria-hidden="true">☆</span><h3>Belum ada tugas terbuka</h3><p>Tugas baru akan muncul saat guru membukanya. Sambil menunggu, kamu bisa melihat kembali jawabanmu.</p><a class="student-cta" href="{{ route('student.index') }}">Lihat jawaban saya →</a></section>
    @endforelse
    </div>
    {{ $pages->links() }}
</div>
@endsection
