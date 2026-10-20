<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RubricVersion extends Model
{
    protected $guarded = ['id'];
    protected $attributes = ['status'=>'draft', 'revision'=>1];
    protected function casts(): array
    {
        return ['source_snapshot'=>'array', 'rules'=>'array', 'criteria'=>'array', 'ai_metadata'=>'array', 'approved_at'=>'datetime', 'revision'=>'integer', 'version'=>'integer'];
    }

    protected static function booted(): void
    {
        static::updating(function (self $rubric) {
            if ($rubric->getOriginal('status') === 'approved') throw new \LogicException('Rubrik yang disahkan tidak dapat diubah.');
        });
        static::deleting(function (self $rubric) {
            if ($rubric->status === 'approved') throw new \LogicException('Rubrik yang disahkan tidak dapat dihapus.');
        });
    }

    public function question() { return $this->belongsTo(Question::class); }
    public function parent() { return $this->belongsTo(self::class, 'parent_id'); }
    public function approver() { return $this->belongsTo(User::class, 'approved_by'); }
    public function matchesSource(array $source): bool
    {
        return hash_equals($this->source_hash, \App\Services\RubricValidator::sourceHash($source));
    }
}
