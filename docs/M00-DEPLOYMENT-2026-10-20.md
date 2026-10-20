# M00 deployment — 20 Oktober 2026

Status: kode dan database terpasang; admin pertama aktif. Login menggunakan kredensial admin dan uji lintas pengajar pada produksi belum dilakukan.

- URL: https://math.speakverse.id/login
- Commit aplikasi: `5604c2ddf563db1542d04ddb5c9ba7a3249574dc`.
- GitHub CI: commit 5604c2d, c2ece03 dan b91a941 berstatus success, diperiksa melalui GitHub API dari server.
- PHP CLI: 8.4.26; Composer tersedia; dependensi dipasang dari composer.lock dengan --no-dev.
- Folder aplikasi: `/home/speakver/math.speakverse.id`.
- Document root: `/home/speakver/math.speakverse.id/public`.
- Database: `speakver_math`; pengguna memiliki ALL PRIVILEGES. Password hanya disimpan di server.
- Migrasi `2026_10_20_000000_create_foundation_tables`: Ran, batch 1.
- APP_KEY dibuat untuk instalasi baru; permission .env 600. Config dan view cache berhasil.
- Admin aktif: 1. Asesmen: 0. AI_ENABLED=false.

## Pemeriksaan

- Domain publik /login dibuka melalui browser dan menampilkan AgentMath.
- /health: JSON app=AgentMath, status=ok.
- /login: HTTP 200; TLS certificate verification berhasil.
- /dashboard, /admin/users, /assessments tanpa sesi: HTTP 302 ke /login.
- /.env: HTTP 403.
- Cookie sesi: Secure, HttpOnly, SameSite=Lax.
- Pengujian login dengan password admin, pengelolaan akun, dan isolasi dua pengajar pada produksi masih perlu dilakukan. Pengujian otomatis sebelumnya lulus di CI; tidak dijalankan ulang pada database produksi.

## Pemulihan

Instalasi pertama: salinan isi public sebelum deployment berada di `/home/speakver/agentmath-backups/m00-initial/`. Checkout sumber commit deployment berada di `/home/speakver/agentmath-m00`.

Jika perlu memulihkan kode, pertahankan .env, APP_KEY, storage dan database. Jangan menjalankan migrate:rollback untuk pemulihan kode karena migration down menghapus tabel. Salinan public awal hanya memulihkan placeholder/config web, bukan aplikasi versi sebelumnya.

Tidak ada rahasia atau data peserta dalam catatan ini.
