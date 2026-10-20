# M03 deployment — 21 Oktober 2026

Status: implementasi, GitHub, CI, cPanel dan smoke selesai. Ketikan, impor CSV dan foto privat tersedia; transkripsi foto tetap M03A.

## Release

- Aplikasi: https://math.speakverse.id
- Commit implementasi: `2d3ad3b4411df21441cc646b9f04ef090808824a`.
- CI: https://github.com/arisbudianto/AgentMath/actions/runs/39508247022
- Kedua job PHP 8.3/8.4 completed/success, masing-masing SQLite/MariaDB serta syntax/manifest/views/routes.
- Lokal PHP 8.5.11: 55 tests / 437 assertions PASS.
- Browser lokal: ketikan, CSV multipart → pratinjau → centang konfirmasi → impor, foto multipart yang tampil sebagai gambar, dan revisi dengan alasan PASS.
- Kegagalan unggah lokal awal diselesaikan dengan konfigurasi folder sementara PHP yang dapat ditulis. Runtime lokal untuk uji unggahan memakai upload 6M/post 8M; tidak ada konfigurasi lokal/fixture yang dikirim ke GitHub.
- Upgrade terisolasi M00→M01→M02→M03 mempertahankan akun/asesmen/owner_id sebelumnya.

## cPanel

- PHP 8.4.26 dan Composer platform checks PASS. composer.lock tidak diubah.
- Aplikasi `/home/speakver/math.speakverse.id`, document root `public`.
- Migrasi M03 `2026_10_21_030000_create_answer_intake_tables`: Ran, batch 4. Pratinjau SQL disimpan sebelum deployment; hanya tiga tabel baru dan relasi/index.
- Config/view cache berhasil, aplikasi kembali live.
- .env/APP_KEY identik dengan backup. Storage lama, .htaccess, php.ini dan .user.ini dipertahankan.
- Direktori app/routes/resources/config dan bootstrap/app.php cocok dengan checkout commit implementasi.
- PHP hosting CLI: file_uploads On, upload_max_filesize 16M, post_max_size 32M; tidak ditemukan override batas tersebut pada php.ini/.user.ini public. Folder sementara dapat ditulis dari runtime CLI. Multipart melalui akun produksi belum diuji lewat browser; alur tersebut diuji di browser lokal, sedangkan storage/validasi/streaming produksi diuji langsung pada runtime di bawah.

## Smoke produksi

Fixture sintetis dibuat dalam satu transaksi MariaDB, tanpa akun permanen atau jaringan AI.

- Jawaban teks `0` diterima, retry UUID sama tidak menggandakan, revisi menyimpan isi lama.
- PNG nyata disimpan pada disk privat. File mode 600, direktori private/answers mode 700.
- Pratinjau CSV tidak membuat jawaban. Dua baris dikonfirmasi sekaligus; konfirmasi ulang tidak menggandakan. Target yang sudah memiliki jawaban ditolak.
- HTTP kernel Laravel: daftar/detail jawaban, foto berstatus belum ditranskripsikan, ringkasan batch, serta stream PNG pemilik HTTP 200. Hash byte PNG yang dikirim cocok dengan fixture.
- Pengajar lain tidak dapat membuka jawaban, batch atau foto: HTTP 403.
- Seluruh fixture dibatalkan dan foto uji dihapus. Jumlah akhir users=1, answers=0, answer_revisions=0, answer_import_batches=0; disk answers berisi 0 file. Counter ID dapat memiliki celah setelah rollback uji.
- HTTPS /health dan /login HTTP 200 dengan TLS verification result 0; /.env HTTP 403.
- Tidak ada AI, penilaian atau transkripsi pada M03. Konfigurasi AI tidak diubah. Login admin produksi tidak diulang.

## Backup dan pemulihan

Backup privat: `/home/speakver/agentmath-backups/m03-2d3ad3b/`.

- `database.sql`: dump konsisten sebelum migrasi.
- `code.tar.gz`: kode/config M02 sebelum deployment; vendor/log/sesi dikecualikan.
- `migration-preview.txt`: SQL yang ditinjau.
- `smoke.txt`: bukti uji runtime tanpa kredensial/isi jawaban siswa nyata.
- Dump/arsip permission 600. File kredensial sementara untuk dump telah dihapus. Tidak ada file uji foto yang tersisa.

Rollback kode dapat memakai M02 `72cd0c1`, dengan .env/APP_KEY/storage/foto dan konfigurasi PHP web dipertahankan. Jangan menghapus tabel M03 atau menjalankan migrate:rollback karena menghapus jawaban dan riwayat. Selama rollback ke kode M02, hindari perubahan kelas untuk asesmen yang telah menerima jawaban M03 sampai modul dipulihkan. Pemulihan database memerlukan evaluasi data baru dan persetujuan terpisah.

Pembersihan payload pratinjau kedaluwarsa tersedia melalui perintah agentmath:purge-imports dan otomatis saat pembuatan pratinjau baru. Cron belum dipasang; jawaban tersimpan dan riwayat tidak termasuk pembersihan tersebut.

Commit dokumentasi berikutnya mencatat hasil ini tanpa mengubah kode aplikasi M03.
