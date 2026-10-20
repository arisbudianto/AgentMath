# M01 deployment — 21 Oktober 2026

Status: selesai, GitHub → CI → cPanel → smoke.

## Release

- Aplikasi: https://math.speakverse.id
- Commit implementasi: `1a569704dbd4873aa61294787c75f6eef3d6cebd`.
- CI: https://github.com/arisbudianto/AgentMath/actions/runs/39496133473
- Kedua job PHP 8.3 dan 8.4: completed / success. Setiap job menjalankan SQLite dan MariaDB, syntax, manifest, views, routes.
- Lokal: PHP 8.5.11, 22 tests / 173 assertions PASS. Pratinjau browser login pengajar → kelas → peserta → asesmen → soal → Paket lengkap PASS.
- Upgrade M00→M01 pada SQLite terisolasi: akun, asesmen lama, dan owner_id dipertahankan.

## Produksi

- PHP 8.4.26, Composer production platform checks PASS; versi dependensi tetap dari composer.lock.
- Aplikasi `/home/speakver/math.speakverse.id`; document root `/home/speakver/math.speakverse.id/public`.
- Migrasi M00 batch 1 dan M01 batch 2: Ran.
- Preview SQL disimpan sebelum migrasi; hanya penambahan tabel/kolom.
- Config/view cache berhasil, maintenance mode sudah dimatikan.
- File .env identik dengan backup sebelum deployment (termasuk APP_KEY); permission 600.
- Konfigurasi PHP web, .htaccess dan storage dipertahankan.
- Perbandingan direktori app, routes, resources dengan checkout commit implementasi: identik.

## Uji fungsi

- HTTPS /health dan /login: HTTP 200, TLS verification result 0.
- /classrooms dan /assessments tanpa sesi: HTTP 302 ke login; /.env: HTTP 403.
- Smoke melalui HTTP kernel Laravel pada runtime dan MariaDB produksi: pemilik membuka daftar/detail kelas, daftar/detail asesmen, dan editor soal (HTTP 200); pengajar lain ditolak pada kelas dan asesmen (HTTP 403).
- Paket sintetis berisi kelas, peserta, soal, kunci, pedoman, skor 2.50 menghasilkan Paket lengkap.
- Seluruh fixture dibuat dalam transaksi yang dibatalkan; jumlah baris kembali sama: users=1, classrooms=0, participants=0, assessments=0, questions=0. Auto-increment dapat memiliki celah akibat transaksi uji.
- Smoke server menggunakan konteks pengguna terkontrol dalam proses, bukan login dengan password admin. Alur login dan formulir diuji pada browser lokal dan pengujian otomatis CI. Login manual admin pada produksi belum diulang.

## Backup dan pemulihan

Backup privat sebelum deployment: `/home/speakver/agentmath-backups/m01-1a56970/`.

- `database.sql`: dump MariaDB konsisten, permission 600.
- `code.tar.gz`: kode dan konfigurasi M00 sebelum pembaruan, permission 600; vendor/log/sesi tidak disertakan.
- `migration-preview.txt`: SQL M01 yang ditinjau sebelum deployment.
- Berkas kredensial sementara untuk dump sudah dihapus. Backup tetap privat di luar document root.

Jika pemulihan kode diperlukan, kembalikan aplikasi ke commit M00 `5604c2d`, pertahankan .env/APP_KEY/storage dan konfigurasi PHP web, lalu bangun ulang cache. Tabel M01 dapat tetap berada di database saat kode M00 dipakai. Jangan menjalankan migrate:rollback karena menghapus data M01. Pemulihan dump database memerlukan evaluasi data baru dan persetujuan terpisah.

Commit dokumentasi berikutnya dapat memuat catatan ini; tidak ada perubahan kode aplikasi setelah commit implementasi di atas.
