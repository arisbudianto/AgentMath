<?php
namespace App\Services;

class GradingResultValidator
{
    public static function object(array $properties): array
    {
        return ['type'=>'object','properties'=>$properties,'required'=>array_keys($properties),'additionalProperties'=>false];
    }
    public static function listOf(array $item): array { return ['type'=>'array','items'=>$item]; }
    // Enforce the same shape locally even when the gateway ignores strict format.
    public function shape(mixed $data,array $schema): void
    {
        $ok=match($schema['type']) {
            'object'=>is_array($data) && !array_is_list($data),
            'array'=>is_array($data) && array_is_list($data),
            'string'=>is_string($data) && mb_strlen($data)<=20000,
            'boolean'=>is_bool($data),
            'number'=>is_int($data) || (is_float($data) && is_finite($data)),
            default=>false,
        };
        if(!$ok || (isset($schema['enum']) && !in_array($data,$schema['enum'],true))) $this->fail();
        if($schema['type']==='object') {
            $keys=array_keys($data); $expected=array_keys($schema['properties']); sort($keys); sort($expected);
            if($keys!==$expected) $this->fail();
            foreach($schema['properties'] as $key=>$child) $this->shape($data[$key],$child);
        }
        if($schema['type']==='array') {
            if(count($data)>40) $this->fail();
            foreach($data as $item) $this->shape($item,$schema['items']);
        }
    }
    public function markerSchema(): array
    {
        $s=['type'=>'string'];
        return self::object(['criteria'=>self::listOf(self::object([
            'criterion_id'=>$s,'score'=>['type'=>'number'],'evidence'=>self::listOf($s),
            'missing_evidence'=>['type'=>'boolean'],'reason'=>$s,'rule_ids'=>self::listOf($s),
        ]))]);
    }
    public function marker(array $data,array $input): array
    {
        $this->shape($data,$this->markerSchema());
        $criteria=$input['rubric']['criteria'];
        if(count($data['criteria'])!==count($criteria)) $this->fail();
        $rows=[]; $total=0;
        foreach($data['criteria'] as $row) {
            $id=$row['criterion_id'];
            $criterion=collect($criteria)->firstWhere('criterion_id',$id);
            if(!$criterion || isset($rows[$id]) || trim($row['reason'])==='') $this->fail();
            try { $points=RubricValidator::cents($row['score']); } catch(\Throwable) { $this->fail(); }
            if($points>RubricValidator::cents($criterion['max_score'])) $this->fail();
            if(!$row['rule_ids'] || count(array_unique($row['rule_ids']))!==count($row['rule_ids'])) $this->fail();
            foreach($row['rule_ids'] as $rule) if(!in_array($rule,$criterion['rule_ids'],true)) $this->fail();
            if($row['missing_evidence']!==($row['evidence']===[]) || ($row['missing_evidence'] && $points!==0)) $this->fail();
            foreach($row['evidence'] as $quote) if(trim($quote)==='' || !str_contains($input['answer_text'],$quote)) $this->fail();
            if(count(array_unique($row['evidence']))!==count($row['evidence'])) $this->fail();
            $row['score']=number_format($points/100,2,'.',''); $rows[$id]=$row; $total+=$points;
        }
        return ['criteria'=>array_map(fn($c)=>$rows[$c['criterion_id']],$criteria),'total_score'=>number_format($total/100,2,'.','')];
    }
    public function differences(array $a,array $b): array
    {
        $differences=[];
        foreach($a['criteria'] as $i=>$row) if($row['criterion_id']!==$b['criteria'][$i]['criterion_id'] || RubricValidator::cents($row['score'])!==RubricValidator::cents($b['criteria'][$i]['score'])) $differences[]=$row['criterion_id'];
        return $differences;
    }
    public function arbitrationSchema(): array
    {
        return self::object(['selected_marker'=>['type'=>'string','enum'=>['marker_a','marker_b']],
            'decisions'=>self::listOf(self::object(['criterion_id'=>['type'=>'string'],'reason'=>['type'=>'string'],
                'rule_ids'=>self::listOf(['type'=>'string'])]))]);
    }
    public function arbitration(array $data,array $input,array $differences): array
    {
        $this->shape($data,$this->arbitrationSchema()); $seen=[];
        foreach($data['decisions'] as $row) {
            $c=collect($input['rubric']['criteria'])->firstWhere('criterion_id',$row['criterion_id']);
            if(!$c || !in_array($row['criterion_id'],$differences,true) || in_array($row['criterion_id'],$seen,true) || trim($row['reason'])==='' || !$row['rule_ids']) $this->fail();
            foreach($row['rule_ids'] as $rule) if(!in_array($rule,$c['rule_ids'],true)) $this->fail();
            if(count(array_unique($row['rule_ids']))!==count($row['rule_ids'])) $this->fail();
            $seen[]=$row['criterion_id'];
        }
        sort($seen); sort($differences); if($seen!==$differences) $this->fail();
        return $data;
    }
    public function confidenceSchema(): array
    {
        return self::object(['label'=>['type'=>'string','enum'=>['High','Low']],'reason'=>['type'=>'string'],
            'concerns'=>self::listOf(self::object(['criterion_id'=>['type'=>'string'],'reason'=>['type'=>'string']]))]);
    }
    public function confidence(array $data,array $input): array
    {
        $this->shape($data,$this->confidenceSchema());
        if(trim($data['reason'])==='' || ($data['label']==='High' && $data['concerns']!==[]) || ($data['label']==='Low' && $data['concerns']===[])) $this->fail();
        $seen=[]; $ids=array_column($input['rubric']['criteria'],'criterion_id');
        foreach($data['concerns'] as $row) {
            if(!in_array($row['criterion_id'],$ids,true) || in_array($row['criterion_id'],$seen,true) || trim($row['reason'])==='') $this->fail();
            $seen[]=$row['criterion_id'];
        }
        return $data;
    }
    private function fail(): never { throw new \UnexpectedValueException('Keluaran penilaian tidak valid.'); }
}
