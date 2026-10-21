# Akun siswa dan guru untuk uji coba

Ditambahkan atas permintaan pengguna setelah M04. Akun login sekarang mencakup admin, teacher dan student. Tidak ada registrasi publik atau password bawaan di source.

Admin membuat akun guru seperti sebelumnya. Untuk siswa, guru lebih dahulu membuat kelas dan peserta berkode; admin memilih peran **Siswa** dan peserta yang belum memiliki akun pada **Akun → Buat akun**. Dropdown menampilkan maksimal 200 peserta terbaru pada kelas pengajar aktif. Admin hanya melihat metadata kelas/kode untuk penautan, bukan isi soal, kunci, jawaban, rubrik atau proses milik guru.

`student_profiles` menautkan satu user ke satu participant; keduanya unik dengan FK restrict. Akun dan tautan dibuat satu transaksi dengan participant row lock sehingga duplikasi tidak meninggalkan akun yatim. Tautan tidak dapat diubah/dihapus melalui model. Relasi ini MVP satu kelas per akun; perpindahan kelas/penautan ulang belum tersedia. Menonaktifkan akun melalui admin mencabut sesi/login siswa seperti akun lain.

Setelah login, siswa membuka **Ruang siswa** untuk melihat kelas, kode dan jawaban miliknya yang dicatat guru. Jawaban ketikan/foto terkini tersedia; transkripsi foto hanya tampil bila versi terbaru confirmed. Draf koreksi tidak tampil. Foto di-stream melalui rute khusus yang memeriksa kepemilikan peserta dan revisi terkini, dengan no-store/private, MIME tetap dan CSP sandbox. Siswa tidak menerima kunci, pedoman, rubrik, hasil agent, data peserta lain atau akses halaman guru/admin.

**Cakupan terkini:** M10 menyediakan publikasi/cabut nilai oleh guru; M11 menyediakan tugas yang dibuka guru dan pengiriman ketikan/foto oleh siswa terkait. Tidak ada registrasi siswa mandiri, reset password mandiri, atau akses otomatis ke seluruh asesmen kelas. Lihat docs/M10.md dan docs/M11.md.
