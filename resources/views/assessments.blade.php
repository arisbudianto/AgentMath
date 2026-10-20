@extends('layout')
@section('content')<p class="eyebrow">ASESMEN</p><h1>Asesmen Anda</h1><section class="card"><h2>Buat asesmen</h2><form method="POST" action="{{ route('assessments.store') }}">@csrf<label>Judul<input name="title" required maxlength="200" value="{{ old('title') }}"></label><button>Simpan</button></form></section>
<section class="card"><h2>Daftar asesmen</h2>@forelse($assessments as $assessment)<p><a href="{{ route('assessments.show', $assessment) }}">{{ $assessment->title }}</a></p>@empty<p>Belum ada asesmen.</p>@endforelse {{ $assessments->links() }}</section>@endsection
