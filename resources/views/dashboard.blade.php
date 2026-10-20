@extends('layout')
@section('content')
<p class="eyebrow">RUANG KERJA</p><h1>Selamat datang, {{ auth()->user()->name }}</h1>
@can('teach')<section class="card"><h2>Asesmen Anda</h2><p>Buat ruang asesmen. Soal, rubrik, dan penilaian AI akan tersedia pada modul berikutnya.</p><a class="button" href="{{ route('assessments.index') }}">Buka asesmen</a></section>@endcan
@can('manage-users')<section class="card"><h2>Administrasi</h2><p>Kelola akun pengajar dan periksa kesiapan konfigurasi AI.</p><a class="button" href="{{ route('users.index') }}">Kelola akun</a><p>Admin tidak memiliki akses otomatis ke isi asesmen pengajar.</p></section>@endcan
@endsection
