<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Competency extends Model
{
    protected $fillable = ['name', 'description'];
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
    public function questions() { return $this->hasMany(Question::class); }
}
