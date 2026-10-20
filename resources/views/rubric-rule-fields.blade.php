@php
$rule = array_replace(['rule_id'=>'','source'=>'scoring_guidelines','quote'=>'','description'=>''], is_array($rule) ? $rule : []);
foreach($rule as $key=>$value) if(!is_scalar($value)) $rule[$key] = '';
@endphp
<fieldset class="rubric-row"><legend>Aturan sumber</legend>
<label>ID aturan<input name="rules[{{ $index }}][rule_id]" required maxlength="20" pattern="R[A-Z0-9_]{1,19}" value="{{ $rule['rule_id'] }}"></label>
<label>Sumber<select name="rules[{{ $index }}][source]" required><option value="scoring_guidelines" @selected($rule['source'] === 'scoring_guidelines')>Pedoman soal</option><option value="instructions" @selected($rule['source'] === 'instructions')>Petunjuk asesmen</option></select></label>
<label>Kutipan persis dari sumber<textarea name="rules[{{ $index }}][quote]" required maxlength="20000" rows="3">{{ $rule['quote'] }}</textarea></label>
<label>Ringkasan aturan<textarea name="rules[{{ $index }}][description]" required maxlength="4000" rows="2">{{ $rule['description'] }}</textarea></label><button type="button" class="secondary" data-remove-row>Hapus aturan dari draf</button></fieldset>
