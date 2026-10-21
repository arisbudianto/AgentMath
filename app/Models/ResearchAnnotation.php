<?php
namespace App\Models;
class ResearchAnnotation extends EvalRecord {
    protected function casts(): array { return ['data'=>'array','version'=>'integer','created_at'=>'datetime']; }
}
