@extends('layout')
@section('content')
<section class="login card"><p class="eyebrow">RUANG PENGAJAR</p><h1>Masuk ke AgentMath</h1><p>Kelola asesmen matematika dalam ruang kerja Anda.</p>
<form method="POST" action="{{ route('login') }}">@csrf
<label>Email<input type="email" name="email" value="{{ old('email') }}" required autocomplete="username" maxlength="255"></label>
<label>Password<input type="password" name="password" required autocomplete="current-password"></label>
<button>Masuk</button></form><p class="muted">Akun disediakan oleh admin. Hubungi admin jika Anda belum memiliki akun atau membutuhkan bantuan password.</p></section>
@endsection
