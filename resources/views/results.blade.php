@extends('layout')
@section('content')
<a href="{{ route('assessments.show',$assessment) }}">← Kembali ke asesmen</a><p class="eyebrow">{{ $assessment->title }}</p><h1>Rekap dan ekspor nilai</h1>
<p class="notice">Rekap memakai peserta kelas dan soal terkini. Usulan AI dipisahkan dari keputusan guru. Keputusan historis tidak masuk nilai akhir; jawaban kosong atau proses gagal tidak dianggap nol. Halaman ini tidak mempublikasikan nilai ke siswa.</p>
<section class="card"><h2>Pilihan rekap</h2><form method="GET" action="{{ route('results.index',$assessment) }}">
<label>Peserta<select name="participant"><option value="">Semua peserta</option>@foreach($allParticipants as $p)<option value="{{ $p->id }}" @selected($report['participant_filter']===$p->id)>{{ $p->code }}</option>@endforeach</select></label>
<label>Soal<select name="question"><option value="">Semua soal</option>@foreach($allQuestions as $q)<option value="{{ $q->id }}" @selected($report['question_filter']===$q->id)>Soal {{ $q->number }}</option>@endforeach</select></label><button>Tampilkan rekap</button></form>
<p>Jumlah soal dan total pada tabel mengikuti pilihan di atas. Maksimal 1000 kombinasi peserta–soal dan 100 soal per rekap/ekspor; ukuran teks sumber juga dibatasi. Gunakan filter untuk paket lebih besar. Ekspor mencakup seluruh peserta dalam pilihan, bukan hanya halaman yang terlihat.</p>
</section>
@if($report['limited'])<p class="error">Pilihan terlalu besar. Pilih peserta atau soal untuk menampilkan dan mengekspor rekap.</p>
@else
<section class="card"><h2>Rekap peserta</h2><p>Waktu rekap {{ \Illuminate\Support\Carbon::parse($report['generated_at'])->timezone(config('app.display_timezone'))->format('d-m-Y H:i:s T') }}. Disahkan/soal menunjukkan kelengkapan. Jumlah disahkan hanya penjumlahan bagian yang sudah berlaku; total akhir tetap kosong sampai semua soal pilihan disahkan.</p>
@if($report['question_filter'])<p class="notice">Filter satu soal aktif; total akhir di sini hanya untuk soal pilihan.</p>@endif
<div style="overflow-x:auto"><table><thead><tr><th>Peserta</th><th>Jawaban/soal</th><th>Usulan AI</th><th>Disahkan/soal</th><th>Draf</th><th>Historis</th><th>Jumlah disahkan</th><th>Total akhir</th><th>Maksimum</th></tr></thead><tbody>
@forelse($rows as $row)<tr><td><a href="{{ route('results.index',[$assessment,'participant'=>$row['participant_id'],'question'=>$report['question_filter']]) }}">{{ $row['participant_code'] }}</a></td><td>{{ $row['answers'] }}/{{ $row['questions'] }}</td><td>{{ $row['proposals'] }}</td><td>{{ $row['approved'] }}/{{ $row['questions'] }}</td><td>{{ $row['drafts'] }}</td><td>{{ $row['historical'] }}</td><td>{{ $row['approved_sum'] }}</td><td>{{ $row['final_total'] ?? '— belum lengkap' }}</td><td>{{ $row['max_score'] }}</td></tr>
@empty<tr><td colspan="9">Belum ada peserta dalam pilihan ini.</td></tr>@endforelse
</tbody></table></div>{{ $rows->links() }}
@if($report['questions']->isEmpty())<p>Belum ada soal. Total akhir belum tersedia.</p>@endif
<p><a class="button" href="{{ route('results.export',[$assessment,'participant'=>$report['participant_filter'],'question'=>$report['question_filter'],'kind'=>'summary']) }}">Unduh rekap peserta CSV</a> <a class="button secondary" href="{{ route('results.export',[$assessment,'participant'=>$report['participant_filter'],'question'=>$report['question_filter'],'kind'=>'detail']) }}">Unduh rincian per soal CSV</a></p>
<p>CSV berisi kode peserta dan skor, tanpa nama, jawaban, foto, kunci soal atau API key. Simpan hasil unduhan secara privat. Kolom final_total/approved_score yang kosong berarti belum ada nilai guru yang berlaku; 0.00 adalah skor nol yang benar-benar disahkan.</p></section>
@if($report['participant_filter'])
<section class="card"><h2>Rincian soal peserta pilihan</h2>
@forelse($report['details'] as $row)<article><h3>Soal {{ $row['question_number'] }} · maksimum {{ $row['max_score'] }}</h3>
<p>{{ $row['answer_status']==='missing'?'Jawaban belum masuk':'Jawaban revisi '.$row['answer_revision'] }} · proses terakhir {{ $row['latest_run_status'] ?? 'belum ada' }}</p>
@if($row['answer_status']==='source_changed')<p class="error">Dasar soal berubah sejak jawaban diterima. Periksa revisi jawaban.</p>@elseif($row['answer_status']==='photo_unconfirmed')<p class="notice">Transkripsi foto terkini belum dikonfirmasi.</p>@endif
<p>{{ \App\Services\ResultReport::PROPOSAL_STATUSES[$row['proposal_status']] }}@if($row['proposal_score']!==null) · {{ $row['proposal_score'] }} · {{ $row['confidence'] }}@endif</p>
<p>{{ \App\Services\ResultReport::GRADE_STATUSES[$row['grade_status']] }}@if($row['approved_score']!==null) · {{ $row['approved_score'] }} · versi {{ $row['approved_version'] }}@endif</p>
@if($row['review_status']==='draft')<p>Draf pemeriksaan versi {{ $row['review_version'] }} belum mengubah nilai disahkan.</p>@endif
@if($row['answer_id'])<a href="{{ route('answers.show',[$assessment,$row['answer_id']]) }}">Buka jawaban dan keputusan</a>@endif
@if($row['proposal_run_id']) · <a href="{{ route('grading.show',[$assessment,$row['proposal_run_id']]) }}">Lihat dasar usulan #{{ $row['proposal_run_id'] }}</a>@endif
@if($row['approved_run_id']) · <a href="{{ route('reviews.show',[$assessment,$row['approved_run_id']]) }}">Riwayat keputusan guru</a>@endif
</article>@empty<p>Belum ada soal dalam pilihan ini.</p>@endforelse</section>
@else<p>Pilih kode peserta pada tabel untuk rincian usulan, proses, dan keputusan per soal.</p>@endif
@endif
@endsection
