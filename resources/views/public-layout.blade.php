<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#fffaf3"><meta name="description" content="Temukan serunya matematika bersama AgentMath. Kenali angka, bentuk, dan cerita melalui latihan kecil untuk anak SD, dengan pendampingan guru."><title>@yield('title','AgentMath · Angka kecil, petualangan besar')</title><link rel="stylesheet" href="{{ asset('home.css') }}?v=m14"></head>
<body class="public-site @yield('page-class')">
<a class="skip-link" href="#utama">Langsung ke isi</a>
<header class="site-header"><a class="home-brand" href="{{ route('home') }}" aria-label="AgentMath, halaman depan"><span class="brand-mark" aria-hidden="true">a<span>✦</span></span><span>Agent<span class="brand-math">Math</span><small>Teman tumbuh bersama angka</small></span></a>
<nav aria-label="Navigasi utama"><a href="{{ route('home') }}#materi">Jelajahi materi</a><a href="{{ route('home') }}#coba">Coba latihan</a><a href="{{ route('home') }}#guru">Untuk guru</a></nav>
@auth<a class="pill-button small" href="{{ route('dashboard') }}">Ruang saya <span aria-hidden="true">↗</span></a>@else<a class="pill-button small outline" href="{{ route('login') }}">Masuk kelas <span aria-hidden="true">↗</span></a>@endauth</header>
<main id="utama">@yield('content')</main>
<footer class="site-footer"><a class="footer-brand" href="{{ route('home') }}">AgentMath<span aria-hidden="true"> ✦</span></a><p>Rasa ingin tahu hari ini. Bekal hebat untuk esok.</p><div><a href="{{ route('home') }}#materi">Materi kelas 1</a><a href="{{ route('login') }}">Masuk ke akun</a></div><small>Belajar bersama. Guru tetap mendampingi dan menentukan nilai akhir.</small></footer>
@stack('scripts')
</body></html>
