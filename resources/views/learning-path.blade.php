@extends('layout')
@section('content')
<div class="student-page learning-page">
<nav class="student-crumb" aria-label="Jejak navigasi"><a href="{{ route('dashboard') }}">Beranda</a><span aria-hidden="true">/</span><span>Jalur belajar</span></nav>
<section class="student-hero student-hero-compact"><div><p class="student-kicker">PETUALANGAN MATEMATIKA</p><h1>Jalur belajarku</h1><p>Mulai dari pretest, jelajahi delapan materi berurutan, lalu akhiri dengan post-test. Setiap langkah membukakan langkah berikutnya.</p></div><div class="student-hero-art" aria-hidden="true"><span class="student-art-sun">✳</span><span class="student-art-number">1 → 8</span><span class="student-art-shape">●</span></div></section>
@if(!$path)
<section class="student-empty"><span aria-hidden="true">☆</span><h2>Jalur belajar belum dibuka</h2><p>Guru perlu menyiapkan pretest dan post-test untuk kelasmu terlebih dahulu.</p><a class="student-cta" href="{{ route('student.index') }}">Lihat jawaban saya →</a></section>
@else
<p class="learning-progress-note">{{ $current===10 ? 'Semua 10 tahap selesai. Hebat!' : 'Tahap '.($current+1).' dari 10 sedang terbuka.' }} Buku: {{ $path->book->title }}</p>
<div class="learning-stage-list">
@for($stage=0;$stage<10;$stage++)
    @php($isCurrent=$current===$stage)
    @php($isDone=$current>$stage)
    @php($name=$stage===0?'Pretest':($stage===9?'Post-test':'Materi '.$stage.' · '.($chapters->firstWhere('number',$stage)?->title ?? '')))
    <article class="learning-stage {{ $isDone?'is-done':($isCurrent?'is-current':'is-locked') }}"><span class="learning-stage-number">{{ $isDone?'✓':str_pad($stage+1,2,'0',STR_PAD_LEFT) }}</span><div><p class="student-kicker">{{ $isDone?'SELESAI':($isCurrent?'SEDANG TERBUKA':'TERKUNCI') }}</p><h2>{{ $name }}</h2><p>{{ $stage===0?'Kenali kemampuan awalmu.':($stage===9?'Lihat perkembangan setelah delapan materi.':'Pelajari seluruh halaman bab dan tandai yang sudah dibaca.') }}</p>
    @if($isCurrent)
        @if($stage===0 || $stage===9)
            @if($task)<a class="student-cta" href="{{ route('student.tasks.show',$task) }}">Kerjakan {{ strtolower($name) }} →</a>@else<p class="learning-wait">Menunggu guru membuka tugas tes ini.</p>@endif
        @else<a class="student-cta" href="{{ route('learning.material',$stage) }}">Pelajari materi {{ $stage }} →</a>@endif
    @elseif($isDone && $stage>=1 && $stage<=8)<a href="{{ route('learning.material',$stage) }}">Buka kembali materi →</a>@endif
    </div></article>
@endfor
</div>
@endif
</div>
@endsection
