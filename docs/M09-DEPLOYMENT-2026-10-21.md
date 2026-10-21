# M09 deployment — 21 Oktober 2026

Application commit: `7016279bb9bcb33e586b4c0a501af2695f500ff4`.
GitHub CI: [39608167864](https://github.com/arisbudianto/AgentMath/actions/runs/39608167864), SUCCESS PHP 8.3/8.4 dengan SQLite dan MariaDB.

## Verifikasi lokal

- PHP 8.5.11: 134 tests / 1281 assertions PASS; syntax PHP, view cache dan dua route audit PASS.
- Pengujian mencakup worker M05 nyata dengan HTTP mock, audit baseline M08, kegagalan A1/A2 tanpa draf tersimpan, transkripsi foto/replay, respons HTTP gagal, output domain ditolak, retry baru, transport failure, token invalid/missing, cache tidak diketahui, snapshot tarif, pending interruption, akses owner/guru lain/admin/siswa, filter proses/tanggal/jenis, ringkasan seluruh halaman dan CSV privat.
- Upgrade SQLite berisi data M08 mempertahankan seluruh jumlah rekaman operasional/evaluasi. Tabel `ai_calls` ditambahkan tanpa backfill metadata yang hilang.
- Browser lokal: tautan asesmen/audit, filter satu proses gagal, estimasi dengan tarif DEMO_SYNTHETIC, serta CSV terunduh dan diperiksa PASS. Semua AI dan harga yang digunakan pada verifikasi merupakan fixture sintetis, bukan pemakaian/tagihan nyata.

## Deployment produksi

Target `https://math.speakverse.id`, aplikasi `/home/speakver/math.speakverse.id`.
Backup privat `/home/speakver/agentmath-backups/m09-7016279/`: dump database, arsip kode/environment, checksum environment, log deployment, smoke script/report/log, final-check script/log. Direktori 700 dan berkas dibuat dengan umask 077. Konfigurasi kredensial dump sementara dihapus setelah backup; arsip tidak boleh dipublikasikan.

OS lock `/home/speakver/.agentmath-grading.lock` dipegang selama backup dan selama maintenance/rsync/migrasi/cache/up. Source dipull fast-forward dan SHA diverifikasi. Environment, storage, vendor, cache serta konfigurasi public host dikecualikan sesuai workflow proyek. Migrasi `2026_10_21_090000_create_ai_calls_table` selesai batch 10; cache konfigurasi dan view dibangun, config cache dikembalikan ke mode 600.

Environment server tidak diubah. Dinoiki/gpt-5-mini, AI dan vision tetap aktif. Tarif belum dikonfigurasi; estimasi nyata akan tetap belum diketahui sampai operator mengisi tarif akun yang terverifikasi sesuai docs/M09.md. Tidak ada harga yang diasumsikan, perubahan kredensial, dependensi atau cron.

## Smoke produksi

Transaksi database dengan foto sintetis privat dan HTTP mock yang melarang request liar. Worker database M05 dan baseline M08 dijalankan. Empat panggilan berhasil tercatat (tiga M05, satu baseline); satu panggilan Marker tambahan sengaja gagal HTTP 429 dan tetap tercatat bersama usage yang diketahui. Lima estimasi sesuai tarif sintetis sementara di memori; konfigurasi environment nyata tetap utuh. Baseline tetap terisolasi dari acuan manusia dan keluaran multi-agent.

Audit owner HTTP 200, guru lain dan siswa 403; laporan per proses mencakup tiga panggilan yang sesuai. CSV berfilter rejected berisi status 429 tanpa isi respons provider, jawaban, kode peserta atau API key; no-store. Pemeriksaan M08 atas pasangan, metrik, source gates dan CSV juga PASS. Tidak ada panggilan AI berbayar tambahan.

Semua fixture, panggilan audit dan job di-rollback; foto uji dihapus. Jumlah akhir: users 3, answers 1, rubrics 0, runs 0, reviews 0, eval datasets 0, eval samples 0, ai calls 0, jobs 0. Akun dan data uji coba pengguna dipertahankan.

Health HTTPS 200/TLS valid (verify result 0); checksum `.env` unchanged; config cache 600; worker cron satu entri, heartbeat `2026-10-21T13:32:34+00:00` (20:32:34 WIB). M09 COMPLETE dan deployed. Audit baru mencatat penggunaan berikutnya; nol baris setelah rollback tidak berarti tidak pernah ada pemakaian sebelum M09.

## Pemulihan

Pegang OS worker lock sebelum maintenance atau pergantian kode. Untuk kembali ke kode sebelum M09, pulihkan arsip kode tanpa menimpa data/environment/storage terbaru; tabel audit dapat dipertahankan. Jangan drop/rollback tabel yang telah berisi catatan. Bila perlu pemulihan database, evaluasi data baru terlebih dahulu. Rebuild cache, chmod 600 config cache, up, lepas lock lalu periksa health/cron.
