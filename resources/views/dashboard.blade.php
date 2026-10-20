@extends('layout')
@section('content')
<p class="eyebrow">RUANG KERJA</p><h1>Selamat datang, {{ auth()->user()->name }}</h1>
@can('teach')<section class="card"><h2>Siapkan kelas dan asesmen</h2><p>Daftarkan peserta berkode, lalu lengkapi soal, kunci jawaban, pedoman penilaian, dan skor maksimum.</p><div class="actions"><a class="button" href="{{ route('classrooms.index') }}">Kelola kelas</a><a class="button secondary" href="{{ route('assessments.index') }}">Buka asesmen</a></div></section>@endcan
@can('manage-users')<section class="card"><h2>Administrasi</h2><p>Kelola akun pengajar dan periksa kesiapan konfigurasi AI.</p><a class="button" href="{{ route('users.index') }}">Kelola akun</a><p>Admin tidak memiliki akses otomatis ke isi asesmen pengajar.</p></section>@endcan
@endsection
