@php
$criterion = array_replace(['criterion_id'=>'','description'=>'','evidence'=>'','scoring'=>'','max_score'=>'','rule_ids'=>''], is_array($criterion) ? $criterion : []);
if(is_array($criterion['rule_ids'])) $criterion['rule_ids'] = implode(', ', array_filter($criterion['rule_ids'], 'is_string'));
foreach($criterion as $key=>$value) if(!is_scalar($value)) $criterion[$key] = '';
@endphp
<fieldset class="rubric-row"><legend>Kriteria penilaian</legend><div class="form-grid">
<label>ID kriteria<input name="criteria[{{ $index }}][criterion_id]" required maxlength="20" pattern="C[A-Z0-9_]{1,19}" value="{{ $criterion['criterion_id'] }}"></label>
<label>Skor maksimum kriteria<input type="number" name="criteria[{{ $index }}][max_score]" min="0.01" max="10000" step="0.01" required value="{{ $criterion['max_score'] }}"></label></div>
<label>Deskripsi kriteria<textarea name="criteria[{{ $index }}][description]" required maxlength="4000" rows="2">{{ $criterion['description'] }}</textarea></label>
<label>Bukti jawaban yang diperlukan<textarea name="criteria[{{ $index }}][evidence]" required maxlength="4000" rows="2">{{ $criterion['evidence'] }}</textarea></label>
<label>Aturan skor nol, parsial, dan penuh<textarea name="criteria[{{ $index }}][scoring]" required maxlength="20000" rows="3">{{ $criterion['scoring'] }}</textarea></label>
<label>ID aturan rujukan, dipisahkan koma<input name="criteria[{{ $index }}][rule_ids]" required maxlength="500" value="{{ is_array($criterion['rule_ids']) ? implode(', ', $criterion['rule_ids']) : $criterion['rule_ids'] }}" placeholder="R1, R2"></label><button type="button" class="secondary" data-remove-row>Hapus kriteria dari draf</button></fieldset>
