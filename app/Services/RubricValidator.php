<?php

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RubricValidator
{
    public static function sourceHash(array $source): string
    {
        return hash('sha256', json_encode($source, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));
    }

    public static function cents(mixed $value): int
    {
        if (!is_numeric($value) || !preg_match('/\A\d+(?:\.\d{1,2})?\z/', (string) $value) || (float) $value > 10000) {
            throw ValidationException::withMessages(['rubric'=>'Skor harus 0–10.000 dengan paling banyak dua angka desimal.']);
        }
        return (int) round((float) $value * 100);
    }

    public function rules(array $rules, array $source): array
    {
        Validator::make(['rules'=>$rules], [
            'rules'=>'required|array|min:1|max:20', 'rules.*'=>'array:rule_id,source,quote,description',
            'rules.*.rule_id'=>['required','string','regex:/\AR[A-Z0-9_]{1,19}\z/','distinct:strict'],
            'rules.*.source'=>'required|in:scoring_guidelines,instructions',
            'rules.*.quote'=>'required|string|max:20000', 'rules.*.description'=>'required|string|max:4000',
        ])->validate();
        foreach ($rules as $rule) {
            if (!str_contains($source[$rule['source']] ?? '', $rule['quote'])) {
                throw ValidationException::withMessages(['rubric'=>'Kutipan '.$rule['rule_id'].' tidak ditemukan persis dalam sumber pedoman.']);
            }
        }
        return array_values($rules);
    }

    public function validate(array $data, array $source): array
    {
        $data = Validator::make($data, [
            'rules'=>'required|array', 'criteria'=>'required|array|min:1|max:20',
            'criteria.*'=>'array:criterion_id,description,evidence,scoring,max_score,rule_ids',
            'criteria.*.criterion_id'=>['required','string','regex:/\AC[A-Z0-9_]{1,19}\z/','distinct:strict'],
            'criteria.*.description'=>'required|string|max:4000',
            'criteria.*.evidence'=>'required|string|max:4000', 'criteria.*.scoring'=>'required|string|max:20000',
            'criteria.*.max_score'=>'required|numeric|gt:0|lte:10000',
            'criteria.*.rule_ids'=>'required|array|min:1|max:20',
            'criteria.*.rule_ids.*'=>'required|string',
            'grading_policy'=>'required|string|max:20000',
        ])->validate();
        $data['rules'] = $this->rules($data['rules'], $source);
        $ids = array_column($data['rules'], 'rule_id'); $total = 0;
        foreach ($data['criteria'] as &$criterion) {
            if (count(array_unique($criterion['rule_ids'], SORT_STRING)) !== count($criterion['rule_ids'])) {
                throw ValidationException::withMessages(['rubric'=>'Rujukan aturan tidak boleh berulang dalam satu kriteria.']);
            }
            foreach ($criterion['rule_ids'] as $id) {
                if (!in_array($id, $ids, true)) throw ValidationException::withMessages(['rubric'=>'Rujukan aturan '.$id.' tidak tersedia.']);
            }
            $points = self::cents($criterion['max_score']);
            if ($points === 0) throw ValidationException::withMessages(['rubric'=>'Skor setiap kriteria harus lebih dari nol.']);
            $criterion['max_score'] = number_format($points / 100, 2, '.', '');
            $total += $points;
        }
        unset($criterion);
        if ($total !== self::cents($source['max_score'])) {
            throw ValidationException::withMessages(['rubric'=>'Jumlah skor kriteria harus sama dengan skor maksimum soal ('.$source['max_score'].').']);
        }
        $data['criteria'] = array_values($data['criteria']);
        return $data;
    }
}
