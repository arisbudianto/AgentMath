@extends('layout')
@section('content')
<a href="{{ route('assessments.show',$assessment) }}">← Asesmen</a><h1>Publikasi nilai siswa</h1>
<p class="notice">Pengesahan guru tidak otomatis menerbitkan nilai. Terbitkan per jawaban setelah memeriksa skor dan pesan untuk siswa. Kunci, rubrik, usulan AI dan alasan internal tidak diteruskan. Perubahan sumber, keputusan/draf baru atau pencabutan akan menyembunyikan nilai; perlu penerbitan ulang. Pencabutan tidak menghapus salinan yang sudah dibaca/disimpan siswa.</p>
@forelse($answers as $answer)
@php($row=$rows[$answer->id])
<section class="card"><h2>{{ $answer->participant->code }} · soal {{ $answer->question->number }}</h2>
<p>{{ $row['visible']?'Nilai terlihat di portal siswa':'Tidak ada nilai yang terlihat' }} · versi publikasi {{ $row['head']?->version ?? 0 }}</p>
@if($row['candidate'])
<p>Keputusan disahkan #{{ $row['candidate']['review_id'] }} · {{ $row['candidate']['total_score'] }} / {{ $row['candidate']['max_score'] }}</p>
<form method="POST" action="{{ route('publications.store',[$assessment,$answer]) }}">@csrf
<input type="hidden" name="creation_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_version" value="{{ $row['head']?->version ?? 0 }}"><input type="hidden" name="action" value="publish"><input type="hidden" name="review_id" value="{{ $row['candidate']['review_id'] }}">
<label>Pesan untuk siswa (opsional)<textarea name="message" maxlength="1000" rows="3"></textarea></label><label class="check"><input type="checkbox" name="confirmation" value="1" required>Saya telah memeriksa nilai/pesan dan ingin menerbitkannya untuk siswa terkait.</label><button>Terbitkan nilai</button></form>
@else
<p>Belum ada keputusan terakhir yang disahkan dan valid untuk sumber terkini.</p>
@endif
@if($row['head']?->action==='publish')
<form method="POST" action="{{ route('publications.store',[$assessment,$answer]) }}">@csrf<input type="hidden" name="creation_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}"><input type="hidden" name="expected_version" value="{{ $row['head']->version }}"><input type="hidden" name="action" value="revoke"><label class="check"><input type="checkbox" name="confirmation" value="1" required>Saya ingin mencabut tampilan nilai ini.</label><button class="secondary">Cabut publikasi</button></form>
@endif
</section>
@empty
<p>Belum ada jawaban.</p>
@endforelse
{{ $answers->links() }}
@endsection
