@extends('layout')
@section('content')
<a href="{{ route('assessments.show', $assessment) }}">← Kembali ke asesmen</a><p class="eyebrow">{{ $assessment->title }}</p><h1>Ubah soal {{ $question->number }}</h1>
@include('question-bank-origin')
<section class="card"><form method="POST" action="{{ route('questions.update', [$assessment, $question]) }}">@csrf @method('PATCH')
@include('question-fields')<button>Simpan soal</button></form></section>
@endsection
