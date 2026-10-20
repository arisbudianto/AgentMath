<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Assessment extends Model
{
    protected $fillable = ['title'];
    public function owner() { return $this->belongsTo(User::class, 'owner_id'); }
}
