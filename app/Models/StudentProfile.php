<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class StudentProfile extends Model {
    protected $guarded=['id'];
    protected static function booted(): void {
        static::updating(fn()=>throw new \LogicException('Tautan akun siswa dikunci; hubungi admin untuk pemeriksaan.'));
        static::deleting(fn()=>throw new \LogicException('Tautan akun siswa tidak dapat dihapus melalui model.'));
    }
    public function user() { return $this->belongsTo(User::class); }
    public function participant() { return $this->belongsTo(Participant::class); }
}
