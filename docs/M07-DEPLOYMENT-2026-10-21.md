# M07 — deployment 21 Oktober 2026

Application commit 638f3f0b4bf4bb5d5027e689e4b510ca26dad697 dideploy ke https://math.speakverse.id setelah [CI 39569650866](https://github.com/arisbudianto/AgentMath/actions/runs/39569650866) sukses PHP 8.3/8.4, SQLite/MariaDB. Lokal: 112 tests / 1044 assertions lulus, views valid. Browser lokal menampilkan nilai sintetis M06 1.50, peserta tanpa jawaban dengan total kosong, dan berhasil mengunduh CSV; isi unduhan diverifikasi.

Backup privat sebelum deployment: /home/speakver/agentmath-backups/m07-638f3f0/ berisi database.sql, code.tar.gz, checksum environment, deployment log dan smoke report. Folder 700; konfigurasi dump sementara dihapus. Jangan publikasikan backup yang memuat rahasia.

Deployment menahan /home/speakver/.agentmath-grading.lock selama maintenance, rsync, cache rebuild sampai aplikasi hidup. Tidak ada migrasi/dependency baru; schema tetap batch 8 dari M06. Environment byte-for-byte dipertahankan, termasuk Dinoiki gpt-5-mini; config cache mode 600. Commit deployment dan dokumentasi dicatat privat pada deployed-commits.txt.

## Uji server

Fixture sintetis memakai foto privat dan transkripsi confirmed, hasil M05 dengan HTTP mock, serta keputusan guru M06 nol yang disahkan. Tidak ada panggilan AI berbayar tambahan.

- Pemilik membuka rekap HTTP 200; guru lain dan siswa 403.
- Kedua CSV (summary/detail) HTTP 200 berupa attachment UTF-8 BOM dengan private/no-store. Parsel CSV membuktikan nilai approved 0.00 tetap nol, peserta tanpa keputusan menghasilkan kolom kosong, dan proposal AI 2.00 terpisah dari keputusan guru.
- CSV tidak mengandung teks jawaban atau API key. Pengujian suite juga mencakup nama akun/kunci soal, formula injection dan quoting.
- Perubahan soal menandai usulan/keputusan historis; skor tidak masuk nilai berlaku. Rekap tidak menghapus keputusan approved saat draf baru atau proses baru gagal.
- Fixture, review, run dan job seluruhnya di-rollback, foto privat sementara dihapus. Akun serta jawaban uji pengguna dipertahankan.
- Tidak ada nilai dipublikasikan ke portal siswa. Guru membuka rekap dari tautan pada halaman asesmen.

Pemeriksaan kesehatan, checksum environment, mode cache, heartbeat dan jumlah data tercatat pada final-check.txt di folder backup.

Hasil akhir: health HTTP 200/TLS verify 0 (valid), environment checksum cocok, config cache 600, satu cron worker dan heartbeat 2026-10-21 08:35:05 UTC (15:35:05 WIB). Users 3, answers 1, rubrics 0, runs 0, reviews 0, jobs 0; schema batch 8. Screenshot docs/m07-production-live.png menunjukkan pemeriksaan server, bukan nilai siswa nyata.

## Pemulihan

Tahan OS lock worker, masuk maintenance, pulihkan kode sebelum M07 dari backup, rebuild config/views, chmod 600 bootstrap/cache/config.php, hidupkan aplikasi dan lepaskan lock. Tidak perlu rollback schema atau memulihkan database untuk modul baca/ekspor ini. Jangan menimpa keputusan atau jawaban baru yang masuk sesudah backup.
