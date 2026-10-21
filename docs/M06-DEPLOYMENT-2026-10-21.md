# M06 — deployment 21 Oktober 2026

Implementasi inti f4f1417bb8106ea57aee77bef585817166fb502d telah dideploy ke https://math.speakverse.id setelah [CI 39563047127](https://github.com/arisbudianto/AgentMath/actions/runs/39563047127) sukses pada PHP 8.3/8.4, SQLite/MariaDB. Lokal: 102 tests / 941 assertions lulus; views/routes valid. Upgrade SQLite berisi data M05 mempertahankan jumlah data lama.

Backup privat sebelum migrasi: /home/speakver/agentmath-backups/m06-f4f1417/ berisi database.sql, code.tar.gz, checksum environment dan laporan. Folder mode 700; file konfigurasi dump sementara dihapus. Backup memuat kredensial, jangan dipublikasikan. Deployment menahan OS lock worker selama maintenance, rsync, migrasi, rebuild cache sampai up. Environment byte-for-byte sama; konfigurasi Dinoiki gpt-5-mini tetap aktif, cache konfigurasi mode 600.

Migrasi grade_reviews berhasil pada batch 8. Tabel baru menyimpan draf/persetujuan append-only, rujukan proses/checkpoint/pembuat, garis riwayat, skor per kriteria dan alasan. Tidak mengubah jawaban, rubrik atau hasil AI lama.

## Verifikasi

- Browser lokal dengan data sintetis: guru mengoreksi usulan 2.00 menjadi 1.50 dengan alasan, menyimpan draf versi 1, lalu mengonfirmasi dan menyetujui versi 2. Dua versi tetap tampil; skor usulan AI tetap 2.00. Screenshot docs/m06-live.png adalah demo lokal, bukan keputusan siswa nyata.
- Runtime produksi: antrean database/worker dengan HTTP mock menghasilkan fixture M05, lalu M06 menjalankan koreksi, replay UUID, penolakan approval tanpa konfirmasi, persetujuan persis draf, stale tab dan model immutable. Tidak ada panggilan AI berbayar tambahan untuk uji M06.
- Foto sintetis privat dan transkripsi confirmed dipakai pada uji server. Halaman pemilik 200, guru lain/siswa 403, halaman keputusan 200 dan streaming foto privat 200. Perubahan soal menahan penyimpanan/persetujuan serta menandai halaman historis.
- Semua fixture database dan job di-rollback; foto sementara dihapus. Jumlah akhir users 3, answers 1, rubrics 0, runs 0, reviews 0, jobs 0. Akun guru/siswa dan jawaban uji pengguna dipertahankan.
- Pemeriksaan akhir: health HTTP 200, TLS verify 0 (valid), checksum environment cocok, config cache 600, cron worker tepat satu entri, heartbeat 2026-10-21 07:39:41 UTC (14:39:41 WIB).
- Browser guru produksi membuka jawaban tersimpan dan bagian Keputusan nilai guru. Tidak mengesahkan rubrik atau nilai pengguna. Label teks jawaban diperjelas agar tidak selalu menyatakan belum dinilai ketika keputusan guru sudah ada.

Commit deployment terbaru (termasuk penyempurnaan label dan laporan) dicatat server pada deployed-commits.txt di folder backup. Pengujian CI dan deployment mengikuti alur GitHub → CI → cPanel. Nilai tidak dipublikasikan ke portal siswa; rekap/ekspor merupakan M07.

## Pemulihan

Tahan /home/speakver/.agentmath-grading.lock, masuk maintenance, pulihkan kode versi sebelum M06 dari backup, rebuild config/views, chmod 600 bootstrap/cache/config.php, hidupkan aplikasi dan lepaskan lock. Tabel grade_reviews boleh dipertahankan saat memulihkan kode agar tidak membuang keputusan. Jangan menjalankan migration rollback atau memulihkan database lama di atas keputusan/jawaban baru tanpa pemeriksaan khusus. Jika kegagalan migrasi terjadi sebelum data M06 dibuat, gunakan backup privat dan periksa schema terlebih dahulu.
