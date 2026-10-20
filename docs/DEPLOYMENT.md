# Deployment cPanel

Target: math.speakverse.id. Repository: arisbudianto/AgentMath.
Database dan username yang disediakan: speakver_math. Host/port/grants belum diverifikasi.

## Preflight wajib
- Konfirmasi PHP CLI dan web minimal 8.3 dan ekstensi yang diperlukan.
- Identifikasi checkout, branch/commit, worktree, document root, dan metode deployment.
- Document root harus menunjuk direktori `public`, bukan root repository.
- Pastikan HTTPS aktif sebelum penggunaan login; secure session cookie membutuhkan HTTPS.
- Periksa database ini khusus AgentMath, isi saat ini, migration status, dan backup sesuai risiko.
- Pastikan composer.lock sudah ada, CI berhasil untuk commit target.
- Jangan overwrite .env atau APP_KEY yang sudah dipakai.

## Instalasi pertama (setelah preflight, bukan perintah untuk dijalankan membabi buta)
1. Checkout commit yang lulus CI pada folder aplikasi yang telah diverifikasi.
2. `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader`
3. Buat .env lokal server dari contoh hanya bila belum ada. Isi password melalui server.
4. Buat APP_KEY sekali pada instalasi baru.
5. Review `php artisan migrate --pretend`, lalu jalankan migration additive setelah database/backups diverifikasi.
6. Buat admin dengan `php artisan agentmath:create-admin`.
7. Pastikan storage dan bootstrap/cache dapat ditulis user PHP; jangan gunakan chmod 777.
8. `php artisan config:cache` dan `php artisan view:cache`.

AI_ENABLED=false sampai modul penilaian siap. QUEUE_CONNECTION=sync hanya untuk M00, yang belum memakai pekerjaan AI; worker/cron harus ditetapkan sebelum M04.

## Smoke
- /health menampilkan app=AgentMath.
- /login menampilkan identitas AgentMath dan HTTPS bekerja.
- Login admin, buat dua akun pengajar, logout.
- Masing-masing pengajar membuat asesmen uji; pastikan daftar terisolasi.
- Uji akses langsung GET/PATCH asesmen pengajar lain ditolak.
- Nonaktifkan akun uji, pastikan sesi tidak dapat melanjutkan.
- Jangan catat password/token dalam hasil pemeriksaan.

## Rollback
Simpan commit/release sebelumnya. Kembalikan kode ke release sebelumnya tanpa menimpa .env, storage, atau APP_KEY.
Rollback kode tidak sama dengan rollback database. Jangan menjalankan migrate:rollback pada data nyata tanpa penilaian risiko dan persetujuan khusus karena migration down menghapus tabel.

Status saat penulisan: akses server, PHP, document root, database, HTTPS dan deployment belum diverifikasi.
