@extends('layout')
@section('content')
<a href="{{ route('assessments.show', $assessment) }}">← {{ $assessment->title }}</a>
<p class="eyebrow">Soal {{ $answer->question->number }} · Jawaban #{{ $answer->id }}</p>
<h1>Diagnosis kesalahan</h1>

@if(!$weak)
<p class="notice">Tidak ada kriteria yang kehilangan nilai pada keputusan yang disahkan, atau jawaban ini belum punya nilai akhir yang disahkan guru. Diagnosis tidak diperlukan.</p>
@else
<section class="card"><h2>Kriteria yang kehilangan nilai</h2>
@foreach($weak as $row)<p><strong>{{ $row['criterion_id'] }}</strong> — skor {{ $row['score'] }} <span class="muted">{{ $row['reason'] ?? '' }}</span></p>@endforeach
</section>

@if($errors->has('diagnosis'))<p class="assessment-status is-draft">{{ $errors->first('diagnosis') }}</p>@endif

@if(!$latest || $latest->status !== 'draft')
<form method="POST" action="{{ route('diagnosis.generate', [$assessment, $answer]) }}">@csrf
<input type="hidden" name="creation_key" value="{{ \Illuminate\Support\Str::uuid() }}">
<button>{{ $latest ? 'Buat diagnosis baru' : 'Buat draf diagnosis dengan AI' }}</button>
</form>
@endif

@if($latest)
<section class="card"><h2>Diagnosis versi {{ $latest->version }} · {{ ['draft'=>'Draf','approved'=>'Disahkan','rejected'=>'Ditolak'][$latest->status] }}</h2>
@foreach($latest->items as $item)
<article><h3>{{ $item['criterion_id'] }} — {{ $categories[$item['category']] ?? $item['category'] }}</h3>
<p>{{ $item['explanation'] }}</p>
<p class="muted">Tindak lanjut: {{ $item['recommended_action'] }}</p></article>
@endforeach
@if($latest->reason)<p class="muted">Catatan guru: {{ $latest->reason }}</p>@endif

@if($latest->status === 'draft')
<form method="POST" action="{{ route('diagnosis.decide', [$assessment, $answer, $latest->id]) }}">@csrf
<input type="hidden" name="expected" value="{{ $latest->version }}">
<input type="hidden" name="action" value="approve">
<button>Sahkan diagnosis ini</button>
</form>
<form method="POST" action="{{ route('diagnosis.decide', [$assessment, $answer, $latest->id]) }}">@csrf
<input type="hidden" name="expected" value="{{ $latest->version }}">
<input type="hidden" name="action" value="reject">
<label>Alasan penolakan (opsional)<textarea name="reason" maxlength="2000" rows="2"></textarea></label>
<button class="secondary">Tolak diagnosis ini</button>
</form>
@endif
</section>
@endif
@endif
@endsection
