# AgentMath

M00–M02: fondasi login, kelas, peserta berkode, paket asesmen dan rubrik berversi Matematika SD berbantuan AI.

M01 menambahkan soal, kunci jawaban, pedoman, skor maksimum, dan pemeriksaan kelengkapan paket. Lihat [cakupan dan penerimaan M01](docs/M01.md).
M02 menambahkan aturan berkutipan sumber, draf manual/AI, kriteria, penyuntingan, pengesahan pengajar dan versi yang dikunci. Lihat [panduan M02](docs/M02.md). AI harus dikonfigurasi pada server; alur manual tidak memerlukan API key.

## Scope terkunci
- Pengguna admin dan pengajar; tidak ada registrasi publik.
- Pengajar hanya dapat melihat dan mengganti judul asesmen miliknya.
- Admin mengelola akun, tanpa akses otomatis ke asesmen.
- Konfigurasi AI hanya melalui environment server; tidak ada panggilan AI pada M00.
- Modul lanjutan: ketikan/CSV, kemudian JPG/PNG satu jawaban per soal, transkripsi dikonfirmasi pengajar.
- Semua nilai akhir wajib disetujui pengajar.

## Runtime
PHP 8.3+, Composer 2, MariaDB/MySQL, ekstensi Laravel standar dan PDO MySQL.
Laravel 13; tidak membutuhkan Node/build frontend untuk M00.

## Setup lokal
```bash
composer install
cp .env.example .env
php artisan key:generate
```
Isi database lokal pada `.env`, lalu jalankan migration pada database kosong yang khusus untuk aplikasi ini:
```bash
php artisan migrate
php artisan agentmath:create-admin
php artisan serve
```
Password admin diminta secara tersembunyi. Tidak ada akun/password bawaan.
Jangan commit `.env`, data siswa, atau API key.

## Verifikasi
```bash
vendor/bin/phpunit
php artisan view:cache
php artisan route:list --except-vendor
```
CI menguji PHP 8.3/8.4 dengan SQLite dan MariaDB, termasuk login gagal/nonaktif, rate limit, peran, isolasi kelas/peserta/soal, validasi paket, dan tidak bocornya API key.
`composer.lock` disimpan dari dependensi yang lulus CI; gunakan `composer install` agar versi konsisten.

## Deployment
Lihat `docs/DEPLOYMENT.md`. Setiap modul: test, push GitHub, CI berhasil, deploy commit yang sama, smoke test.
