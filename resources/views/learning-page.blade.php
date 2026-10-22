@extends('layout')
@section('content')
<div class="student-page learning-page"><nav class="student-crumb"><a href="{{ route('learning.material',$unit->number) }}">Materi {{ $unit->number }}</a><span aria-hidden="true">/</span><span>Halaman {{ $record->number }}</span></nav>
<div class="learning-reading-head"><div><p class="student-kicker">{{ $unit->title }}</p><h1>Halaman {{ $record->number }}</h1></div><span class="student-pill">{{ $read?'✓ Sudah dibaca':'Belum ditandai' }}</span></div>
<img class="learning-book-page" src="{{ route('learning.image',[$unit->number,$record->number]) }}" alt="Halaman {{ $record->number }} buku Matematika kelas 1">
<div class="learning-reading-actions">
@if($current===$unit->number && !$read)
<form method="POST" action="{{ route('learning.read',[$unit->number,$record->number]) }}">@csrf<button>Sudah membaca halaman ini</button></form>
@endif
@if($next)
<a class="student-cta" href="{{ route('learning.page',[$unit->number,$next]) }}">Halaman berikutnya →</a>
@else
<a class="student-cta" href="{{ route('learning.material',$unit->number) }}">Kembali ke materi →</a>
@endif
</div>
</div>
@endsection
