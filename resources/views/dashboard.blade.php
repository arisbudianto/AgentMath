@extends('layout')
@section('content')
@can('learn')
<div class="student-page student-dashboard">
    <section class="student-hero">
        <div><p class="student-kicker">RUANG BELAJARKU</p><h1>Halo, {{ auth()->user()->name }}! <span aria-hidden="true">✦</span></h1><p>Ayo lanjutkan petualangan angkamu. Pilih tugas dari guru atau lihat kembali jawabanmu.</p><a class="student-cta" href="{{ route('student.tasks.index') }}">Lihat tugas saya <span aria-hidden="true">→</span></a></div>
        <div class="student-hero-art" aria-hidden="true"><span class="student-art-sun">✳</span><span class="student-art-number">1 2 3</span><span class="student-art-shape">●</span></div>
    </section>
    <div class="student-home-grid">
        <a class="student-home-card" href="{{ route('student.tasks.index') }}"><span class="student-home-icon" aria-hidden="true">✎</span><span class="student-kicker">MULAI DI SINI</span><strong>Tugas dari guru</strong><span>Lihat soal yang sedang terbuka dan kirim jawabanmu.</span><b>Lihat tugas <span aria-hidden="true">→</span></b></a>
        <a class="student-home-card" href="{{ route('student.index') }}"><span class="student-home-icon mint" aria-hidden="true">☆</span><span class="student-kicker">CERITA BELAJARKU</span><strong>Jawaban &amp; nilai</strong><span>Baca kembali jawabanmu dan nilai yang sudah diterbitkan guru.</span><b>Buka ruang siswa <span aria-hidden="true">→</span></b></a>
    </div>
    <p class="student-kind-note">Tidak apa-apa mencoba lagi. Guru akan membantumu memahami setiap langkah.</p>
</div>
@else
<p class="eyebrow">RUANG KERJA</p><h1>Selamat datang, {{ auth()->user()->name }}</h1>
@endcan
@can('teach')<section class="card"><h2>Siapkan kelas dan asesmen</h2><p>Daftarkan peserta berkode, lalu lengkapi soal, kunci jawaban, pedoman penilaian, dan skor maksimum.</p><div class="actions"><a class="button" href="{{ route('classrooms.index') }}">Kelola kelas</a><a class="button secondary" href="{{ route('assessments.index') }}">Buka asesmen</a></div></section>@endcan
@can('manage-users')<section class="card"><h2>Administrasi</h2><p>Kelola akun pengajar dan periksa kesiapan konfigurasi AI.</p><a class="button" href="{{ route('users.index') }}">Kelola akun</a><p>Admin tidak memiliki akses otomatis ke isi asesmen pengajar.</p></section>@endcan
@endsection

