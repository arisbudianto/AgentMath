# M10 deployment — 21 Oktober 2026

Application `fc35a28051c73c2b0b451a000404e481595fafb8`; [CI 39612275179](https://github.com/arisbudianto/AgentMath/actions/runs/39612275179) SUCCESS PHP8.3/8.4 SQLite/MariaDB. Lokal 138 tests / 1323 assertions, populated upgrade M09→M10 dan browser penerbitan guru PASS.

Backup privat `/home/speakver/agentmath-backups/m10-fc35a28/` memuat dump DB, arsip kode/environment, checksum dan log/report pemeriksaan. Direktori 700, umask077; konfigurasi kredensial dump sementara dihapus. Worker OS lock dipegang selama backup dan maintenance/rsync/migrasi/cache/up. Migrasi publikasi batch11; environment/storage/vendor/konfigurasi host dipertahankan, config cache600.

Smoke transaksi produksi menggunakan foto/akun sintetis dan HTTP mock tanpa request berbayar: M05/baseline/audit tetap PASS, keputusan guru nol valid diterbitkan eksplisit, replay tidak menggandakan publikasi, portal siswa menampilkan pesan/total tanpa alasan internal atau API key, owner200/foreign-student403 dan revoke menghilangkan tampilan. Semua fixture, job, audit, keputusan dan publikasi di-rollback, foto uji dihapus.

Jumlah akhir users3 answers1 rubrics0 runs0 reviews0 datasets0 samples0 publications0 aicalls0 jobs0. Akun/data uji pengguna dipertahankan. Health200 TLS valid, checksum environment unchanged, cache600, satu worker cron dan heartbeat `2026-10-21T13:58:24+00:00` (20:58:24 WIB). M10 COMPLETE. M11 dan M12 masih harus diselesaikan sesuai permintaan pengguna.

Pemulihan: pegang OS lock sebelum maintenance/pergantian kode, pertahankan environment/storage dan catatan baru, jangan drop tabel publikasi yang telah berisi data. Rebuild config/view cache, chmod600 config cache, up lalu periksa health/heartbeat. Pencabutan publikasi tidak dapat menghapus salinan nilai yang sudah dibaca siswa.
