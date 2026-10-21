# M13 deployment — 22 Oktober 2026

Application commit `4b82633121ca68f6341d577722ccc754b0e2f8ac`.
[CI39678499902](https://github.com/arisbudianto/AgentMath/actions/runs/39678499902) SUCCESS (PHP8.3/8.4, SQLite/MariaDB). Lokal163 tes/1553 assertions PASS; setelah penambahan tampilan jejak sumber, tes bank/paket asesmen17 tes/182 assertions PASS. Upgrade database lokal berisi data dan impor ulang buku asli PASS. Browser lokal membuka gambar hlm57 dan menyalin soal3+2 ke asesmen kelas1 beserta kunci, pedoman dan jejak sumber PASS.

## Data yang dipasang permanen

1 buku,8 bab,242 halaman materi,86 paket sumber. Paket per bab14/13/12/11/11/9/7/9. Jenis31 latihan,8 evaluasi,39 kegiatan/eksplorasi dan8 proyek. Paket berisi beberapa soal/subsoal, bukan86 soal individual. Seluruh halaman cetak1–242 tersedia pada peta bab. Kunci/pedoman tetap ditinjau guru saat memilih soal untuk asesmen.

Arsip sumber privat16.677.286 bytes; SHA256 `4e547c312eb6c59a96a5ee4b9fd1addf87db21184f46d6e354476692d98df576`. Dikirim lewat cPanel File Upload ke `/home/speakver/agentmath-bank-incoming/`,100% complete dan hash server cocok. Arsip diperiksa dengan unzip test, diekstrak di storage privat, direktori700/berkas600. Tidak ada unggahan isi buku/gambar ke GitHub atau permintaan AI untuk ekstraksi.

## Deployment dan pengujian produksi

Backup privat `/home/speakver/agentmath-backups/m13-4b82633/`: database.sql dan code.tar.gz sebelum perubahan, checksum environment, arsip bank sumber, deploy/smoke/final-check logs. Worker OS lock dipegang selama backup/deploy/smoke. Migration batch14 berhasil. Impor CLI dua kali: hasil identik tanpa duplikasi. Caches dibangun ulang, config600 dan aplikasi live.

Semua86 paket dan sumbernya lolos validasi checksum. Katalog dapat diakses guru aktif; siswa403. Detail dan gambar privat/no-store PASS. Salinan ke asesmen kelas1 dengan jejak sumber dan replay UUID PASS; kelas yang tidak sesuai ditolak. Tidak ada pengesahan rubrik atau pemanggilan AI otomatis. Fixture copy, pengguna/kelas/asesmen/jawaban/job penelitian tambahan di-rollback dan foto privat uji dihapus. Bank buku permanen dipertahankan.

Regresi M05 worker, baseline terisolasi M08, audit M09, publikasi/cabut M10, pengiriman/revisi/foto/tutup M11 serta pasangan manusia/adjudikasi/CER M12 PASS dengan HTTP mock dan preventStrayRequests. Tidak ada AI berbayar.

Browser produksi memakai akun uji guru yang sudah ada: login, katalog86 paket, filter bab8 (9 paket), tampilan diagram gambar asli hlm234 PASS. Kredensial tidak diubah/ditampilkan. Gambar sumber belum otomatis terlampir ke soal siswa; guru menulis deskripsi lengkap atau menyediakan lembar asli.

Pemeriksaan akhir: users3/answers1/rubrics0/runs0/reviews0/jobs0/datasets0/samples0/tasks0/submissions0/annotations0/publications0/ai_calls0. Bank books1/chapters8/pages242/sets86/copy_links0. Environment checksum unchanged. Dinoiki/gpt-5-mini dan vision tetap aktif, tarif belum diatur. HTTPS health200/TLS valid, config cache600, tepat satu cron worker, heartbeat `2026-10-21T21:29:27+00:00` (22 Oktober04:29:27 WIB).

Pemulihan: pegang worker lock, maintenance dan pulihkan database/kode secara terkoordinasi dari backup privat; arsip sumber memungkinkan impor ulang bank identik. Pertahankan storage dan seluruh data siswa. Bangun ulang caches, chmod600 config, up dan verifikasi health/smoke. Jangan otomatis rollback migrasi setelah salinan soal bank mulai digunakan.
