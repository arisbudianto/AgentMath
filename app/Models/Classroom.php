<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Classroom extends Model
{
    protected $fillable = ['name', 'grade', 'school_year'];

    protected function casts(): array { return ['grade' => 'integer']; }
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function participants() { return $this->hasMany(Participant::class)->orderBy('code'); }
    public function assessments() { return $this->hasMany(Assessment::class); }
}
