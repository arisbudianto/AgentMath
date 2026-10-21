<?php
namespace App\Services;
// M05 extends this adapter with validated agent outputs. Never simulate scores in production.
class GradingStages
{
    public const PLAN=['preflight','marker_a','marker_b','arbitration','confidence'];
    public function supports(string $stage): bool { return $stage==='preflight'; }
    public function execute(string $stage, array $input, array $previous, array $configuration): array {
        if($stage!=='preflight') throw new \LogicException('Agent belum tersedia.');
        app(RubricValidator::class)->validate($input['rubric'],$input['source']);
        app(AnswerIntake::class)->text($input['answer_text']);
        return ['validated'=>true,'answer_characters'=>mb_strlen($input['answer_text']),'criteria_count'=>count($input['rubric']['criteria']),'schema_version'=>'m04_v1'];
    }
    public function validate(string $stage,array $output): array {
        if($stage!=='preflight' || ($output['validated'] ?? null)!==true || ($output['schema_version'] ?? null)!=='m04_v1' || !is_int($output['answer_characters'] ?? null) || !is_int($output['criteria_count'] ?? null)) throw new \UnexpectedValueException('Keluaran tahap tidak valid.');
        return $output;
    }
}
