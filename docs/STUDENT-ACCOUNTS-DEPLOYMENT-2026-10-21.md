# Akun siswa dan guru uji — deployment 21 Oktober 2026

Fitur login siswa diminta pengguna setelah M04, lalu satu akun guru dan satu akun siswa dibuat untuk uji coba. Akun siswa hanya membaca kelas/jawaban peserta yang ditautkan; pengerjaan mandiri/pengiriman jawaban belum tersedia.

- Application commit: `c9c1c22a7ace445782d0d70d78fbd6d8e40dc7b6`.
- [CI 39534320235](https://github.com/arisbudianto/AgentMath/actions/runs/39534320235): PHP 8.3/8.4, SQLite/MariaDB success.
- Lokal: **77 tests / 715 assertions PASS**, sintaks 73 PHP files, manifest dan views PASS. Upgrade dengan admin/guru, kelas, peserta, soal, jawaban lama serta FK tetap utuh PASS.
- Backup privat sebelum rilis: `/home/speakver/agentmath-backups/students-c9c1c22/`, SQL/code archive/configuration dan migration preview; file kredensial dump sementara dihapus.
- Backup dan deployment mengambil lock OS worker `/home/speakver/.agentmath-grading.lock`. Source checkout commit yang lulus CI, Composer production/platform requirements PASS. Migrasi hanya memperluas enum role dan menambah tabel student_profiles dengan FK/unik.
- Maintenance, rsync (preserve .env/storage/cache/web config), migrasi **batch 7**, cache rebuilt dan up exit 0. `.env`/APP_KEY byte-identical dengan backup. Cron M04 tetap berjalan (heartbeat 09:44:03 WIB setelah rilis); AI tetap nonaktif. Health HTTP 200, TLS verification 0.

## Akun/data uji yang sengaja dipertahankan

Satu guru aktif, satu siswa aktif, kelas **Kelas Uji Coba SD**, jenjang 4, 2026/2027, tiga peserta sintetis UJI001/UJI002/UJI003. Siswa ditautkan hanya ke UJI001. Satu asesmen/soal dan satu jawaban ketikan sintetis disediakan untuk menguji portal, tanpa rubrik yang disahkan atau nilai. Admin lama tetap satu dan tidak diubah. Ini bukan fixture smoke yang dihapus: pengguna meminta akun/data ini untuk dipakai mencoba aplikasi.

Kredensial dibuat acak secara lokal, disimpan di berkas diabaikan Git `.tools/akun-uji-agentmath.txt`; hanya hash bcrypt dikirim ke server melalui helper privat. Password tidak dicetak di log, laporan, tangkapan layar atau GitHub. Helper pembuatan akun menolak jika salah satu email uji sudah ada, sehingga tidak mereset/mengganti kredensial lama. Akun dan data contoh dibuat dalam satu transaksi.

## Verifikasi browser produksi

- Login guru nyata berhasil, dashboard guru tampil, kelas uji terlihat dengan tiga peserta/satu asesmen, logout berhasil.
- Login siswa nyata berhasil; dashboard/navigasi hanya ruang siswa, halaman menampilkan kelas/kode UJI001 dan jawaban contoh sendiri.
- Saat siswa membuka `/classrooms`, produksi mengembalikan 403; kembali ke ruang siswa berhasil. Kunci/pedoman/rubrik tidak ditampilkan di portal siswa.
- Browser ditinggalkan pada ruang siswa uji. Bukti `docs/student-accounts-live.png` diabaikan Git, hanya data sintetis.
- Pemeriksaan akhir: admin=1, teacher=1, student=1, student_profiles=1, kedua akun uji aktif, guru memiliki satu kelas. Tidak ada akun produksi lama yang diubah.

Tes otomatis tambahan mencakup foto privat, revisi terkini, transkripsi confirmed vs draft, duplikasi peserta/account rollback, akun nonaktif, dan isolasi terhadap jawaban siswa lain. Tidak ada permintaan AI berbayar.

## Pemulihan

Nonaktifkan akun uji melalui admin bila tidak lagi dipakai; jangan menghapus riwayat jawaban/profile secara otomatis. Rollback kode memerlukan lock worker, backup terkini, preservasi .env/APP_KEY/storage dan tabel/role baru. Migration down sengaja menolak selama akun siswa masih ada. Jangan restore SQL penuh tanpa menilai data baru yang telah dibuat pengguna. Kredensial uji tidak termasuk backup kode Git, hanya berkas privat pengguna dan hash di database.
