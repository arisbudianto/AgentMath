@extends('layout')
@section('content')
<a href="{{ route('assessments.show',$assessment) }}">← Kembali ke asesmen</a><p class="eyebrow">{{ $assessment->title }}</p><h1>Evaluasi penelitian</h1>
<p class="notice">Evaluasi terpisah dari nilai operasional. Label acuan diisi manusia tanpa menyalin/melihat hasil AI untuk kasus itu; persetujuan M06 tidak otomatis menjadi acuan penelitian. Akurasi belum dapat disimpulkan sebelum data acuan nyata yang sesuai tersedia.</p>
<section class="card"><h2>Daftarkan protokol dataset</h2>
<form method="POST" action="{{ route('evaluations.store',$assessment) }}">@csrf<input type="hidden" name="creation_key" value="{{ (string)\Illuminate\Support\Str::uuid() }}">
<label>Nama dataset<input name="name" required maxlength="160"></label>
<label>Jenis data<select name="data_kind" required><option value="">Pilih jenis data</option><option value="synthetic">Sintetis — uji fungsi, bukan bukti kualitas nyata</option><option value="real">Data nyata yang telah dihilangkan identitas pribadinya</option></select></label>
<label>Tujuan penelitian<textarea name="goal" required maxlength="2000" rows="3"></textarea></label>
<label>Sasaran keberhasilan yang ditetapkan sebelum eksperimen<textarea name="target" required maxlength="2000" rows="3" placeholder="Nyatakan metrik, ambang, ukuran/karakteristik sampel, dan cara membandingkan dengan acuan manusia."></textarea></label>
<label>Toleransi kesalahan (% dari maksimum tiap total/kriteria)<input type="number" name="tolerance" min="0" max="100" step="0.01" value="0" required></label>
<p>0 berarti kecocokan persis. Kasus keliru bila total atau setidaknya satu kriteria melewati toleransi. Protokol dan pembagian per soal akan dibekukan; aplikasi menyajikan metrik deskriptif, tidak menyatakan kelayakan otomatis.</p><button>Buat dataset evaluasi</button></form></section>
<section class="card"><h2>Dataset Anda</h2>@forelse($datasets as $d)<article><h3><a href="{{ route('evaluations.show',[$assessment,$d]) }}">{{ $d->name }}</a></h3><p>{{ $d->data_kind }} · {{ $d->status }}</p></article>
@empty<p>Belum ada dataset. Siapkan acuan manusia dan rencana pengujian terlebih dahulu.</p>
@endforelse{{ $datasets->links() }}</section>
@endsection

