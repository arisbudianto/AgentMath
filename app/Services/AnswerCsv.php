<?php
namespace App\Services;

use App\Models\Assessment;
use Illuminate\Validation\ValidationException;

class AnswerCsv
{
    public const HEADERS=['participant_code','question_number','answer_text'];

    public function parse(string $csv): array
    {
        if (strlen($csv)>2097152 || !mb_check_encoding($csv,'UTF-8') || str_contains($csv,"\0")) $this->fail('CSV maksimal 2 MiB, harus UTF-8 dan tidak mengandung NUL.');
        $csv=preg_replace('/\A\xEF\xBB\xBF/','',$csv);
        $first=strtok($csv,"\r\n"); $delimiter=null;
        foreach ([',',';'] as $candidate) {
            $headers=array_map(fn($v)=>strtolower(trim($v)),str_getcsv($first ?: '',$candidate,'"',''));
            $sorted=$headers; sort($sorted); $expected=self::HEADERS; sort($expected);
            if ($sorted===$expected) { $delimiter=$candidate; break; }
        }
        if (!$delimiter) $this->fail('Header CSV harus tepat participant_code, question_number, answer_text. Gunakan pemisah koma atau titik koma.');
        $this->syntax($csv,$delimiter);
        $stream=fopen('php://temp','r+'); fwrite($stream,$csv); rewind($stream);
        $headers=array_map(fn($v)=>strtolower(trim($v)),fgetcsv($stream,0,$delimiter,'"',''));
        $rows=[];
        try {
            while (($fields=fgetcsv($stream,0,$delimiter,'"','')) !== false) {
                if (count($fields)===1 && trim($fields[0] ?? '')==='') continue;
                if (count($rows)>=500) $this->fail('Maksimal 500 jawaban per impor CSV.');
                $row=['record'=>count($rows)+1,'participant_code'=>'','question_number'=>'','answer_text'=>'','errors'=>[]];
                if (count($fields)!==3) { $row['errors'][]='Jumlah kolom harus tiga.'; }
                else foreach ($headers as $i=>$header) $row[$header]=$fields[$i] ?? '';
                $row['participant_code']=strtoupper(trim($row['participant_code']));
                $row['question_number']=trim($row['question_number']);
                $row['answer_text']=str_replace(["\r\n","\r"],"\n",$row['answer_text']);
                $rows[]=$row;
            }
        } finally { fclose($stream); }
        if (!$rows) $this->fail('CSV tidak memuat jawaban.');
        return $rows;
    }

    public function resolve(Assessment $assessment, array $rows, bool $lock = false): array
    {
        app(AnswerIntake::class)->classroom($assessment);
        $participants=$assessment->classroom->participants(); $questions=$assessment->questions();
        if ($lock) { $participants->lockForUpdate(); $questions->lockForUpdate(); }
        $people=$participants->get()->keyBy('code'); $items=$questions->get()->keyBy('number');
        $existing=$assessment->answers()->get()->keyBy(fn($answer)=>$answer->participant_id.':'.$answer->question_id);
        $seen=[];
        foreach ($rows as $i=>&$row) {
            $row['errors']=$row['errors'] ?? [];
            $p=$people->get($row['participant_code']);
            $number=preg_match('/\A\d{1,4}\z/',$row['question_number'])?(int)$row['question_number']:0;
            $q=$number>0?$items->get($number):null;
            if (!$p) $row['errors'][]='Kode peserta tidak ditemukan pada kelas asesmen.';
            if (!$q) $row['errors'][]='Nomor soal tidak ditemukan pada asesmen.';
            if (trim($row['answer_text'])==='' || mb_strlen($row['answer_text'])>20000) $row['errors'][]='Jawaban wajib diisi, maksimal 20.000 karakter.';
            $row['participant_id']=$p?->id; $row['question_id']=$q?->id;
            $q?->setRelation('assessment',$assessment);
            $row['source_hash']=$q?RubricValidator::sourceHash($q->rubricSource()):null;
            $pair=$row['participant_code'].':'.$number;
            if (isset($seen[$pair])) { $row['errors'][]='Pasangan peserta–soal berulang dalam CSV.'; $rows[$seen[$pair]]['errors'][]='Pasangan peserta–soal berulang dalam CSV.'; }
            else $seen[$pair]=$i;
            if ($p && $q && $existing->has($p->id.':'.$q->id)) $row['errors'][]='Jawaban sudah ada; gunakan revisi, bukan impor ulang.';
        }
        unset($row);
        return $rows;
    }

    private function syntax(string $csv, string $delimiter): void
    {
        $quoted=false; $closed=false; $start=true; $length=strlen($csv);
        for ($i=0;$i<$length;$i++) {
            $c=$csv[$i];
            if ($quoted) {
                if ($c==='"') { if (($csv[$i+1] ?? '')==='"') $i++; else { $quoted=false; $closed=true; } }
                continue;
            }
            if ($c===$delimiter || $c==="\n" || $c==="\r") { $start=true; $closed=false; continue; }
            if ($closed) $this->fail('Format tanda kutip CSV tidak valid. Ekspor ulang dengan CSV UTF-8.');
            if ($c==='"') { if (!$start) $this->fail('Tanda kutip di dalam kolom harus ditulis sebagai dua tanda kutip pada kolom berpetik.'); $quoted=true; }
            $start=false;
        }
        if ($quoted) $this->fail('CSV memiliki tanda kutip yang belum ditutup.');
    }

    private function fail(string $message): never { throw ValidationException::withMessages(['csv_file'=>$message]); }
}
