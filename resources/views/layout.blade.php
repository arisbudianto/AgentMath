<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>AgentMath</title><link rel="stylesheet" href="{{ asset('app.css') }}"></head>
<body><header><a class="brand" href="{{ auth()->check()?route('dashboard'):route('home') }}">AgentMath</a><span>Matematika SD</span>
@auth <nav><a href="{{ route('dashboard') }}">Beranda</a>@can('learn')<a href="{{ route('student.index') }}">Ruang siswa</a>@endcan @can('teach')<a href="{{ route('classrooms.index') }}">Kelas</a><a href="{{ route('assessments.index') }}">Asesmen</a><a href="{{ route('bank.index') }}">Bank soal</a>@endcan @can('manage-users')<a href="{{ route('users.index') }}">Akun</a><a href="{{ route('ai.status') }}">Layanan AI</a>@endcan</nav><form method="POST" action="{{ route('logout') }}">@csrf<button class="secondary">Keluar</button></form>@endauth</header>
<main>@if(session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
@if($errors->any())<div class="error" role="alert">@foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach</div>@endif
@yield('content')</main><footer>AgentMath · Keputusan nilai akhir tetap pada pengajar.</footer></body></html>
