# M03A deployment — 21 Oktober 2026

- Target: https://math.speakverse.id (cPanel, PHP 8.4.26, MariaDB).
- Application commit: `3835e9bc33bfab4dd4c58faf53c4eaaa691cc9e3`.
- [CI 39510301948](https://github.com/arisbudianto/AgentMath/actions/runs/39510301948): PHP 8.3 dan 8.4 success; masing-masing menjalankan SQLite/MariaDB, pemeriksaan sintaks, views dan routes.
- Lokal PHP 8.5.11: 62 tests / 538 assertions PASS; Composer manifest valid; sintaks, Blade, routes PASS. Upgrade terisolasi M00 → M03A mempertahankan akun, asesmen dan kepemilikan lama.
- Browser lokal: navigasi dari asesmen ke jawaban foto, draf manual, pemeriksaan dan konfirmasi dengan foto sintetis PASS. Tidak menggunakan data siswa. Bukti lokal `docs/m03a-live.png` diabaikan Git.

## Backup dan penerapan

Backup privat dibuat sebelum maintenance di `/home/speakver/agentmath-backups/m03a-3835e9b/`:

- `database.sql` (~16 KiB), konsisten melalui single transaction.
- `code.tar.gz` (~120 KiB), termasuk `.env` dan penyimpanan aplikasi; mengecualikan vendor, log dan sesi.
- `migration-preview.txt`, `smoke.php`, `smoke.txt` berisi pratinjau/mekanisme/hasil pemeriksaan dengan fixture sintetis.

Folder backup mode 700; SQL dan arsip mode 600, di luar document root. File sementara kredensial dump dihapus setelah backup. Jangan menyalin arsip ke area publik atau GitHub.

Source checkout `/home/speakver/agentmath-m00` dipasang pada commit aplikasi yang lulus CI. `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader` dan platform requirements PASS. Pratinjau migrasi hanya menambahkan tabel transkripsi dan constraint terkait. Aplikasi memasuki maintenance, rsync kode/vendor/public dengan pengecualian `.env`, storage, cache, konfigurasi web dan `.well-known`, lalu migrasi, config cache, view cache dan kembali live.

Migrasi `2026_10_21_040000_create_answer_transcriptions_table`: **batch 5 Ran**. M00–M03 tetap Ran. Perbandingan byte `.env` dengan backup: **UNCHANGED**, termasuk APP_KEY; tidak ada aktivasi AI. Source `app` dan `resources` sama dengan checkout terverifikasi.

## Uji produksi

Runtime nyata memakai transaksi luar dan fixture sintetis, tanpa akun permanen atau permintaan AI:

- PNG asli tersimpan privat dan byte hasil streaming identik.
- Draf/replay idempotent; draf tidak menghasilkan readyText.
- Konfirmasi tanpa pemeriksaan ditolak; konfirmasi eksplisit menghasilkan teks `0`, waktu konfirmasi dan versi baru.
- Riwayat transkripsi menolak perubahan melalui model.
- Halaman transkripsi dan foto: pemilik HTTP 200; pengajar lain HTTP 403.
- Perubahan soal membatalkan readiness; marker tidak terbaca dan penyuntingan foto historis ditolak; revisi jawaban terkini digunakan.
- Semua fixture database di-rollback, jumlah baris asli dipertahankan dan foto/file sementara dihapus.
- `AI_ENABLED=false`, `AI_TRANSCRIPTION_ENABLED=false`; tidak ada panggilan eksternal. Kunci/model tetap belum lengkap.
- HTTPS `/health` 200, `/login` 200, `/.env` 403; TLS verification result 0. Browser publik kembali menampilkan login normal.
- `.env` mode 600, folder foto privat mode 700.

Belum diuji: akurasi layanan AI vision nyata, pemakaian berbayar, dan login browser produksi menggunakan akun pengguna. Pengujian browser alur transkripsi dilakukan lokal; runtime, database, views berotorisasi dan penyimpanan diperiksa langsung di produksi.

## Pemulihan bila diperlukan

Utamakan pemulihan kode M03 dengan mempertahankan tabel transkripsi tambahan agar riwayat baru tidak hilang. Aktifkan maintenance dan gunakan arsip/source versi sebelum rilis, pasang dependensi dari lockfile, pertahankan `.env`/APP_KEY/storage, bangun ulang cache lalu uji sebelum up. Restore SQL penuh hanya setelah menilai data baru sejak backup dan membuat backup terkini; jangan rollback/drop tabel secara otomatis. Dokumen ini tidak menjalankan pemulihan.
