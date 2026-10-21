# M12 deployment — 21 Oktober 2026

Application commit: `b146c41e84eaf740628b92b83e635a3e4801f2c9`.
[GitHub CI 39618921334](https://github.com/arisbudianto/AgentMath/actions/runs/39618921334): SUCCESS, PHP 8.3/8.4, SQLite/MariaDB. Lokal: 157 tes / 1.488 assertions, kompilasi view/rute dan populated M11→M12 upgrade PASS.

Backup privat: `/home/speakver/agentmath-backups/m12-b146c41/` berisi database.sql, code.tar.gz, checksum environment, deploy/smoke/final-check logs. Database dan kode diverifikasi tidak kosong. Worker OS lock dipegang selama backup/deploy dan smoke. Migration `2026_10_21_120000_create_research_annotations_table` batch13 berhasil; caches dibangun ulang, config mode600, aplikasi live.

Uji produksi memakai data sintetis dalam transaksi, HTTP AI mock dan preventStrayRequests. Protokol ketat menolak pembekuan tanpa dua manusia/adjudikasi; setelah label dan adjudikasi, manifest valid. Label dengan total sama tetapi kriteria berbeda menghasilkan total exact100%/criterion exact0%. Foto tanpa draf AI menghasilkan AI CER missing/null, teks terkonfirmasi CER0%. Ini nilai fixture, bukan hasil penelitian. Form acuan foto menyembunyikan teks terkonfirmasi sebelum pelabelan. Laporan owner200, guru lain/siswa403; CSV privat tanpa teks/alasan/kode/kredensial. Anotasi/laporan tidak menambah AI call.

Regresi worker M05/baseline terisolasi M08, audit M09, publikasi/cabut M10, serta pengiriman/revisi/foto/replay/tutup M11 PASS. Semua fixture dan job di-rollback, foto privat dihapus. Tidak ada panggilan AI berbayar pada pengujian ini.

Pemeriksaan akhir: users3/answers1/rubrics0/runs0/reviews0/jobs0/datasets0/samples0/tasks0/submissions0/annotations0/publications0/ai_calls0. Environment checksum tetap. Dinoiki/gpt-5-mini, AI dan vision tetap aktif; tarif belum diatur. HTTPS health200, TLS valid. Cron worker tepat satu; heartbeat `2026-10-21T14:42:59+00:00` (21:42:59 WIB). Akun uji yang sudah ada dipertahankan.

Pemulihan jika diperlukan: pegang worker lock, maintenance, pulihkan backup kode/database privat secara terkoordinasi, bangun ulang cache mode600, up lalu health/smoke. Jangan otomatis menjalankan migrate rollback pada data penelitian. Backup memuat konfigurasi privat dan tidak boleh dipublikasikan.

M10, M11 dan M12 selesai secara teknis dan telah diterapkan. Validasi empiris masih memerlukan kasus representatif, dua penilai nyata, acuan independen dan analisis penelitian sesuai protokol.
