# MOVA — Roadmap & Tier Reference

> Dokumen acuan untuk pengembangan berikutnya. Update setiap ada keputusan/skope baru.
> Status per **2026-07-03**.

Referensi paket (dari `migrations/001_foundation.sql` → `mova_subscription_plans`):

| Tier | max_users | Retensi | Modul |
|---|---|---|---|
| **Free** | 10 | 90 hari | vehicle_request, trip_log, driver_self_service, fuel_expense, maintenance_reminder |
| **Premium** | 50 | 365 hari | + analytics, supervisor_approval, export |
| **Enterprise** | -1 (∞) | -1 (∞) | + gps_monitoring, custom_branding, api_integration, custom_approval |

---

## 1. Free tier — Status

### ✅ Sudah jadi & berjalan
- **Vehicle Request** — ajukan → approve (L1 Koordinator) → reject → assign (dgn/tanpa driver, full/half day).
- **Trip Log** — input, start, complete, edit, detail.
- **Driver Self-Service** — checklist pre/post-trip + foto, issue report (create/edit/resolve).
- **Fuel & Expense** — lapor, edit, approve, reject.
- **Maintenance** — jadwal + log + banner overdue.
- **Pendukung**: RBAC (`requireRole`/`requireLayer`), CSRF, rate-limit login, Argon2id, isolasi tenant (query ter-scope `customer_id` via `BaseRepository`), notifikasi in-app, dashboard, searchable dropdown, modul **Konfigurasi** (Tipe Perjalanan/Kategori), data dummy Juni 2026.
- Approval flow = **Driver → Koordinator** (default Free benar; Supervisor opsional/off).

### ❌ Gap Free tier (belum ada)
| # | Gap | Prioritas | Catatan |
|---|---|---|---|
| 1 | **Gating modul per-plan** (baca `allowed_modules`) | 🔴 Prasyarat premium | Tanpa ini, customer Free bisa lihat modul premium |
| 2 | **Kuota `max_users`** (Free=10) di `UserController::create` | 🟠 | Belum dibatasi |
| 3 | **Retensi data 90 hari** (purge job/cron) | 🟡 | Background |
| 4 | **Notifikasi maintenance due/overdue** (proaktif) | 🟡 | Sekarang hanya banner visual |

---

## 2. Premium modules (belum dibangun)

| key | Label | Deskripsi singkat |
|---|---|---|
| `analytics` | Analitik & Laporan | Dashboard analitik lanjutan: tren biaya, utilisasi armada, performa driver |
| `supervisor_approval` | Approval Bertingkat | Tambahan approval Level 2 oleh Supervisor (baca `mova_customer_configs.enable_supervisor_approval`) |
| `export` | Export Data | Ekspor laporan trip/BBM/biaya ke Excel & PDF |

## 3. Enterprise modules (belum dibangun)

| key | Label | Deskripsi singkat |
|---|---|---|
| `gps_monitoring` | GPS Monitoring | Pelacakan posisi kendaraan real-time di peta |
| `custom_branding` | Custom Branding | Logo, warna, domain sesuai identitas perusahaan |
| `api_integration` | API Integration | Integrasi data via REST API dengan sistem eksternal |
| `custom_approval` | Custom Approval Flow | Alur approval yang dapat dikustomisasi |

---

## 4. Mode DEMO (keputusan 2026-07-03)

Untuk demo ke management, **semua modul harus terlihat di sidebar** — termasuk Premium & Enterprise — dengan **flagging**:
- Section header menandai tier (**Premium** / **Enterprise**).
- Tiap item modul yang belum jadi diberi chip **"Under Development"**.
- Klik modul → halaman placeholder ("sedang dikembangkan" + deskripsi + badge tier), bukan 404.

**Implementasi:**
- Katalog modul tunggal: helper `moduleCatalog()` di `src/Helpers/functions.php` (sumber tunggal untuk sidebar + halaman placeholder).
- Sidebar: `src/Modules/CustomerPanel/Views/layout.php` (section Premium & Enterprise).
- Placeholder: `UnderDevelopmentController@show`, route `GET /customer/module/{key}`.
- Ditampilkan di **Customer Panel** → demo login sebagai **`manager@demo.com` / `demo123`** untuk melihat produk bertingkat lengkap.

> Flag ini murni UI; belum ada gating fungsional. Saat modul benar-benar dibangun, ganti placeholder dengan halaman asli + terapkan gating (#1 di bawah).

---

## 5. Technical prerequisites (sebelum modul premium fungsional)

1. **Plan gating infra**
   - `TenantContext::hasModule(string $key): bool` — baca `allowed_modules` plan customer + override `mova_customer_configs.allowed_modules_override`.
   - `AuthMiddleware::requireModule(string $key)` — guard endpoint premium.
   - Gating menu sidebar (item disembunyikan/locked bila plan tak mengizinkan).
2. **Kuota user** — cek `max_users` (plan atau `max_users_override`) di `UserController::create`.
3. (Nice-to-have) Retensi data & reminder maintenance proaktif.

---

## 6. Catatan teknis penting
- **Migration runner** (`migrations/run.php`) sempat bermasalah (statement diawali komentar terbuang) — sudah diperbaiki (strip komentar sebelum split + aman implicit-commit DDL).
- **Service worker** dinonaktifkan selama development (self-destruct) — aktifkan lagi sebelum production bila butuh PWA offline.
- **UI system**: Enterprise SaaS light, brand teal `#0F6E56`, sidebar putih, kartu netral. Lihat memori `ui-design-system`.
