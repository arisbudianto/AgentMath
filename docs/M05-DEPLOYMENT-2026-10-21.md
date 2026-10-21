# M05 — deployment 21 Oktober 2026

Application commit awal eab01b4b4375e2e2c7c66126dd834821149e3b81 telah dipush dan dideploy ke https://math.speakverse.id setelah [CI 39542973588](https://github.com/arisbudianto/AgentMath/actions/runs/39542973588) sukses pada PHP 8.3/8.4, SQLite/MariaDB. Lokal: 92 tests / 839 assertions lulus; views/routes valid.

Backup privat sebelum deployment: /home/speakver/agentmath-backups/m05-eab01b4/ berisi database.sql, code.tar.gz, checksum environment dan laporan. File konfigurasi dump sementara sudah dihapus. Folder backup mode 700; environment serta bootstrap/cache/config.php mode 600. Backup memuat rahasia, jangan diunggah ke Git.

Deployment menahan /home/speakver/.agentmath-grading.lock selama maintenance, rsync, cache rebuild sampai aplikasi hidup kembali. Environment byte-for-byte tetap sama. Tidak ada perubahan dependency, schema atau migrasi; database tetap batch 7. Provider Dinoiki dan model gpt-5-mini yang dipilih pengguna dipertahankan.

## Hasil uji

- Pipeline worker database nyata di cPanel dengan sumber dan jawaban sintetis: lima checkpoint berhasil (preflight, Marker A, Marker B, arbitrase dilewati karena skor kriteria sama, confidence). Usulan 2.00/2.00, pilihan marker_a. High adalah keluaran pemeriksaan AI, bukan probabilitas akurasi.
- Tiga request pada percobaan berhasil: Marker A 748 input / 832 output; Marker B 748 / 677; Confidence 1030 / 462 token. Angka ini hanya untuk percobaan berhasil tersebut.
- Satu percobaan awal HTTP 200 ditahan di Marker A karena invalid_output. Tidak ada skor pengganti, tidak ada auto-retry, dan validator tidak dilonggarkan. Percobaan diagnostik baru kemudian berhasil tanpa perubahan kontrak. Kualitas/reliabilitas keluaran model tetap memerlukan evaluasi M08.
- HTTP runtime pemilik 200 dengan tampilan usulan, pemilik lain 403; perubahan soal setelah selesai menandai usulan historis.
- Semua fixture, akun sementara, hasil dan job uji server di-rollback; jumlah tabel sebelum/sesudah sama. Akun guru/siswa dan jawaban uji pengguna tetap dipertahankan.
- Browser lokal terisolasi: login guru dan tampilan perbandingan Marker, skor, kutipan, label, checkpoint dan riwayat berhasil diperiksa menggunakan respons mock yang dinyatakan sebagai demo sintetis lokal.
- Browser produksi: halaman antrean baru tersedia untuk guru; tidak membuat proses atau mengesahkan rubrik pengguna melalui browser.

Pemeriksaan langsung Arbitrator Dinoiki lulus dengan dua checkpoint sintetis yang sengaja berbeda per kriteria namun total sama. Arbitrator memilih marker_a secara utuh dan memberi rujukan/alasan untuk kedua kriteria. Pemakaian request tersebut 859 input / 2031 output token. Ini uji kontrak Arbitrator, bukan dua penilaian independen baru terhadap jawaban nyata.

Penyempurnaan UI application commit 6b0b1593cb1412ba4e635ee88c66a655e29fc36f telah dideploy setelah [CI 39559362749](https://github.com/arisbudianto/AgentMath/actions/runs/39559362749) sukses. Login guru produksi dan halaman antrean yang menyebut penerima Dinoiki berhasil diperiksa. Pemeriksaan akhir: health HTTP 200, verifikasi TLS 0 (valid), environment checksum cocok, cache konfigurasi 600, migration batch 7, worker cron tepat satu entri, heartbeat 2026-10-21 07:07:04 UTC (14:07:04 WIB). Jumlah akhir: users 3, answers 1, rubrics 0, runs 0, jobs 0. Uji sintetis tidak meninggalkan hasil atau akun tambahan. M05 selesai; persetujuan/koreksi nilai tetap menunggu M06.

## Pemulihan

Tahan OS lock worker yang sama, masuk maintenance, pulihkan kode aplikasi sebelum M05 dari backup, rebuild config/views, beri mode 600 pada cache konfigurasi, lalu hidupkan aplikasi dan lepaskan lock. Tidak perlu memulihkan database untuk adapter/UI saja. Jangan menimpa data pengguna yang bertambah setelah backup. Run yang dibuat sebelum M05 tidak otomatis mendapat kontrak baru; batalkan dan buat ulang jika ingin diproses dengan konfigurasi aktif.
