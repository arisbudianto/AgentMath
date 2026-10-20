# M02 deployment — 21 Oktober 2026

Status: implementasi, GitHub, CI, cPanel dan smoke selesai. Bantuan AI nyata belum diaktifkan/dikonfigurasi; rubrik manual tersedia.

## Release dan pengujian

- URL: https://math.speakverse.id
- Commit implementasi: `26d2de3bfde131842e73b3dc96bffeff32460191`.
- CI: https://github.com/arisbudianto/AgentMath/actions/runs/39502874052
- Job PHP 8.3 dan 8.4 completed/success; masing-masing menjalankan SQLite, MariaDB, syntax, manifest, views dan routes.
- Lokal PHP 8.5.11: 35 tests / 274 assertions PASS. Browser: draf manual, editor dinamis, simpan, sahkan, versi terkunci, salin menjadi versi 2 dan rubrik berlaku versi 1 PASS.
- Upgrade terisolasi M00→M01→M02 mempertahankan akun, asesmen lama dan owner_id.
- A1/A2 diuji menggunakan HTTP fake (termasuk refusal, incomplete, kegagalan HTTP, kutipan palsu, skor salah dan sumber berubah saat proses). Tidak ada pengujian API berbayar/nyata.

## cPanel

- PHP CLI 8.4.26; composer install --no-dev dan platform checks PASS, composer.lock tetap.
- Aplikasi `/home/speakver/math.speakverse.id`; document root `public`.
- Migrasi M02 `2026_10_21_020000_create_rubric_versions_table`: Ran, batch 3.
- Preview SQL ditinjau sebelum deployment. Hanya tabel rubric_versions dengan hubungan ke soal/pengguna dan versi asal yang ditambah.
- Config/view cache berhasil; maintenance mode sudah dimatikan.
- .env dan APP_KEY identik dengan backup sebelum deployment; permission .env 600. Konfigurasi PHP web, .htaccess dan storage dipertahankan.
- Direktori app, routes, resources serta rubric-editor.js cocok dengan checkout commit implementasi.

## Smoke produksi

Fixture sintetis dibuat pada satu transaksi MariaDB dan dibatalkan, tanpa password yang disediakan ke pengguna atau jaringan AI.

- Service nyata: buat draf, retry dengan UUID sama, sunting/simpan, sahkan, penolakan overwrite versi disahkan, salin versi baru beserta parent_id PASS.
- Pemilihan rubrik berlaku mempertahankan versi 1 saat versi 2 masih draf.
- HTTP kernel Laravel: indeks versi, halaman disahkan dan editor draf untuk pemilik HTTP 200; pengajar lain HTTP 403.
- Perubahan soal memblokir pengesahan draf lama; snapshot historis tetap utuh dan tidak ada rubrik berlaku untuk dasar yang berubah.
- Semua fixture dibatalkan; jumlah akhir users=1, classrooms=0, assessments=0, questions=0, rubric_versions=0. Kenaikan counter ID akibat transaksi uji normal.
- HTTPS /health, /login dan /rubric-editor.js HTTP 200; TLS verification result 0. /.env HTTP 403.
- AI_ENABLED=false dan model/key belum lengkap, tidak diubah oleh deployment. Login admin produksi tidak diulang; login pengajar browser lokal dan tes CI PASS.

## Backup dan pemulihan

Lokasi privat: `/home/speakver/agentmath-backups/m02-26d2de3/`.

- `database.sql`: snapshot konsisten MariaDB sebelum migrasi.
- `code.tar.gz`: kode/config M01 sebelum perubahan, tanpa vendor/log/sesi.
- `migration-preview.txt`: SQL pratinjau.
- `smoke.txt`: hasil uji runtime; tidak mengandung kredensial.
- File kredensial sementara untuk dump telah dihapus; arsip/dump permission 600, di luar document root.

Untuk rollback kode, gunakan commit M01 `e2ce53d`, pertahankan .env/APP_KEY/storage dan konfigurasi PHP web, lalu bangun ulang cache. Tabel rubric_versions dapat dipertahankan saat M01 berjalan. Jangan migrate:rollback karena menghapus seluruh riwayat rubrik. Pemulihan database memerlukan evaluasi data baru dan persetujuan terpisah.

Commit dokumentasi berikutnya mencatat hasil ini tanpa mengubah kode aplikasi M02.
