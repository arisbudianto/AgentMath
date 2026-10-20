<?php
namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
class UserController
{
    public function index() { return view('users', ['users' => User::orderBy('name')->paginate(20)]); }
    public function store(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:120', 'email' => 'required|email|max:255|unique:users,email', 'role' => 'required|in:teacher,admin', 'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()]]);
        $user = new User(['name' => $data['name'], 'email' => strtolower($data['email']), 'password' => $data['password']]);
        $user->role = $data['role'];
        $user->is_active = true;
        $user->save();
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
