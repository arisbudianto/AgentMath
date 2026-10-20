<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Participant extends Model
{
    protected $fillable = ['code'];
    public function classroom() { return $this->belongsTo(Classroom::class); }
}
