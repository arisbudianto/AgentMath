@extends('public-layout')
@section('title','Masuk kelas · AgentMath')
@section('page-class','login-page')
@section('content')
<section class="login-scene section-wrap"><div class="login-welcome"><p class="little-label">SENANG BERTEMU LAGI!</p><h1>Siap melanjutkan<br><span>petualanganmu?</span></h1><p>Tugas dari guru dan cerita belajarmu menunggu di dalam kelas.</p><img src="{{ asset('images/math-garden.svg') }}" width="480" height="405" alt="Dua sahabat angka menyambutmu di taman belajar"></div><div class="login-panel"><a class="back-home" href="{{ route('home') }}">← Kembali ke halaman depan</a><span class="login-star" aria-hidden="true">✦</span><h2>Masuk ke AgentMath</h2><p>Untuk siswa, guru, dan admin.</p>
@if(session('status'))<p class="login-notice" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="login-error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
<form method="POST" action="{{ route('login') }}">@csrf
<label for="email">Email<input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" maxlength="255" placeholder="Email akunmu"></label>
<label for="password">Password<input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Password akunmu"></label>
<button class="pill-button" type="submit">Masuk kelas <span aria-hidden="true">→</span></button></form><div class="account-help"><span aria-hidden="true">♡</span><p>Belum punya akun atau lupa password?<br>Minta bantuan guru atau admin sekolahmu. Akun disediakan oleh admin.</p></div></div></section>
@endsection
