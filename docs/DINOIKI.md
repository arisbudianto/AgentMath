# Dinoiki AI — Responses adapter

Pengguna memilih Dinoiki AI dan model **gpt-5-mini**. Dokumentasi dashboard setelah login menyediakan `POST https://ai.dinoiki.com/v1/responses` untuk model OpenAI, menerima input/instructions dan mengembalikan Responses format. Model non-OpenAI memakai endpoint lain dan belum didukung adapter ini. Rujukan: [Responses Dinoiki](https://dinoiki.com/dashboard/ai/docs/responses), [halaman API publik](https://dinoiki.com/ai).

Konfigurasi server:

```dotenv
AI_PROVIDER=dinoiki
DINOIKI_API_KEY=isi_hanya_di_server
DINOIKI_MODEL=gpt-5-mini
AI_ENABLED=true
AI_TRANSCRIPTION_ENABLED=true
AI_MAX_OUTPUT_TOKENS=4000
```

`DINOIKI_MODEL` mengambil prioritas, lalu `AI_MODEL` jika kosong. Key Dinoiki **hanya** berasal dari DINOIKI_API_KEY; tidak mengambil key OpenAI secara diam-diam. Provider openai tetap menggunakan AI_API_KEY/AI_MODEL dan endpoint resmi OpenAI. Endpoint provider berupa allowlist tetap, bukan URL dari input/browser/environment bebas. Redirect HTTP dimatikan; tidak ada retry jaringan otomatis atau fallback ke protokol/model lain.

Kesiapan konfigurasi memeriksa provider/model/key tanpa mencetak key. Dinoiki Responses hanya diaktifkan untuk ID model keluarga OpenAI; klaim kompatibel gateway tetap memerlukan uji langsung. Adapter mempertahankan `store=false`, strict JSON schema, status completed, refusal/incomplete/error checks, batas body, citation/score validation dan validasi transkripsi. Flag store dikirim; ini bukan jaminan independen tentang retensi gateway atau penyedia model.

Pada uji awal, gateway memberi HTTP 200/completed dan JSON, tetapi aturan belum memenuhi validator aplikasi. Sebagai penguatan kompatibilitas, kontrak JSON Schema juga disampaikan melalui instruksi tepercaya untuk Dinoiki; parameter format HTTP tetap dikirim. Ini tidak melonggarkan validator atau mengubah JSON rusak menjadi hasil sah. Varian prompt tercatat sebagai `m02_v1_dinoiki_contract` / `m03a_v1_dinoiki_contract`; provider OpenAI langsung tidak menerima perubahan prompt ini.

Rubrik memakai dua permintaan A1/A2, masing-masing timeout 60 detik (connect 10), output 1000–8000 token (default 4000). Creation lock diperpanjang menjadi 180 detik untuk menaungi dua timeout. Transkripsi memakai satu permintaan maksimal 60 detik. UI memperingatkan proses rubrik dapat memerlukan sekitar 120 detik; batas waktu web host masih dapat menghentikan request lebih dahulu. Tidak ada auto-approval atau nilai otomatis. M05 Marker/arbitrase belum diimplementasikan oleh integrasi provider ini.

Foto hanya dikirim saat guru meminta bantuan AI dan menyetujui checkbox. UI menyebut penerima **Dinoiki AI (gateway ke model OpenAI)**. Foto asli tetap privat; konteks tambahan tidak menyertakan akun/kode peserta/kunci jawaban. Pengajar tetap harus memastikan foto bebas identitas pribadi dan mengonfirmasi transkripsi. Tidak ada foto/jawaban siswa nyata yang digunakan untuk uji integrasi; uji memakai sumber dan gambar sintetis.

Pengujian mock mencakup endpoint/auth/provider metadata, schema strict, vision bytes privat, tidak adanya identitas/kunci pada vision input, refusal/incomplete/wrong protocol, provider/model tidak didukung dan kerahasiaan error/key. Hasil uji live/configuration/deployment dicatat terpisah setelah dijalankan; konfigurasi siap bukan bukti akurasi penilaian.
