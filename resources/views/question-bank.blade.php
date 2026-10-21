@extends('layout')
@section('content')
<h1>Bank soal Matematika</h1>
<p>Latihan dari buku sumber, dikelompokkan sebagai paket halaman agar gambar, subsoal dan kegiatan tidak terpisah dari konteksnya. Jumlah paket bukan jumlah butir soal individual.</p>
<form method="GET"><label>Buku<select name="book">@foreach($books as $b)<option value="{{ $b->id }}" @selected($book?->id===$b->id)>Kelas {{ $b->grade }} · {{ $b->title }} · {{ $b->edition }}</option>@endforeach</select></label>
<label>Bab<select name="chapter"><option value="">Semua bab</option>@foreach($chapters as $c)<option value="{{ $c->id }}" @selected(request('chapter')==$c->id)>{{ $c->number }}. {{ $c->title }} ({{ $c->sets_count }} paket)</option>@endforeach</select></label>
<label>Jenis<select name="kind"><option value="">Semua kegiatan</option>@foreach($kinds as $k=>$name)<option value="{{ $k }}" @selected(request('kind')===$k)>{{ $name }}</option>@endforeach</select></label>
<label>Cari teks sumber<input name="q" maxlength="80" value="{{ request('q') }}"></label><button>Cari bank soal</button></form>
@if($book)
<section class="card"><h2>{{ $book->title }}</h2><p>Kelas {{ $book->grade }} · {{ $book->authors }} · {{ $book->edition }} · ISBN {{ $book->isbn }}</p><p>{{ $chapters->count() }} bab · {{ $book->pages()->count() }} halaman materi tersimpan · {{ $chapters->sum('sets_count') }} paket latihan/kegiatan.</p>
<details><summary>Peta materi dan seluruh halaman asli</summary>@foreach($chapters as $c)<h3>Bab {{ $c->number }} · {{ $c->title }}</h3><p>{{ $c->objectives }} · hlm. {{ $c->start_page }}–{{ $c->end_page }}</p><p>@foreach($sourcePages->whereBetween('number',[$c->start_page,$c->end_page]) as $p)<a href="{{ route('bank.page',$p) }}" target="_blank" rel="noopener">{{ $p->number }}</a> · @endforeach</p>@endforeach</details></section>
@endif
<p class="notice">Halaman asli dan teks ekstraksi tersedia untuk guru. Ekstraksi teks bisa kehilangan gambar atau susunan angka. Kunci tidak dibuat otomatis. Sebelum memakai soal dalam asesmen, lengkapi teks/gambar yang diperlukan dan tinjau kunci serta pedoman.</p>
<p>{{ $sets->total() }} paket sesuai pencarian.</p>
@forelse($sets as $s)<article class="card"><h2><a href="{{ route('bank.show',$s) }}">{{ $s->title }}</a></h2><p>Bab {{ $s->chapter->number }} · {{ $kinds[$s->kind] }} · hlm. {{ $s->start_page }}–{{ $s->end_page }}</p><p>{{ \Illuminate\Support\Str::limit($s->source_text,250) }}</p></article>@empty<p>Belum ada paket yang sesuai. Pilih bab lain atau hubungi pengelola untuk impor buku.</p>@endforelse
{{ $sets->links() }}
@endsection
