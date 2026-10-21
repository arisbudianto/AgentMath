# M11 deployment — 21 Oktober 2026

Application `937f6afb39cca58f8118ffc19c47a0a2872131f6`; [CI39614934595](https://github.com/arisbudianto/AgentMath/actions/runs/39614934595) SUCCESS PHP8.3/8.4 SQLite/MariaDB. Lokal 146 tests/1394 assertions, populated M10→M11 upgrade dan browser guru membuka tugas dengan zona WIB/siswa login/kirim/portal PASS.

Backup privat `/home/speakver/agentmath-backups/m11-937f6af/`: dump database, kode/environment, checksum, deploy/smoke/final-check log/report. Direktori700, umask077, konfigurasi kredensial sementara dihapus. Deployment memegang OS worker lock selama maintenance/rsync/migrasi/cache/up; environment/storage/vendor/konfigurasi host dipertahankan. Migrasi dua tabel tugas/receipt batch12; cache600.

Smoke transaksi produksi: rangkaian M05/M08/M09/M10 tetap PASS dengan HTTP mock, siswa terkait mengirim ketikan dan revisi ke dua receipt berbeda, UUID replay tidak menggandakan jawaban, foto privat ber-origin student menunggu konfirmasi transkripsi, halaman tugas tidak membocorkan kunci/pedoman, jawaban guru tidak dapat ditimpa, penutupan menolak kiriman baru. Tidak ada tambahan request AI akibat kiriman siswa. Semua fixture, receipt/tugas, revisi, job dan audit di-rollback; kedua foto sintetis dihapus.

Jumlah akhir users3 answers1 rubrics0 runs0 reviews0 datasets0 samples0 tasks0 submissions0 publications0 aicalls0 jobs0. Akun/data uji pengguna dipertahankan. HTTPS health200/TLS valid, checksum environment unchanged, cache600, satu worker cron; heartbeat `2026-10-21T14:16:49+00:00` (21:16:49 WIB). M11 COMPLETE; lanjut M12 sesuai permintaan pengguna.

Pemulihan: pegang OS lock sebelum maintenance/perubahan, pertahankan environment/storage/jawaban baru. Jangan drop tabel receipt/tugas yang telah berisi histori. Rebuild cache, chmod600 config cache, up dan periksa health/heartbeat. Menutup tugas tidak menghapus salinan soal yang telah dibaca siswa.
