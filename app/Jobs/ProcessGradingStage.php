<?php
namespace App\Jobs;
use Illuminate\Contracts\Queue\ShouldQueue;
class ProcessGradingStage implements ShouldQueue {
    public $tries=1;
    public $timeout=60;
    public function __construct(public int $runId,public int $generation) {}
    public function handle(\App\Services\GradingOrchestrator $workflow): void { $workflow->process($this->runId,$this->generation); }
}
