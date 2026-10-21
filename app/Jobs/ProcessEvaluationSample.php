<?php
namespace App\Jobs;
use Illuminate\Contracts\Queue\ShouldQueue;
class ProcessEvaluationSample implements ShouldQueue {
    public $tries=1; public $timeout=60;
    public function __construct(public int $sampleId,public int $generation) {}
    public function handle(\App\Services\EvaluationWorkflow $workflow): void { $workflow->process($this->sampleId,$this->generation); }
}
