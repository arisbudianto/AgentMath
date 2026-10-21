<?php
namespace App\Models;
class EvalObservation extends EvalRecord {
    protected function casts(): array { return ['version'=>'integer','review_seconds'=>'integer']; }
}
