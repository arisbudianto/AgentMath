<?php
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;
Artisan::command('agentmath:create-admin', function () {
    $name = $this->ask('Nama admin');
    $email = strtolower(trim((string) $this->ask('Email admin')));
    $password = $this->secret('Password (minimal 12 karakter, huruf dan angka)');
    $confirmation = $this->secret('Ulangi password');
    $validator = Validator::make(['name' => $name, 'email' => $email, 'password' => $password, 'password_confirmation' => $confirmation], [
        'name' => 'required|string|max:120', 'email' => 'required|email|max:255|unique:users,email',
        'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
    ]);
    if ($validator->fails()) { $this->error($validator->errors()->first()); return 1; }
    $user = new User(['name' => $name, 'email' => $email, 'password' => $password]);
    $user->role = 'admin';
    $user->save();
    $this->info('Admin dibuat. Tidak ada password bawaan.');
    return 0;
})->purpose('Membuat admin melalui terminal server');
