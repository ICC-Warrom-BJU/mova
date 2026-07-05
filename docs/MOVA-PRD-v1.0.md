# MOVA — Mobility Vehicles Administration

## Product Requirements Document (PRD) v1.0

| | |
|---|---|
| Versi Dokumen | v1.0 — Phase 1 (Free Tier) |
| Tanggal | 1 Juli 2026 |
| Dibuat oleh | Muhajir Muslimin — PT. Bumi Jasa Utama |
| Status | Draft — Internal Review |
| Platform | Web Responsive (PWA) + Native PHP |
| Database | MySQL / MariaDB |

---

## 1. Ringkasan Eksekutif

**🎯 Tujuan Utama:** Memberikan customer BJU tools digital gratis untuk mengelola armada, driver, trip, dan biaya operasional kendaraan — menggantikan proses manual berbasis kertas atau spreadsheet — sekaligus menjadi keunggulan kompetitif produk rental BJU di pasar.

MOVA adalah platform manajemen armada dan driver berbasis web yang dikembangkan oleh PT. Bumi Jasa Utama (BJU) / Kalla Transport & Logistics sebagai produk nilai tambah (add-on) untuk customer rental kendaraan. Sistem ini dirancang sebagai platform multi-tenant SaaS dengan dua lapisan akses utama: Company Layer (operator BJU) dan Customer Layer (perusahaan penyewa kendaraan).

Pada Phase 1, MOVA diluncurkan dalam versi Free Tier yang diberikan secara gratis kepada seluruh customer aktif BJU sebagai diferensiasi produk. Roadmap selanjutnya mencakup versi Premium dan Enterprise dengan fitur yang lebih lengkap.

---

## 2. Lingkup & Batasan Sistem

### 2.1 Yang Dicakup (Phase 1 — Free Tier)

- Modul Vehicle Request dengan approval workflow Koordinator (opsional Supervisor)
- Modul Trip & Driver Log — pencatatan perjalanan digital pengganti buku manual
- Modul Driver Self-Service — checklist kendaraan, foto dokumentasi, laporan kerusakan
- Modul Laporan BBM & Biaya Perjalanan dengan approval Koordinator
- Modul Maintenance Reminder — jadwal servis berbasis KM dan tanggal
- Dashboard ringkasan untuk semua role di Customer Layer
- Company Layer Panel — Super Admin, Management, Operation, Marketing
- Sistem notifikasi via Email (PHPMailer) dan Telegram Bot API
- Multi-tenant dengan isolasi data ketat per customer
- Manajemen subscription & quota user via Super Admin Panel

### 2.2 Yang Tidak Dicakup (Phase 2+)

- GPS Monitoring real-time (memerlukan hardware GPS terpasang di unit)
- Integrasi langsung dengan sistem rental internal BJU (TMS, FleetOps)
- Mobile App native (Android/iOS) — Phase 1 hanya PWA
- Laporan keuangan terintegrasi dengan sistem akuntansi
- Custom approval workflow (tersedia di Enterprise)
- API publik untuk integrasi pihak ketiga (tersedia di Enterprise)

---

## 3. Arsitektur Role & Akses

### 3.1 Dual-Layer Architecture

MOVA menggunakan arsitektur dua lapis (dual-layer) yang memisahkan secara tegas antara **Company Layer** (BJU sebagai operator platform) dan **Customer Layer** (setiap perusahaan penyewa sebagai tenant).

### 3.2 Access Matrix

| Role | Layer | Scope Akses | Kewenangan Utama |
|---|---|---|---|
| Super Admin | Company | Global — semua data | Konfigurasi platform, manage semua user, override subscription, feature flag control |
| Management BJU | Company | Semua Region & Branch | Dashboard global, laporan semua customer, read-only |
| Operation Staff | Company | 1 Region + 1 Branch (multi Customer) | Monitor operasional, kelola maintenance, lihat trip — hanya customer di branch-nya |
| Marketing Staff | Company | 1 Region + 1 Branch (multi Customer) | Onboarding customer, data kontrak commercial — hanya customer di branch-nya |
| Manager | Customer | Customer sendiri | Dashboard & laporan lengkap, monitor semua aktivitas, tanpa chain approval |
| Supervisor | Customer | Customer sendiri | Approve level 2 (jika diaktifkan Super Admin), monitor Koordinator & Driver |
| Koordinator | Customer | Customer sendiri | Approve semua laporan & request dari Driver, assign tugas Driver, input semua laporan (superset Driver) |
| Driver | Customer | Data milik sendiri | Terima tugas, checklist kendaraan, input trip, laporan BBM & biaya, lapor kerusakan |

### 3.3 Aturan Isolasi Data

- Setiap Customer Layer sepenuhnya terisolasi — Customer A tidak dapat melihat data Customer B meskipun berada di Branch yang sama.
- Isolasi diterapkan di level query database menggunakan `customer_id` yang melekat pada setiap record dan setiap user session.
- Operation Staff dan Marketing Staff BJU hanya dapat mengakses customer yang berada di Region dan Branch tempat mereka terdaftar — dibatasi sistem, bukan bergantung pada disiplin user.
- Cross-region dan cross-branch selalu diblokir di level middleware, bukan hanya di tampilan UI.
- Super Admin dan Management BJU adalah satu-satunya role yang dapat mengakses data lintas Region dan Branch.

### 3.4 Catatan Penting: Koordinator sebagai Superset Driver

Koordinator memiliki semua kemampuan Driver ditambah kemampuan approval dan pengelolaan. Artinya, Koordinator dapat menginput trip, laporan BBM, biaya, dan checklist atas nama tim — mengakomodir kondisi ketika driver tidak diberikan akses sistem oleh Koordinatornya.

---

## 4. Alur Approval

### 4.1 Vehicle Request

| Kondisi | Alur Approval | Keterangan |
|---|---|---|
| Default (semua customer) | Driver/Karyawan → Koordinator | Koordinator sebagai approver tunggal |
| Jika Supervisor diaktifkan (konfigurasi Super Admin) | Driver → Koordinator → Supervisor | Supervisor sebagai final approver, diaktifkan per customer |
| Manager | Tidak masuk chain approval | Manager hanya monitor & view status |

### 4.2 Laporan BBM & Biaya Perjalanan

| Jenis Laporan | Alur Approval | Efek Setelah Approve |
|---|---|---|
| Laporan BBM | Driver → Koordinator | Masuk Cost Analytics & rekapitulasi bulanan |
| Biaya Lain (tol, parkir, dll) | Driver → Koordinator | Masuk Cost Analytics & rekapitulasi bulanan |
| Pre-trip Checklist | Langsung tersimpan (no approval) | Notifikasi otomatis ke Koordinator |
| Laporan Kerusakan Unit | Langsung tersimpan (no approval) | Notifikasi ke Koordinator & Operation BJU |

---

## 5. Subscription Plan

Semua batasan fitur dan quota user dikonfigurasi melalui Super Admin Panel dan disimpan di tabel `customer_subscriptions`. Super Admin dapat melakukan override per customer kapan saja tanpa perlu deploy ulang.

| Fitur | Free | Premium | Enterprise |
|---|---|---|---|
| Max user per customer | 10 (configurable) | 50 | Unlimited |
| Vehicle Request + Approval | ✓ | ✓ | ✓ |
| Trip & Driver Log | ✓ | ✓ | ✓ |
| Pre-trip Checklist & Foto | ✓ | ✓ | ✓ |
| Laporan BBM & Biaya | ✓ | ✓ | ✓ |
| Maintenance Reminder (basic) | ✓ | ✓ | ✓ |
| Cost & Analytics Lengkap | — (terbatas) | ✓ | ✓ |
| Supervisor Approval Chain | — | ✓ | ✓ |
| Export PDF & Excel | — | ✓ | ✓ |
| Notifikasi Email & Telegram | — | ✓ | ✓ |
| GPS Monitoring (Phase 2) | — | — | ✓ |
| Custom Branding / Logo | — | — | ✓ |
| API Integration | — | — | ✓ |
| Custom Approval Workflow | — | — | ✓ |
| Data Retention | 3 bulan | 12 bulan | Unlimited |

---

## 6. Database Schema

### 6.1 Master Data & Struktur Organisasi

#### `mova_regions`
Master wilayah / region operasional BJU.

| Column | Type | Null | Key | Description |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK | Primary key, auto increment |
| name | VARCHAR(100) | NO | | Nama region |
| code | VARCHAR(20) | NO | UQ | Kode unik region |
| is_active | TINYINT(1) | NO | | Status aktif region, default 1 |
| created_at | TIMESTAMP | NO | | Waktu dibuat |
| updated_at | TIMESTAMP | NO | | Waktu terakhir diperbarui |

#### `mova_branches`
Master cabang / branch di setiap region.

| Column | Type | Null | Key | Description |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK | Primary key, auto increment |
| region_id | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_regions.id` |
| name | VARCHAR(100) | NO | | Nama branch |
| code | VARCHAR(20) | NO | UQ | Kode unik branch |
| address | TEXT | YES | | Alamat lengkap branch |
| phone | VARCHAR(20) | YES | | Nomor telepon branch |
| is_active | TINYINT(1) | NO | | Status aktif branch, default 1 |
| created_at | TIMESTAMP | NO | | Waktu dibuat |
| updated_at | TIMESTAMP | NO | | Waktu terakhir diperbarui |

#### `mova_customers`
Master data customer / tenant.

| Column | Type | Null | Key | Description |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK | Primary key, auto increment |
| branch_id | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_branches.id` |
| name | VARCHAR(150) | NO | | Nama perusahaan customer |
| code | VARCHAR(30) | NO | UQ | Kode unik customer |
| pic_name | VARCHAR(100) | YES | | Nama PIC / contact person |
| pic_phone | VARCHAR(20) | YES | | Nomor telepon PIC |
| pic_email | VARCHAR(150) | YES | | Email PIC |
| contract_start | DATE | YES | | Tanggal mulai kontrak rental |
| contract_end | DATE | YES | | Tanggal akhir kontrak rental |
| total_units | INT | NO | | Jumlah unit kendaraan dalam kontrak, default 0 |
| subscription_plan_id | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_subscription_plans.id` |
| is_active | TINYINT(1) | NO | | Status aktif customer, default 1 |
| created_at | TIMESTAMP | NO | | Waktu dibuat |
| updated_at | TIMESTAMP | NO | | Waktu terakhir diperbarui |

### 6.2 User, Role & Akses

#### `mova_roles`
Master daftar role yang tersedia di sistem.

| Column | Type | Null | Key | Description |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK | Primary key, auto increment |
| name | VARCHAR(50) | NO | UQ | Nama role (`super_admin`, `management`, `operation`, `marketing`, `manager`, `supervisor`, `koordinator`, `driver`) |
| layer | ENUM | NO | | `company` atau `customer` |
| description | TEXT | YES | | Deskripsi kewenangan role |
| created_at | TIMESTAMP | NO | | Waktu dibuat |

#### `mova_users`
Master data semua user di semua layer.

| Column | Type | Null | Key | Description |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK | Primary key, auto increment |
| role_id | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_roles.id` |
| customer_id | BIGINT UNSIGNED | YES | FK | Relasi ke `mova_customers.id` (NULL jika Company Layer) |
| name | VARCHAR(100) | NO | | Nama lengkap user |
| email | VARCHAR(150) | NO | UQ | Email, digunakan sebagai username login |
| password | VARCHAR(255) | NO | | Bcrypt hashed password |
| phone | VARCHAR(20) | YES | | Nomor WhatsApp / telepon |
| telegram_chat_id | VARCHAR(50) | YES | | Chat ID Telegram untuk notifikasi |
| avatar | VARCHAR(255) | YES | | Path foto profil |
| is_active | TINYINT(1) | NO | | Status aktif user, default 1 |
| last_login_at | TIMESTAMP | YES | | Waktu login terakhir |
| created_at | TIMESTAMP | NO | | Waktu dibuat |
| updated_at | TIMESTAMP | NO | | Waktu terakhir diperbarui |

#### `mova_user_branch_access`
Relasi akses Operation & Marketing Staff BJU ke Region/Branch.

| Column | Type | Null | Key | Description |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK | Primary key, auto increment |
| user_id | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_users.id` |
| branch_id | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_branches.id` |
| created_at | TIMESTAMP | NO | | Waktu dibuat |

### 6.3 Subscription & Konfigurasi

#### `mova_subscription_plans`
Master paket langganan yang tersedia.

| Column | Type | Null | Key | Description |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK | Primary key, auto increment |
| name | VARCHAR(50) | NO | UQ | Nama plan: `free`, `premium`, `enterprise` |
| max_users | INT | NO | | Batas maksimal user per customer (-1 = unlimited) |
| allowed_modules | JSON | NO | | Array nama modul yang dapat diakses |
| data_retention_days | INT | NO | | Retensi data dalam hari (-1 = unlimited) |
| is_active | TINYINT(1) | NO | | Status aktif plan, default 1 |
| created_at | TIMESTAMP | NO | | Waktu dibuat |
| updated_at | TIMESTAMP | NO | | Waktu terakhir diperbarui |

#### `mova_customer_configs`
Konfigurasi spesifik per customer (override dari Super Admin).

| Column | Type | Null | Key | Description |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK | Primary key, auto increment |
| customer_id | BIGINT UNSIGNED | NO | FK UQ | Relasi ke `mova_customers.id`, satu baris per customer |
| max_users_override | INT | YES | | Override batas user dari plan (NULL = ikuti plan) |
| enable_supervisor_approval | TINYINT(1) | NO | | Aktifkan chain approval Supervisor, default 0 |
| allowed_modules_override | JSON | YES | | Override modul aktif (NULL = ikuti plan) |
| updated_by | BIGINT UNSIGNED | YES | FK | User Super Admin yang terakhir mengubah |
| updated_at | TIMESTAMP | NO | | Waktu terakhir diperbarui |

### 6.4 Master Kendaraan

#### `mova_vehicles`
Master data unit kendaraan per customer.

| Column | Type | Null | Key | Description |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK | Primary key, auto increment |
| customer_id | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_customers.id` |
| plate_number | VARCHAR(20) | NO | | Nomor plat kendaraan |
| brand | VARCHAR(50) | NO | | Merek kendaraan |
| model | VARCHAR(50) | NO | | Model kendaraan |
| year | YEAR | YES | | Tahun kendaraan |
| color | VARCHAR(30) | YES | | Warna kendaraan |
| vehicle_type | VARCHAR(30) | YES | | Jenis: sedan, mpv, minibus, pickup, dll |
| current_km | INT | NO | | KM odometer saat ini, default 0 |
| status | ENUM | NO | | `active`, `standby`, `maintenance`, `inactive` |
| stnk_expiry | DATE | YES | | Tanggal kadaluarsa STNK |
| kir_expiry | DATE | YES | | Tanggal kadaluarsa KIR |
| is_active | TINYINT(1) | NO | | Status aktif unit, default 1 |
| created_at | TIMESTAMP | NO | | Waktu dibuat |
| updated_at | TIMESTAMP | NO | | Waktu terakhir diperbarui |

### 6.5 Vehicle Request

#### `mova_vehicle_requests`
Data pengajuan request kendaraan oleh karyawan/driver customer.

| Column | Type | Null | Key | Description |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK | Primary key, auto increment |
| customer_id | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_customers.id` |
| request_number | VARCHAR(30) | NO | UQ | Nomor request otomatis (e.g. `REQ-2025-0041`) |
| requested_by | BIGINT UNSIGNED | NO | FK | User yang mengajukan, relasi ke `mova_users.id` |
| department | VARCHAR(80) | YES | | Departemen pengaju |
| destination | VARCHAR(150) | NO | | Kota / lokasi tujuan |
| purpose | TEXT | NO | | Keperluan / keterangan perjalanan |
| departure_date | DATE | NO | | Tanggal berangkat |
| return_date | DATE | NO | | Tanggal kembali |
| passenger_count | INT | NO | | Jumlah penumpang, default 1 |
| vehicle_preference | VARCHAR(50) | YES | | Preferensi jenis unit (opsional) |
| assigned_vehicle_id | BIGINT UNSIGNED | YES | FK | Unit yang diassign, relasi ke `mova_vehicles.id` |
| assigned_driver_id | BIGINT UNSIGNED | YES | FK | Driver yang ditugaskan, relasi ke `mova_users.id` |
| status | ENUM | NO | | `pending`, `approved_l1`, `approved`, `rejected`, `cancelled` |
| approved_by_l1 | BIGINT UNSIGNED | YES | FK | Koordinator yang approve, relasi ke `mova_users.id` |
| approved_at_l1 | TIMESTAMP | YES | | Waktu approve level 1 |
| approved_by_l2 | BIGINT UNSIGNED | YES | FK | Supervisor yang approve (jika aktif), relasi ke `mova_users.id` |
| approved_at_l2 | TIMESTAMP | YES | | Waktu approve level 2 |
| rejected_by | BIGINT UNSIGNED | YES | FK | User yang menolak, relasi ke `mova_users.id` |
| rejection_reason | TEXT | YES | | Alasan penolakan |
| notes | TEXT | YES | | Catatan tambahan |
| created_at | TIMESTAMP | NO | | Waktu request dibuat |
| updated_at | TIMESTAMP | NO | | Waktu terakhir diperbarui |

### 6.6 Trip & Driver Log

#### `mova_trips`
Pencatatan perjalanan / trip kendaraan.

| Column | Type | Null | Key | Description |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK | Primary key, auto increment |
| customer_id | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_customers.id` |
| vehicle_request_id | BIGINT UNSIGNED | YES | FK | Relasi ke `mova_vehicle_requests.id` (opsional) |
| vehicle_id | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_vehicles.id` |
| driver_id | BIGINT UNSIGNED | NO | FK | Driver yang bertugas, relasi ke `mova_users.id` |
| trip_number | VARCHAR(30) | NO | UQ | Nomor trip otomatis (e.g. `TRP-2025-0120`) |
| origin | VARCHAR(100) | NO | | Kota / lokasi keberangkatan |
| destination | VARCHAR(100) | NO | | Kota / lokasi tujuan |
| trip_date | DATE | NO | | Tanggal perjalanan |
| departure_time | TIME | YES | | Waktu berangkat |
| return_time | TIME | YES | | Waktu kembali |
| km_start | INT | YES | | KM odometer saat berangkat |
| km_end | INT | YES | | KM odometer saat kembali |
| distance_km | INT | YES | | Jarak tempuh |
| purpose_type | VARCHAR(50) | NO | | `dinas`, `material`, `karyawan`, `klien`, `lainnya` |
| notes | TEXT | YES | | Catatan tambahan perjalanan |
| status | ENUM | NO | | `draft`, `in_progress`, `completed`, `cancelled` |
| input_by | BIGINT UNSIGNED | NO | FK | User yang input trip, relasi ke `mova_users.id` |
| created_at | TIMESTAMP | NO | | Waktu dibuat |
| updated_at | TIMESTAMP | NO | | Waktu terakhir diperbarui |

### 6.7 Driver Self-Service

#### `mova_trip_checklists`
Checklist kondisi kendaraan pre-trip dan post-trip.

| Column | Type | Null | Key | Description |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK | Primary key, auto increment |
| trip_id | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_trips.id` |
| check_type | ENUM | NO | | `pre_trip` atau `post_trip` |
| submitted_by | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_users.id` |
| submitted_at | TIMESTAMP | NO | | Waktu submit checklist |
| items | JSON | NO | | Array item checklist beserta status dan catatan |
| overall_condition | ENUM | NO | | `good`, `minor_issue`, `major_issue` |
| notes | TEXT | YES | | Catatan umum kondisi kendaraan |
| created_at | TIMESTAMP | NO | | Waktu dibuat |

#### `mova_trip_photos`
Foto dokumentasi kendaraan per trip (pre/post).

| Column | Type | Null | Key | Description |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK | Primary key, auto increment |
| trip_id | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_trips.id` |
| photo_type | ENUM | NO | | `pre_trip` atau `post_trip` |
| position | ENUM | NO | | `front`, `rear`, `left`, `right`, `interior`, `other` |
| file_path | VARCHAR(255) | NO | | Path file foto di server |
| uploaded_by | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_users.id` |
| uploaded_at | TIMESTAMP | NO | | Waktu upload |

#### `mova_issue_reports`
Laporan kerusakan / masalah kendaraan dari driver.

| Column | Type | Null | Key | Description |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK | Primary key, auto increment |
| customer_id | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_customers.id` |
| vehicle_id | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_vehicles.id` |
| trip_id | BIGINT UNSIGNED | YES | FK | Relasi ke `mova_trips.id` (opsional) |
| report_number | VARCHAR(30) | NO | UQ | Nomor laporan otomatis (e.g. `ISS-2025-0018`) |
| reported_by | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_users.id` |
| category | VARCHAR(50) | NO | | `mesin`, `ac_kelistrikan`, `rem_kemudi`, `body`, `ban`, `lainnya` |
| description | TEXT | NO | | Deskripsi detail masalah |
| severity | ENUM | NO | | `low`, `medium`, `high`, `critical` |
| status | ENUM | NO | | `open`, `in_review`, `in_progress`, `resolved`, `closed` |
| photo_paths | JSON | YES | | Array path foto pendukung laporan |
| resolved_at | TIMESTAMP | YES | | Waktu masalah dinyatakan selesai |
| resolved_notes | TEXT | YES | | Catatan penyelesaian masalah |
| created_at | TIMESTAMP | NO | | Waktu laporan dibuat |
| updated_at | TIMESTAMP | NO | | Waktu terakhir diperbarui |

### 6.8 Laporan BBM & Biaya

#### `mova_fuel_reports`
Laporan pengisian BBM per trip oleh driver.

| Column | Type | Null | Key | Description |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK | Primary key, auto increment |
| customer_id | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_customers.id` |
| trip_id | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_trips.id` |
| vehicle_id | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_vehicles.id` |
| reported_by | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_users.id` (driver atau koordinator) |
| fuel_date | DATE | NO | | Tanggal pengisian BBM |
| fuel_type | VARCHAR(20) | NO | | `pertalite`, `pertamax`, `solar`, dll |
| liters | DECIMAL(8,2) | NO | | Jumlah liter BBM |
| price_per_liter | DECIMAL(10,2) | NO | | Harga per liter saat pengisian |
| total_cost | DECIMAL(12,2) | NO | | Total biaya BBM (liters × price_per_liter) |
| km_at_refuel | INT | YES | | KM odometer saat pengisian |
| station_name | VARCHAR(100) | YES | | Nama SPBU / lokasi pengisian |
| receipt_photo | VARCHAR(255) | YES | | Path foto struk / bukti pengisian |
| status | ENUM | NO | | `pending`, `approved`, `rejected` |
| approved_by | BIGINT UNSIGNED | YES | FK | Koordinator yang approve, relasi ke `mova_users.id` |
| approved_at | TIMESTAMP | YES | | Waktu approve |
| rejection_reason | TEXT | YES | | Alasan penolakan |
| notes | TEXT | YES | | Catatan tambahan |
| created_at | TIMESTAMP | NO | | Waktu laporan dibuat |
| updated_at | TIMESTAMP | NO | | Waktu terakhir diperbarui |

#### `mova_expense_reports`
Laporan biaya perjalanan lainnya (tol, parkir, retribusi, dll).

| Column | Type | Null | Key | Description |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK | Primary key, auto increment |
| customer_id | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_customers.id` |
| trip_id | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_trips.id` |
| vehicle_id | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_vehicles.id` |
| reported_by | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_users.id` (driver atau koordinator) |
| expense_date | DATE | NO | | Tanggal pengeluaran |
| category | VARCHAR(50) | NO | | `tol`, `parkir`, `retribusi`, `penyeberangan`, `lainnya` |
| description | VARCHAR(200) | NO | | Keterangan singkat pengeluaran |
| amount | DECIMAL(12,2) | NO | | Nominal biaya dalam Rupiah |
| receipt_photo | VARCHAR(255) | YES | | Path foto struk / bukti pembayaran |
| status | ENUM | NO | | `pending`, `approved`, `rejected` |
| approved_by | BIGINT UNSIGNED | YES | FK | Koordinator yang approve, relasi ke `mova_users.id` |
| approved_at | TIMESTAMP | YES | | Waktu approve |
| rejection_reason | TEXT | YES | | Alasan penolakan |
| created_at | TIMESTAMP | NO | | Waktu laporan dibuat |
| updated_at | TIMESTAMP | NO | | Waktu terakhir diperbarui |

### 6.9 Maintenance

#### `mova_maintenance_schedules`
Jadwal servis dan perawatan kendaraan.

| Column | Type | Null | Key | Description |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK | Primary key, auto increment |
| customer_id | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_customers.id` |
| vehicle_id | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_vehicles.id` |
| service_type | VARCHAR(80) | NO | | Jenis servis (e.g. \"Ganti Oli\", \"Servis 20.000 KM\") |
| trigger_type | ENUM | NO | | `km_based` atau `date_based` |
| km_threshold | INT | YES | | KM batas servis (jika `km_based`) |
| scheduled_date | DATE | YES | | Tanggal jadwal servis (jika `date_based`) |
| reminder_days_before | INT | NO | | Berapa hari sebelum jadwal kirim notifikasi, default 7 |
| status | ENUM | NO | | `scheduled`, `overdue`, `in_progress`, `completed`, `cancelled` |
| notes | TEXT | YES | | Catatan jadwal servis |
| created_by | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_users.id` |
| created_at | TIMESTAMP | NO | | Waktu dibuat |
| updated_at | TIMESTAMP | NO | | Waktu terakhir diperbarui |

#### `mova_maintenance_logs`
Riwayat / histori servis yang sudah dilakukan.

| Column | Type | Null | Key | Description |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK | Primary key, auto increment |
| schedule_id | BIGINT UNSIGNED | YES | FK | Relasi ke `mova_maintenance_schedules.id` (opsional) |
| customer_id | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_customers.id` |
| vehicle_id | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_vehicles.id` |
| service_type | VARCHAR(80) | NO | | Jenis servis yang dilakukan |
| service_date | DATE | NO | | Tanggal servis dilakukan |
| km_at_service | INT | YES | | KM kendaraan saat servis |
| workshop_name | VARCHAR(100) | YES | | Nama bengkel / tempat servis |
| cost | DECIMAL(12,2) | YES | | Biaya servis (informasi) |
| notes | TEXT | YES | | Catatan hasil servis |
| next_service_km | INT | YES | | Target KM servis berikutnya |
| next_service_date | DATE | YES | | Target tanggal servis berikutnya |
| logged_by | BIGINT UNSIGNED | NO | FK | Relasi ke `mova_users.id` |
| created_at | TIMESTAMP | NO | | Waktu log dibuat |

### 6.10 Notifikasi

#### `mova_notifications`
Log semua notifikasi yang dikirim sistem. Bersifat polymorphic — `reference_type` dan `reference_id` dapat merujuk ke tabel manapun.

| Column | Type | Null | Key | Description |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | NO | PK | Primary key, auto increment |
| user_id | BIGINT UNSIGNED | NO | FK | Penerima notifikasi, relasi ke `mova_users.id` |
| customer_id | BIGINT UNSIGNED | YES | FK | Konteks customer terkait, relasi ke `mova_customers.id` |
| type | VARCHAR(50) | NO | | `vehicle_request`, `fuel_report`, `maintenance`, `issue`, `trip` |
| title | VARCHAR(150) | NO | | Judul notifikasi |
| message | TEXT | NO | | Isi pesan notifikasi |
| channel | ENUM | NO | | `in_app`, `email`, `telegram` |
| reference_type | VARCHAR(50) | YES | | Nama tabel yang dirujuk (polymorphic) |
| reference_id | BIGINT UNSIGNED | YES | | ID record yang dirujuk (polymorphic) |
| is_read | TINYINT(1) | NO | | Status baca untuk `in_app`, default 0 |
| sent_at | TIMESTAMP | YES | | Waktu notifikasi berhasil dikirim |
| failed_at | TIMESTAMP | YES | | Waktu gagal kirim |
| created_at | TIMESTAMP | NO | | Waktu dibuat |

---

## 7. Tech Stack & Infrastruktur

| Komponen | Teknologi | Keterangan |
|---|---|---|
| Frontend | HTML5 + CSS3 + Vanilla JS (PWA) | Responsive, installable di mobile via \"Add to Home Screen\" |
| Backend | Native PHP 8.x | REST API pattern, modular per fitur |
| Database | MySQL 8.0 / MariaDB 10.6+ | Multi-tenant single database, isolasi via `customer_id` |
| Autentikasi | PHP Session + JWT Token | Session untuk web, JWT untuk API calls |
| Email Notifikasi | PHPMailer + SMTP | Gratis menggunakan SMTP Gmail/Outlook |
| Telegram Notifikasi | Telegram Bot API | Gratis tanpa batas, via `sendMessage` endpoint |
| File Upload | PHP native file handling | Foto checklist, struk BBM, foto kerusakan |
| Hosting | Hostinger VPS KVM2 | LAMP stack (Linux + Apache + MySQL + PHP) |
| Version Control | Git + GitHub | Repository privat, deployment via git pull |

---

## 8. Roadmap Pengembangan

| Phase | Estimasi | Fokus | Deliverable |
|---|---|---|---|
| Phase 1 | Bulan 1–4 | Free Tier — core modules | Vehicle Request, Trip Log, Driver Self-Service, BBM & Biaya, Maintenance Reminder, Company Panel |
| Phase 2 | Bulan 5–6 | Premium Tier — analytics & export | Cost Analytics lengkap, Export PDF/Excel, Supervisor approval, Notifikasi Email & Telegram |
| Phase 3 | Bulan 7–9 | Enterprise + GPS Integration | GPS Monitoring, Custom branding, API publik, SSO, White-label option |
| Phase 4 | Bulan 10+ | Mobile App Native | Android APK untuk Driver App, optimasi PWA, push notification native |

---

## 9. Catatan Implementasi

### 9.1 Prioritas Development Phase 1

| Urutan | Modul |
|---|---|
| 1 | Setup & Auth — Multi-tenant session, login per layer, middleware access control berdasarkan `customer_id` & `branch_id` |
| 2 | Master Data — CRUD Region, Branch, Customer, Vehicle, User — dikelola dari Company Panel |
| 3 | Vehicle Request — Form request, approval flow, status tracker, notifikasi in-app |
| 4 | Trip & Driver Log — Input trip (manual & dari approved request), tabel log, filter & search |
| 5 | Driver Module — Checklist, foto upload, laporan BBM, laporan biaya, laporan kerusakan |
| 6 | Maintenance — Jadwal servis, reminder logic, history log |
| 7 | Dashboard — Ringkasan per role, stat cards, aktivitas terbaru, status armada |

### 9.2 Konvensi Penting

- Semua query database **WAJIB** menyertakan filter `WHERE customer_id = ?` untuk mencegah data bocor lintas tenant.
- Semua upload file disimpan di folder terstruktur: `/uploads/{customer_id}/{module}/{year}/{month}/`.
- Nomor otomatis (request, trip, issue) menggunakan format: `[PREFIX]-[YEAR]-[4DIGIT_SEQUENCE]`, di-generate sisi server.
- Semua perubahan status penting (approval, rejection, assignment) wajib tercatat di `mova_notifications`.
- Password disimpan dalam format bcrypt (`password_hash` PHP), tidak pernah plain text.
- Setiap API endpoint Company Layer **HARUS** memvalidasi `branch_id` user sebelum mengizinkan akses ke data customer.

---

*© PT. Bumi Jasa Utama / Kalla Transport & Logistics — Confidential*
