# Dinoiki — deployment 21 Oktober 2026

Aktif di https://math.speakverse.id: provider dinoiki, model gpt-5-mini, endpoint https://ai.dinoiki.com/v1/responses. AI_ENABLED dan AI_TRANSCRIPTION_ENABLED true; batas output 4000 token. Key Dinoiki yang sudah ada, APP_KEY, database dan environment lain dipertahankan. Environment dan cache konfigurasi bermode 600.

Application commit 254d480a28501d128b3fa6229c1dd4b376c6b6d7; [CI 39538727532](https://github.com/arisbudianto/AgentMath/actions/runs/39538727532) sukses PHP 8.3/8.4 SQLite/MariaDB. Adapter awal f2e25adb2c425f17ca29a3425c4d6a293641bff1, CI 39537739717 sukses. Pengujian lokal: 81 tests / 738 assertions lulus.

Dua percobaan A1 awal memberi HTTP 200 dan JSON tetapi gagal kontrak domain. Validator tetap ketat; instruksi Dinoiki diperkuat dengan schema tepercaya, sementara strict schema pada request dipertahankan. Uji akhir tiga request HTTP 200 lulus: A1, A2 (dua aturan, satu kriteria), dan gambar sintetis (27 karakter, pecahan 1/2 dan 3/4 terdeteksi). Pemakaian tiga request akhir: 1432 input / 2872 output token; tidak mencakup dua percobaan awal. Tidak ada data siswa nyata dikirim. Ini bukti kompatibilitas contoh, bukan evaluasi akurasi.

Foto sintetis privat dihapus; tidak menyimpan rubrik/transkripsi uji. Data tetap tiga user, satu jawaban, nol versi rubrik. Akun dan data sintetis pengguna dipertahankan. Browser guru produksi menampilkan tombol Buat draf dengan AI. Konfirmasi transkripsi dan pengesahan rubrik tetap wajib. M05 belum tersedia; M04 berhenti di awaiting_agent tanpa nilai.

Deployment menahan OS lock worker selama maintenance, rsync dan rebuild cache. Tidak ada migrasi; batch terakhir 7. Heartbeat cron 2026-10-21 03:28:04 UTC; health HTTPS 200 dengan TLS valid. Backup privat sebelum integrasi: /home/speakver/agentmath-backups/dinoiki-f2e25ad/ (database, kode, environment sebelum aktivasi, laporan probe). Jangan publikasikan backup karena memuat kredensial.

Pemulihan: tahan lock /home/speakver/.agentmath-grading.lock, masuk maintenance, pulihkan kode/config dari backup, rebuild cache, chmod 600 bootstrap/cache/config.php, hidupkan aplikasi lalu lepaskan lock. Adapter tidak mengubah database; jangan menimpa jawaban atau akun pengguna.
