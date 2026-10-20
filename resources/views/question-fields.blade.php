<div class="form-grid"><label>Nomor soal<input type="number" name="number" required min="1" max="9999" value="{{ old('number', $question?->number ?? $nextNumber ?? 1) }}"></label>
<label>Skor maksimum<input type="number" name="max_score" required min="0.01" max="10000" step="0.01" value="{{ old('max_score', $question?->max_score) }}"></label></div>
<label>Teks soal<textarea name="prompt" required maxlength="20000" rows="4">{{ old('prompt', $question?->prompt) }}</textarea></label>
<label>Kunci jawaban<textarea name="answer_key" required maxlength="20000" rows="3">{{ old('answer_key', $question?->answer_key) }}</textarea></label>
<label>Pedoman penilaian<textarea name="scoring_guidelines" required maxlength="20000" rows="4" aria-describedby="guideline-help">{{ old('scoring_guidelines', $question?->scoring_guidelines) }}</textarea></label>
<p class="muted" id="guideline-help">Jelaskan jawaban yang diterima, syarat nilai penuh, dan aturan nilai parsial. Pedoman ini menjadi dasar penyusunan rubrik pada M02.</p>
