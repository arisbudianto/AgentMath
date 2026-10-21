<?php
\Illuminate\Support\Facades\Artisan::command('agentmath:work',function() {
    $lock=\Illuminate\Support\Facades\Cache::lock('grading-worker',180);
    if(!$lock->get()) { $this->info('Worker lain masih aktif.'); return 0; }
    try {
        $recovered=app(\App\Services\GradingOrchestrator::class)->recoverExpired();
        $result=$this->call('queue:work',['connection'=>'grading','--queue'=>'grading','--stop-when-empty'=>true,'--max-time'=>45,'--max-jobs'=>100,'--sleep'=>1,'--tries'=>1,'--timeout'=>60]);
        \Illuminate\Support\Facades\Cache::put('grading-worker-finished',now()->toIso8601String(),now()->addDays(7));
        $this->info('Worker selesai; lease terputus ditandai: '.$recovered); return $result;
    } finally { $lock->release(); }
})->purpose('Worker antrean penilaian terbatas untuk cron shared hosting');
\Illuminate\Support\Facades\Artisan::command('agentmath:purge-imports', function () {
    $count=\App\Models\AnswerImportBatch::where('status','preview')->where('expires_at','<=',now())->update(['rows'=>null,'status'=>'expired']);
    $this->info($count.' pratinjau kedaluwarsa dibersihkan. Jawaban tersimpan tidak berubah.');
})->purpose('Membersihkan payload pratinjau CSV yang kedaluwarsa');
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
