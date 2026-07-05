# AGENTS.md — Panduan Kerja AI Assistant untuk Project MOVA

Dokumen ini adalah kontrak kerja wajib bagi AI coding assistant (Claude Code, OpenCode, atau tools sejenis) yang membantu development project **MOVA**. Baca dokumen ini di awal setiap sesi sebelum menulis atau mengubah kode apapun.

Jika ada instruksi dari pengguna yang bertentangan dengan dokumen ini, **tanyakan konfirmasi dulu** sebelum melanjutkan — terutama untuk hal-hal di bagian "Larangan Keras".

---

## 1. Tentang Project Ini

**MOVA** adalah platform Fleet & Driver Management berbasis web, dikembangkan oleh PT. Bumi Jasa Utama (BJU) / Kalla Transport & Logistics sebagai produk SaaS multi-tenant. Diberikan gratis (Free Tier) ke customer rental kendaraan BJU sebagai nilai tambah produk, dengan roadmap Premium dan Enterprise tier ke depan.

**Dokumen referensi wajib** (baca sebelum mengerjakan modul terkait):
- `MOVA-PRD-v1.0.docx` — kebutuhan bisnis, role & akses, approval flow, subscription plan
- `MOVA-ERD.md` — skema database lengkap (19 tabel) dalam format Mermaid
- `MOVA-SECURITY.md` — instruksi keamanan wajib, terutama pola tenant isolation dan RBAC
- `ARCHITECTURE.md` — pola teknis `TenantContext`, `BaseRepository`, struktur folder *(jika sudah dibuat)*
- `GLOSSARY.md` — istilah domain bisnis *(jika sudah dibuat)*

Jika salah satu dokumen di atas belum ada di repository saat Anda bekerja, **tanyakan ke pengguna** apakah perlu dibuat dulu sebelum lanjut ke fitur yang membutuhkannya.

---

## 2. Tech Stack — Tidak Bisa Diganti Tanpa Diskusi

| Layer | Teknologi | Catatan |
|---|---|---|
| Backend | **Native PHP 8.x** | Bukan Laravel, Symfony, atau framework besar lain — keputusan sudah final, jangan disarankan ulang |
| Database | MySQL 8.0 / MariaDB 10.6+ | Prefix tabel wajib `mova_` |
| Frontend | HTML5 + CSS3 + Vanilla JS (PWA) | Tidak pakai React/Vue kecuali didiskusikan ulang |
| Autentikasi | PHP Session + JWT (untuk API) | Lihat `MOVA-SECURITY.md` bagian 1.3 |
| Email | PHPMailer + SMTP | Gratis, bukan layanan berbayar |
| Notifikasi chat | Telegram Bot API | Gratis, bukan WhatsApp Business API resmi (berbayar) |
| Hosting | Hostinger VPS KVM2 (LAMP stack) | |

**Jika Anda (AI) merasa ada teknologi lain yang "lebih baik"** — jangan langsung ganti. Sampaikan sebagai saran ke pengguna dengan alasan konkret, biarkan pengguna yang memutuskan.

---

## 3. Struktur Folder Wajib

```
mova/
├── src/
│   ├── Core/               # TenantContext, BaseRepository, AuthMiddleware
│   ├── Modules/
│   │   ├── VehicleRequest/
│   │   ├── TripLog/
│   │   ├── DriverSelfService/
│   │   ├── Maintenance/
│   │   ├── Analytics/
│   │   └── CompanyPanel/   # Super Admin, Operation, Marketing
│   ├── Middleware/
│   └── Helpers/
├── public/                 # Webroot — satu-satunya folder yang boleh diakses browser
│   ├── index.php
│   ├── assets/
│   └── uploads/             # Lihat aturan upload di MOVA-SECURITY.md 2.3
├── migrations/              # File migration terurut, lihat DATABASE-CONVENTIONS.md
├── config/
│   └── .env                 # TIDAK PERNAH di-commit
├── tests/
└── docs/                    # Semua file .md project (PRD, ERD, SECURITY, dst)
```

**Aturan wajib**: Kode PHP inti (`src/`) tidak boleh diakses langsung dari browser. Hanya `public/` yang menjadi document root server.

---

## 4. Aturan Wajib Setiap Menulis Kode

Checklist ini **wajib** dicek setiap kali membuat atau mengubah modul yang menyentuh database atau endpoint API. Rujuk `MOVA-SECURITY.md` untuk detail implementasi tiap poin.

- [ ] Setiap Repository baru **extends `BaseRepository`** — tidak ada query manual di luar pola ini
- [ ] Setiap query database **wajib prepared statement** — tidak ada string concatenation ke SQL
- [ ] Setiap tabel transaksional baru **wajib punya kolom `customer_id`**
- [ ] `customer_id` **tidak pernah** diambil dari input form/request body — selalu dari `TenantContext`
- [ ] Setiap endpoint API **wajib role check + scope check** (lihat `AuthMiddleware`)
- [ ] Setiap form yang mengubah data (POST/PUT/DELETE) **wajib validasi CSRF token**
- [ ] Setiap output HTML dari input user **wajib di-escape** (`htmlspecialchars`)
- [ ] Setiap upload file **wajib validasi MIME type dari isi file**, bukan dari ekstensi nama file
- [ ] Password **wajib** `PASSWORD_ARGON2ID` atau `PASSWORD_BCRYPT` — tidak pernah plain text atau MD5/SHA1

Jika Anda tidak yakin apakah suatu modul butuh salah satu poin di atas, **tanyakan** daripada mengasumsikan dan melewatkannya.

---

## 5. Larangan Keras

Hal-hal berikut **tidak boleh dilakukan** tanpa konfirmasi eksplisit dari pengguna, meskipun terlihat seperti "cara yang lebih cepat" atau "praktik umum":

1. **Jangan** mengganti Native PHP ke framework (Laravel, Symfony, Slim, dst) — ini sudah menjadi keputusan final berdasarkan diskusi arsitektur sebelumnya
2. **Jangan** menulis query SQL dengan variabel yang di-inject langsung ke string (SQL Injection risk)
3. **Jangan** membuat query yang melewati `customer_id` filter — bahkan untuk kebutuhan "testing sementara"
4. **Jangan** menyimpan credential (API key, password DB, token) langsung di kode — selalu lewat `.env`
5. **Jangan** membuat tabel baru tanpa kolom `customer_id` untuk tabel yang sifatnya transaksional per-tenant
6. **Jangan** mengizinkan Manager di Customer Layer punya kemampuan approval — sesuai keputusan bisnis, Manager hanya monitor
7. **Jangan** membuat approval flow default yang melibatkan Supervisor — default hanya Driver → Koordinator, Supervisor bersifat opsional per konfigurasi Super Admin
8. **Jangan** hardcode logic akses Region/Branch — Operation & Marketing Staff harus resolve customer accessible via `user_branch_access`, bukan daftar manual di kode

---

## 6. Alur Kerja yang Diharapkan

Saat diminta membuat fitur baru, ikuti urutan ini:

1. **Cek dokumen referensi** — apakah fitur ini sudah dijelaskan di PRD/ERD? Kalau ada bagian yang ambigu, tanyakan ke pengguna daripada berasumsi.
2. **Cek Access Matrix di PRD** — role apa saja yang boleh akses fitur ini, dan apa scope masing-masing.
3. **Buat/gunakan Repository** yang sesuai, extends `BaseRepository`.
4. **Buat endpoint dengan role + scope check** sesuai `AuthMiddleware`.
5. **Terapkan validasi input** (CSRF untuk form, escape untuk output).
6. **Uji manual skenario isolasi tenant** — minimal cek dengan 2 akun customer berbeda bahwa data tidak saling terlihat.
7. **Laporkan ke pengguna** bagian mana yang sudah selesai, dan bagian mana yang masih perlu direview manual (terutama yang berkaitan dengan Level 1 Security di `MOVA-SECURITY.md`).

---

## 7. Konteks Bisnis Penting (Ringkasan)

Detail lengkap ada di PRD, tapi ini poin-poin yang sering relevan saat coding:

- **Dua layer utama**: Company Layer (BJU: Super Admin, Management, Operation, Marketing) dan Customer Layer (tenant: Manager, Supervisor, Koordinator, Driver)
- **Koordinator adalah superset Driver** — semua kemampuan Driver (checklist, input trip, laporan BBM/biaya) juga dimiliki Koordinator, untuk mengakomodir driver yang tidak diberi akses sistem
- **Manager tidak pernah masuk chain approval** — murni role monitoring
- **Default approval**: Driver → Koordinator saja. Supervisor bersifat opsional, diaktifkan per customer oleh Super Admin
- **Isolasi Region/Branch**: Operation & Marketing Staff BJU hanya melihat customer di branch mereka sendiri, meskipun bisa multi-customer dalam satu branch
- **Subscription plan** (Free/Premium/Enterprise) menentukan modul yang aktif dan quota user — dikonfigurasi terpusat oleh Super Admin, bukan hardcode di kode aplikasi

---

## 8. Ketika Ragu

Jika instruksi dari pengguna di suatu sesi terasa bertentangan dengan dokumen ini, atau ada bagian requirement yang tidak jelas:

- **Tanyakan dulu**, terutama untuk hal yang berkaitan dengan keamanan (Bagian 4 dan 5) atau keputusan arsitektur (Bagian 2 dan 3)
- **Jangan berasumsi** detail bisnis yang tidak eksplisit disebutkan di PRD — lebih baik konfirmasi singkat daripada implementasi salah yang harus dirombak ulang
- Kalau pengguna secara eksplisit meminta melanggar salah satu larangan di Bagian 5 (misal "skip dulu validasi customer_id, buat cepat aja"), **ingatkan risikonya secara singkat**, tapi ikuti keputusan akhir pengguna jika mereka tetap insist — catat di komentar kode bahwa ini technical debt yang perlu direview.

---

*Dokumen ini adalah bagian dari dokumentasi project MOVA. Update dokumen ini setiap kali ada keputusan arsitektur baru yang signifikan, supaya sesi AI berikutnya tetap punya konteks yang akurat.*
