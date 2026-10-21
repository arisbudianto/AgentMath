# M08 deployment — 21 Oktober 2026

Application commit: `4ff3c5605852cb551b396dadf3f82bb720a30a16`.
GitHub CI: [39576854855](https://github.com/arisbudianto/AgentMath/actions/runs/39576854855), SUCCESS untuk PHP 8.3/8.4, SQLite dan MariaDB.

## Verifikasi sebelum deployment

- Lokal PHP 8.5.11: 126 tests / 1178 assertions PASS; syntax PHP, kompilasi view, 13 route M08 PASS.
- Upgrade SQLite berisi data M07: tabel operasional dan jumlah rekaman dipertahankan, tujuh tabel evaluasi tersedia.
- Browser lokal: pendaftaran protokol, penambahan kasus, label sintetis, pembekuan, laporan metrik dan penyimpanan catatan analisis PASS. Template dataset diperbaiki sebelum commit setelah uji browser menemukan parsing direktif inline. Seluruh respons AI lokal disimulasikan.
- Pengujian meliputi isolasi konteks baseline, total sama tetapi kriteria salah, MAE nonnol/normalisasi, retry tiga percobaan, penggantian antrean tertahan, generation lama, pembatalan saat request, lease kedaluwarsa, perubahan konfigurasi, snapshot rusak, acuan berversi, pembekuan dan test reuse.

## Produksi

Target: `https://math.speakverse.id`; aplikasi `/home/speakver/math.speakverse.id`.
Backup privat: `/home/speakver/agentmath-backups/m08-4ff3c56/` (direktori 700, berkas dibuat dengan umask 077): dump database, arsip kode/environment, checksum environment, deploy log, smoke script/report/log dan final-check log. Berkas konfigurasi kredensial database sementara telah dihapus. Jangan publikasikan arsip atau isi environment.

Deployment memegang OS lock `/home/speakver/.agentmath-grading.lock`, pull fast-forward main dengan verifikasi SHA, maintenance, rsync dengan pengecualian environment/storage/vendor/konfigurasi host, migrasi, cache konfigurasi/view, chmod 600 cache konfigurasi, up dan verifikasi checksum environment.

Migrasi `2026_10_21_080000_create_evaluation_tables` selesai pada batch 9. Tidak ada perubahan dependensi, provider, model, API key, APP_KEY, akun uji atau cron.

Smoke runtime menggunakan transaksi database, foto sintetis privat, dan HTTP mock dengan larangan request liar. Worker database M05 lalu worker baseline M08 benar-benar dijalankan. Baseline mengirim tepat satu request simulasi dengan source/rubric/answer_text saja; label manusia sintetis, keluaran multi-agent dan kode peserta tidak diteruskan. Laporan mempertahankan kesalahan kriteria walau total sama; metrik baseline, token dan test reuse sesuai fixture. Akses dataset/acuan/laporan/analisis owner 200; guru lain dan siswa 403. CSV tanpa teks jawaban, kode peserta atau key dan memakai no-store. Nilai operasional tidak berubah.

Semua fixture dan job di-rollback, foto uji dihapus. Hasil akhir: users 3, answers 1, rubrics 0, grading runs 0, grade reviews 0, eval datasets 0, eval samples 0, jobs 0. Akun dan jawaban uji coba yang memang disediakan untuk pengguna dipertahankan.

Health HTTPS 200, verifikasi TLS 0 (valid); checksum environment unchanged; config cache mode 600; satu entri worker cron; heartbeat `2026-10-21T09:28:45+00:00` (16:28:45 WIB). M08 COMPLETE secara fungsional dan deployed. Tidak ada tambahan panggilan AI berbayar. Belum ada dataset/acuan manusia nyata, sehingga belum ada kesimpulan akurasi empiris.

## Pemulihan

Pegang OS worker lock sebelum maintenance/perubahan. Pulihkan kode/environment dan database dari backup privat bila perlu; jangan jalankan rollback migrasi yang sudah berisi data penelitian. Rebuild config/view cache, chmod 600 config cache, up, lepas lock lalu periksa health dan heartbeat. Pertahankan storage, histori dan akun pengguna.
