# M04 deployment — 21 Oktober 2026

- Target https://math.speakverse.id, PHP CLI 8.4.26 (pcntl tersedia), MariaDB.
- Application commit `4b9c658f5a3de25bf75499e08f1d3ed575fcced7`.
- [CI 39531187707](https://github.com/arisbudianto/AgentMath/actions/runs/39531187707): PHP 8.3 dan 8.4 success, masing-masing SQLite/MariaDB, sintaks, manifest, views dan routes.
- Lokal PHP 8.5.11: **72 tests / 634 assertions PASS**. Upgrade M00 → M04 terisolasi mempertahankan akun/asesmen/kepemilikan lama. Browser lokal: antrekan foto terkonfirmasi, worker nyata, checkpoint/peristiwa, pembatalan dengan riwayat utuh dan proses baru PASS. Data demo sintetis; `docs/m04-live.png` diabaikan Git.

## Backup dan rilis

Backup sebelum maintenance: `/home/speakver/agentmath-backups/m04-4b9c658/`, folder mode 700. `database.sql` (~18 KiB) dan `code.tar.gz` (~130 KiB) mode 600, termasuk environment/storage tetapi tanpa vendor/log/sesi. File kredensial dump sementara sudah dihapus. Backup tetap di luar document root dan GitHub.

Source `/home/speakver/agentmath-m00` checkout commit aplikasi yang lulus CI, Composer install produksi dari lockfile dan platform requirements PASS. Pratinjau hanya menambah lima tabel: grading_runs, grading_attempts, grading_events, agentmath_jobs, failed_jobs beserta constraint. Tidak ada DROP/TRUNCATE/DELETE data; frasa ON DELETE RESTRICT adalah constraint referensial.

Deployment menggunakan OS lock `/home/speakver/.agentmath-grading.lock`, maintenance, rsync kode/vendor/public dengan pengecualian `.env`, storage, bootstrap/cache, konfigurasi web dan `.well-known`. Migrasi, config/view cache, kemudian up semuanya exit 0. M04 migration **batch 6 Ran**; migrasi sebelumnya tetap utuh. Perbandingan byte `.env` dengan backup **UNCHANGED**, termasuk APP_KEY. `app`/`resources` identik dengan checkout aplikasi.

## Worker dan cron

Satu cron baru bertanda `# AgentMath-M04`, setiap menit:

```cron
* * * * * /usr/bin/flock -n /home/speakver/.agentmath-grading.lock /bin/sh -c 'umask 077; cd /home/speakver/math.speakverse.id && /usr/local/bin/php artisan agentmath:work > /home/speakver/agentmath-grading-worker.log 2>&1' # AgentMath-M04
```

Dua jadwal lama dipertahankan dan dibandingkan ulang: **OLD_CRON_UNCHANGED=YES**, **M04_ENTRIES=1**. Wrapper `crontab -l` hosting menambahkan pesan backup ke stdout; pesan tersebut disaring hanya pada prefix pesan wrapper yang teramati, bukan mengubah jadwal. Pemasangan pertama ditolak akibat pesan itu, kemudian diperbaiki dan diverifikasi melalui pembacaan ulang crontab. Backup mentah `crontab-before.txt`; versi bersih untuk pemulihan `crontab-restore.txt`; jadwal baru `crontab-new.txt`, seluruhnya privat.

Cron otomatis benar-benar berjalan: worker heartbeat `2026-10-21T02:13:02+00:00` (09:13:02 WIB), log mtime sama, diperiksa pada 09:13:29 WIB. Ini tick cron setelah smoke, bukan command worker manual. Log worker mode 600, di luar document root, ditimpa per tick sehingga tidak tumbuh tanpa batas. Riwayat proses bisnis tetap di database. Default `QUEUE_CONNECTION=sync` lama tidak diubah: proses M04 selalu memakai koneksi database khusus `grading`.

**Deployment berikutnya wajib mengambil lock OS yang sama sebelum maintenance/rsync**, lalu melepasnya setelah up. Ini menghindari worker aktif memuat campuran kode dua versi. Lock cache aplikasi menjadi lapisan tambahan, bukan pengganti lock OS deployment.

## Smoke produksi

Fixture sintetis dibuat dalam transaksi luar, tanpa akun permanen/API call:

- Pembuatan proses dan antrean database atomik; UUID replay menghasilkan satu run/satu delivery; payload job hanya ID/generation dan snapshot config tanpa API key.
- Worker database nyata melalui command M04 menyelesaikan preflight, menyimpan output tervalidasi/checkpoint/peristiwa, mengosongkan delivery dan berhenti di awaiting_agent/marker_a.
- Agent belum tersedia tidak dapat dipaksa retry dan tidak menghasilkan skor nol.
- Pemilik membuka daftar/detail proses HTTP 200; pengajar lain HTTP 403.
- Cancel mempertahankan riwayat; lease expired ditandai worker_interrupted, manual resume melanjutkan tahap belum berhasil dengan nomor percobaan baru.
- Perubahan sumber soal membuat proses queued stale; snapshot asli utuh.
- Seluruh fixture dan queued jobs di-rollback, jumlah baris asli dipertahankan. Pemeriksaan akhir: users=1, grading_runs=0, agentmath_jobs=0, failed_jobs=0.
- AI dan AI transcription tetap false. Marker/arbitrase/confidence belum ada dan tidak diuji sebagai layanan nyata; ini cakupan M05.
- HTTPS `/health` dan `/login` 200; `/.env` 403; rute grading tanpa login 302. TLS verification result 0. `.env` dan log worker mode 600; folder backup 700; kredensial dump sementara tidak tersisa.

Pengujian browser alur pengajar dilakukan lokal. Tidak memakai kredensial akun aplikasi produksi. Runtime produksi, database, worker, halaman berotorisasi dan cron diperiksa langsung dengan fixture transaksi/heartbeat.

## Pemulihan

Sebelum mengembalikan kode, ambil lock OS worker, buat backup terkini dan hentikan/tahan cron M04 tanpa menyentuh dua jadwal lama. Snapshot `crontab-restore.txt` mengembalikan jadwal sebelum M04 hanya jika tidak ada perubahan jadwal lain setelah rilis; jika ada, hapus hanya baris bertanda AgentMath-M04 sambil mempertahankan perubahan baru. Periksa pembacaan ulang karena wrapper hosting menambahkan pesan.

Kembalikan kode M03A/dependensi dari lockfile, pertahankan `.env`/APP_KEY/storage serta tabel proses/riwayat tambahan, bangun cache dan uji sebelum up. Job dari versi baru tidak boleh dijalankan oleh kode lama; antrean grading harus ditahan. Restore SQL penuh hanya setelah menilai data baru sejak backup; jangan drop tabel proses/riwayat otomatis. Pemulihan tidak dijalankan dalam rilis ini.
