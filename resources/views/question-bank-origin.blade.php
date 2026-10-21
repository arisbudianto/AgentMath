@if($question->bankSource)
<p class="muted">Salinan bank: {{ $question->bankSource->source_snapshot['book'] }} · {{ $question->bankSource->source_snapshot['edition'] }} · bab {{ $question->bankSource->source_snapshot['chapter'] }} · hlm. {{ implode('–',$question->bankSource->source_snapshot['pages']) }}.
<a href="{{ route('bank.show',$question->bankSource->bank_set_id) }}">Lihat sumber asli</a>
@if(hash('sha256',$question->prompt)!==$question->bankSource->source_snapshot['prompt_hash']) Teks telah diedit setelah penyalinan; jejak sumber awal tetap tersimpan. @endif</p>
@endif
