<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
class UserController
{
    public function index() { return view('users', ['users' => User::orderBy('name')->paginate(20),'participants'=>\App\Models\Participant::whereDoesntHave('studentProfile')->whereHas('classroom.owner',fn($q)=>$q->where('role','teacher')->where('is_active',true))->with('classroom')->latest('id')->limit(200)->get()]); }
    public function store(Request $request)
    {
        if(is_string($request->input('email'))) $request->merge(['email'=>strtolower(trim($request->input('email')))]);
        $data = $request->validate(['name' => 'required|string|max:120', 'email' => 'required|email|max:255|unique:users,email', 'role' => 'required|in:teacher,admin,student', 'participant_id'=>'required_if:role,student|prohibited_unless:role,student|nullable|integer', 'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()]]);
        DB::transaction(function() use($data,$request) {
        $participant=null;
        if($data['role']==='student') {
            $participant=\App\Models\Participant::lockForUpdate()->find($data['participant_id']);
            if(!$participant || $participant->studentProfile()->exists() || !$participant->classroom->owner->is_active || $participant->classroom->owner->role!=='teacher') throw ValidationException::withMessages(['participant_id'=>'Pilih peserta pada kelas pengajar aktif yang belum memiliki akun siswa.']);
        }
        $user = new User(['name' => $data['name'], 'email' => strtolower($data['email']), 'password' => $data['password']]);
        $user->role = $data['role'];
        $user->is_active = true;
        $user->save();
        if($participant) $user->studentProfile()->create(['participant_id'=>$participant->id,'created_by'=>$request->user()->id]);
        });
        return back()->with('status', 'Akun berhasil dibuat.');
    }
    public function disable(Request $request, User $user)
    {
        abort_if($user->id === $request->user()->id, 422, 'Tidak dapat menonaktifkan akun sendiri.');
        $user->is_active = false;
        $user->save();
        return back()->with('status', 'Akun dinonaktifkan.');
    }
}
