# Skenario demo jalur belajar kelas 1

Operator menjalankan `php scripts/seed-learning-path-demo.php --apply` sekali setelah bank buku kelas 1 tersedia. Perintah ini sengaja menolak pengulangan jika salah satu alamat demo sudah ada; seluruh pembuatan data dilakukan dalam satu transaksi. Hasilnya menampilkan kata sandi acak hanya pada keluaran perintah, bukan disimpan di repositori atau dokumen.

Kelas **DEMO - Matematika Kelas 1** dimiliki akun guru demo. Kelas memakai buku asli yang telah diimpor (Materi 1–8) dan dua asesmen simulasi yang terpisah, masing-masing dua soal aritmetika sederhana dengan kunci dan pedoman. Soal tes adalah contoh buatan untuk simulasi, bukan klaim salinan halaman buku. Kedua tugas tes dibuka selama 89 hari sejak pembuatan; batas waktu dapat dikelola guru demo.

| Akun siswa demo | Tahap awal | Yang terlihat |
|---|---:|---|
| `siswa-demo-awal@example.test` | 0 | Pretest siap dikerjakan; seluruh materi terkunci. |
| `siswa-demo-materi@example.test` | 1 | Pretest sudah dikirim; Materi 1 terbuka. |
| `siswa-demo-posttest@example.test` | 9 | Materi 1–8 sudah ditandai selesai; post-test terbuka. |

Guru demo masuk dengan `guru-demo-kelas1@example.test` dan membuka kelas demo lalu **Jalur belajar** untuk melihat kemajuan ketiga peserta. Tanda selesai pada akun tahap 9 dan jawaban pretest adalah data sintetis untuk memperagakan tampilan; belum ada pemeriksaan guru atau nilai terbit. Semua halaman materi tetap berasal dari buku privat dengan pemeriksaan checksum. Akun contoh tidak memiliki akses ke kelas lain.
