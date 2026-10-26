<?php
namespace App\Services;

use App\Models\Assessment;
use Illuminate\Validation\ValidationException;

class QuestionCsv
{
    public const HEADERS=['number','prompt','answer_key','scoring_guidelines','max_score'];

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
        if (!$delimiter) $this->fail('Header CSV harus tepat number, prompt, answer_key, scoring_guidelines, max_score. Gunakan pemisah koma atau titik koma.');
        $this->syntax($csv,$delimiter);
        $stream=fopen('php://temp','r+'); fwrite($stream,$csv); rewind($stream);
        $headers=array_map(fn($v)=>strtolower(trim($v)),fgetcsv($stream,0,$delimiter,'"',''));
        $rows=[];
        try {
            while (($fields=fgetcsv($stream,0,$delimiter,'"','')) !== false) {
                if (count($fields)===1 && trim($fields[0] ?? '')==='') continue;
                if (count($rows)>=500) $this->fail('Maksimal 500 soal per impor CSV.');
                $row=['record'=>count($rows)+1,'number'=>'','prompt'=>'','answer_key'=>'','scoring_guidelines'=>'','max_score'=>'','errors'=>[]];
                if (count($fields)!==5) { $row['errors'][]='Jumlah kolom harus lima.'; }
                else foreach ($headers as $i=>$header) $row[$header]=$fields[$i] ?? '';
                $row['number']=trim($row['number']);
                $row['max_score']=trim($row['max_score']);
                foreach (['prompt','answer_key','scoring_guidelines'] as $field) $row[$field]=str_replace(["\r\n","\r"],"\n",$row[$field]);
                $rows[]=$row;
            }
        } finally { fclose($stream); }
        if (!$rows) $this->fail('CSV tidak memuat soal.');
        return $rows;
    }

    public function resolve(Assessment $assessment, array $rows, bool $lock = false): array
    {
        $questions=$assessment->questions();
        if ($lock) $questions->lockForUpdate();
        $existingNumbers=$questions->get()->pluck('number')->flip();
        $seen=[];
        foreach ($rows as $i=>&$row) {
            $row['errors']=$row['errors'] ?? [];
            $number=preg_match('/\A\d{1,4}\z/',$row['number'])?(int)$row['number']:0;
            if ($number<1 || $number>9999) $row['errors'][]='Nomor soal harus bilangan 1-9999.';
            elseif ($existingNumbers->has($number)) $row['errors'][]='Nomor soal '.$number.' sudah ada pada asesmen ini. Hapus soal lama atau pakai nomor lain.';
            if (isset($seen[$number]) && $number>0) { $row['errors'][]='Nomor soal berulang dalam CSV.'; $rows[$seen[$number]]['errors'][]='Nomor soal berulang dalam CSV.'; }
            elseif ($number>0) $seen[$number]=$i;
            if (trim($row['prompt'])==='' || mb_strlen($row['prompt'])>20000) $row['errors'][]='Teks soal wajib diisi, maksimal 20.000 karakter.';
            if (trim($row['answer_key'])==='' || mb_strlen($row['answer_key'])>20000) $row['errors'][]='Kunci jawaban wajib diisi, maksimal 20.000 karakter.';
            if (trim($row['scoring_guidelines'])==='' || mb_strlen($row['scoring_guidelines'])>20000) $row['errors'][]='Pedoman penilaian wajib diisi, maksimal 20.000 karakter.';
            if (!is_numeric($row['max_score']) || (float)$row['max_score']<=0 || (float)$row['max_score']>10000 || !preg_match('/\A\d+(\.\d{1,2})?\z/',$row['max_score'])) $row['errors'][]='Skor maksimum harus angka lebih dari 0, maksimal 10000, maksimal 2 desimal.';
            $row['number']=$number;
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
