@extends('layout')
@section('content')
<a href="{{ route('student.index') }}">← Jawaban saya</a><h1>Tugas yang dapat dikerjakan</h1>
@forelse($available as $task)
<section class="card"><h2><a href="{{ route('student.tasks.show',$task['id']) }}">{{ $task['title'] }}</a></h2><p>{{ count($task['questions']) }} soal · batas {{ $task['closes_at']->timezone(config('app.display_timezone'))->format('d-m-Y H:i T') }}</p></section>
@empty
<p>Belum ada tugas terbuka yang masih berlaku pada halaman ini. Hubungi pengajar bila tugas belum tersedia.</p>
@endforelse
{{ $pages->links() }}
@endsection
