# MOVA — Entity Relationship Diagram

Dokumen ini berisi ERD lengkap platform **MOVA** (Fleet & Driver Management Platform) dalam format [Mermaid](https://mermaid.js.org/syntax/entityRelationshipDiagram.html), sehingga dapat langsung di-render oleh GitHub, GitLab, Notion, mkdocs, atau tools ERD lain, maupun diparsing otomatis oleh sistem code-generation (migration builder, ORM scaffolding, dsb).

- **Database engine**: MySQL 8.0 / MariaDB 10.6+
- **Prefix tabel**: seluruh tabel menggunakan prefix `mova_`
- **Isolasi multi-tenant**: setiap tabel transaksional wajib memiliki `customer_id` sebagai filter query
- **Referensi lengkap**: lihat `MOVA-PRD-v1.0.docx` bagian 6 untuk deskripsi kolom per baris

---

## 1. Struktur Organisasi & Master Data

Mencakup hierarki Region → Branch → Customer, Subscription Plan, Role & User, serta Master Kendaraan.

```mermaid
erDiagram
  REGIONS ||--o{ BRANCHES : "contains"
  BRANCHES ||--o{ CUSTOMERS : "contains"
  BRANCHES ||--o{ USER_BRANCH_ACCESS : "scopes staff"
  SUBSCRIPTION_PLANS ||--o{ CUSTOMERS : "assigned to"
  CUSTOMERS ||--o| CUSTOMER_CONFIGS : "overrides via"
  CUSTOMERS ||--o{ USERS : "employs"
  CUSTOMERS ||--o{ VEHICLES : "owns"
  ROLES ||--o{ USERS : "assigned to"
  USERS ||--o{ USER_BRANCH_ACCESS : "granted via"
  USERS ||--o{ CUSTOMER_CONFIGS : "last updated by"

  REGIONS {
    bigint id PK "Primary key, auto increment"
    varchar name "Nama region"
    varchar code UK "Kode unik region"
    boolean is_active "Status aktif, default 1"
    timestamp created_at
    timestamp updated_at
  }

  BRANCHES {
    bigint id PK "Primary key, auto increment"
    bigint region_id FK "Relasi ke mova_regions.id"
    varchar name "Nama branch"
    varchar code UK "Kode unik branch"
    text address "Alamat lengkap"
    varchar phone "Nomor telepon branch"
    boolean is_active "Status aktif, default 1"
    timestamp created_at
    timestamp updated_at
  }

  CUSTOMERS {
    bigint id PK "Primary key, auto increment"
    bigint branch_id FK "Relasi ke mova_branches.id"
    bigint subscription_plan_id FK "Relasi ke mova_subscription_plans.id"
    varchar name "Nama perusahaan customer"
    varchar code UK "Kode unik customer"
    varchar pic_name "Nama PIC / contact person"
    varchar pic_phone "Nomor telepon PIC"
    varchar pic_email "Email PIC"
    date contract_start "Tanggal mulai kontrak"
    date contract_end "Tanggal akhir kontrak"
    int total_units "Jumlah unit kendaraan kontrak"
    boolean is_active "Status aktif, default 1"
    timestamp created_at
    timestamp updated_at
  }

  SUBSCRIPTION_PLANS {
    bigint id PK "Primary key, auto increment"
    varchar name UK "free / premium / enterprise"
    int max_users "Batas user per customer, -1 = unlimited"
    json allowed_modules "Array nama modul yang dapat diakses"
    int data_retention_days "Retensi data dalam hari, -1 = unlimited"
    boolean is_active "Status aktif plan, default 1"
    timestamp created_at
    timestamp updated_at
  }

  CUSTOMER_CONFIGS {
    bigint id PK "Primary key, auto increment"
    bigint customer_id FK "Relasi ke mova_customers.id, satu baris per customer"
    int max_users_override "Override batas user, NULL = ikuti plan"
    boolean enable_supervisor_approval "Aktifkan chain approval Supervisor, default 0"
    json allowed_modules_override "Override modul aktif, NULL = ikuti plan"
    bigint updated_by FK "User Super Admin terakhir mengubah"
    timestamp updated_at
  }

  ROLES {
    bigint id PK "Primary key, auto increment"
    varchar name UK "super_admin, management, operation, marketing, manager, supervisor, koordinator, driver"
    varchar layer "Nilai: company atau customer"
    text description "Deskripsi kewenangan role"
    timestamp created_at
  }

  USERS {
    bigint id PK "Primary key, auto increment"
    bigint role_id FK "Relasi ke mova_roles.id"
    bigint customer_id FK "Relasi ke mova_customers.id, NULL jika Company Layer"
    varchar name "Nama lengkap user"
    varchar email UK "Digunakan sebagai username login"
    varchar password "Bcrypt hashed password"
    varchar phone "Nomor WhatsApp / telepon"
    varchar telegram_chat_id "Chat ID Telegram untuk notifikasi"
    varchar avatar "Path foto profil"
    boolean is_active "Status aktif user, default 1"
    timestamp last_login_at
    timestamp created_at
    timestamp updated_at
  }

  USER_BRANCH_ACCESS {
    bigint id PK "Primary key, auto increment"
    bigint user_id FK "Relasi ke mova_users.id"
    bigint branch_id FK "Relasi ke mova_branches.id"
    timestamp created_at
  }

  VEHICLES {
    bigint id PK "Primary key, auto increment"
    bigint customer_id FK "Relasi ke mova_customers.id"
    varchar plate_number "Nomor plat kendaraan"
    varchar brand "Merek kendaraan"
    varchar model "Model kendaraan"
    year year "Tahun kendaraan"
    varchar color "Warna kendaraan"
    varchar vehicle_type "sedan, mpv, minibus, pickup, dll"
    int current_km "KM odometer saat ini, default 0"
    enum status "active, standby, maintenance, inactive"
    date stnk_expiry "Tanggal kadaluarsa STNK"
    date kir_expiry "Tanggal kadaluarsa KIR"
    boolean is_active "Status aktif unit, default 1"
    timestamp created_at
    timestamp updated_at
  }
```

---

## 2. Data Operasional

Mencakup Vehicle Request, Trip & Driver Log, Driver Self-Service (checklist, foto, laporan kerusakan), Laporan BBM & Biaya, serta Maintenance.

```mermaid
erDiagram
  VEHICLES ||--o{ VEHICLE_REQUESTS : "assigned to"
  VEHICLE_REQUESTS ||--o| TRIPS : "generates"
  VEHICLES ||--o{ TRIPS : "used in"
  TRIPS ||--o{ TRIP_CHECKLISTS : "checked via"
  TRIPS ||--o{ TRIP_PHOTOS : "documented by"
  TRIPS ||--o{ FUEL_REPORTS : "logs"
  TRIPS ||--o{ EXPENSE_REPORTS : "logs"
  VEHICLES ||--o{ ISSUE_REPORTS : "reported for"
  TRIPS ||--o{ ISSUE_REPORTS : "reported during"
  VEHICLES ||--o{ MAINTENANCE_SCHEDULES : "scheduled for"
  MAINTENANCE_SCHEDULES ||--o{ MAINTENANCE_LOGS : "fulfilled by"
  VEHICLES ||--o{ MAINTENANCE_LOGS : "serviced"

  VEHICLE_REQUESTS {
    bigint id PK "Primary key, auto increment"
    bigint customer_id FK "Relasi ke mova_customers.id"
    varchar request_number UK "Nomor otomatis, format REQ-YYYY-NNNN"
    bigint requested_by FK "Relasi ke mova_users.id"
    varchar department "Departemen pengaju"
    varchar destination "Kota / lokasi tujuan"
    text purpose "Keperluan / keterangan perjalanan"
    date departure_date
    date return_date
    int passenger_count "Default 1"
    varchar vehicle_preference "Preferensi jenis unit, opsional"
    bigint assigned_vehicle_id FK "Relasi ke mova_vehicles.id"
    bigint assigned_driver_id FK "Relasi ke mova_users.id"
    enum status "pending, approved_l1, approved, rejected, cancelled"
    bigint approved_by_l1 FK "Koordinator, relasi ke mova_users.id"
    timestamp approved_at_l1
    bigint approved_by_l2 FK "Supervisor jika aktif, relasi ke mova_users.id"
    timestamp approved_at_l2
    bigint rejected_by FK "Relasi ke mova_users.id"
    text rejection_reason
    text notes
    timestamp created_at
    timestamp updated_at
  }

  TRIPS {
    bigint id PK "Primary key, auto increment"
    bigint customer_id FK "Relasi ke mova_customers.id"
    bigint vehicle_request_id FK "Opsional, relasi ke mova_vehicle_requests.id"
    bigint vehicle_id FK "Relasi ke mova_vehicles.id"
    bigint driver_id FK "Relasi ke mova_users.id"
    varchar trip_number UK "Nomor otomatis, format TRP-YYYY-NNNN"
    varchar origin "Lokasi keberangkatan"
    varchar destination "Lokasi tujuan"
    date trip_date
    time departure_time
    time return_time
    int km_start "KM odometer saat berangkat"
    int km_end "KM odometer saat kembali"
    int distance_km "Jarak tempuh"
    varchar purpose_type "dinas, material, karyawan, klien, lainnya"
    text notes
    enum status "draft, in_progress, completed, cancelled"
    bigint input_by FK "Driver atau Koordinator, relasi ke mova_users.id"
    timestamp created_at
    timestamp updated_at
  }

  TRIP_CHECKLISTS {
    bigint id PK "Primary key, auto increment"
    bigint trip_id FK "Relasi ke mova_trips.id"
    enum check_type "pre_trip atau post_trip"
    bigint submitted_by FK "Relasi ke mova_users.id"
    timestamp submitted_at
    json items "Array item checklist beserta status dan catatan"
    enum overall_condition "good, minor_issue, major_issue"
    text notes
    timestamp created_at
  }

  TRIP_PHOTOS {
    bigint id PK "Primary key, auto increment"
    bigint trip_id FK "Relasi ke mova_trips.id"
    enum photo_type "pre_trip atau post_trip"
    enum position "front, rear, left, right, interior, other"
    varchar file_path "Path file foto di server"
    bigint uploaded_by FK "Relasi ke mova_users.id"
    timestamp uploaded_at
  }

  ISSUE_REPORTS {
    bigint id PK "Primary key, auto increment"
    bigint customer_id FK "Relasi ke mova_customers.id"
    bigint vehicle_id FK "Relasi ke mova_vehicles.id"
    bigint trip_id FK "Opsional, relasi ke mova_trips.id"
    varchar report_number UK "Nomor otomatis, format ISS-YYYY-NNNN"
    bigint reported_by FK "Relasi ke mova_users.id"
    varchar category "mesin, ac_kelistrikan, rem_kemudi, body, ban, lainnya"
    text description
    enum severity "low, medium, high, critical"
    enum status "open, in_review, in_progress, resolved, closed"
    json photo_paths "Array path foto pendukung"
    timestamp resolved_at
    text resolved_notes
    timestamp created_at
    timestamp updated_at
  }

  FUEL_REPORTS {
    bigint id PK "Primary key, auto increment"
    bigint customer_id FK "Relasi ke mova_customers.id"
    bigint trip_id FK "Relasi ke mova_trips.id"
    bigint vehicle_id FK "Relasi ke mova_vehicles.id"
    bigint reported_by FK "Driver atau Koordinator, relasi ke mova_users.id"
    date fuel_date
    varchar fuel_type "pertalite, pertamax, solar, dll"
    decimal liters "Jumlah liter BBM"
    decimal price_per_liter "Harga per liter saat pengisian"
    decimal total_cost "liters x price_per_liter"
    int km_at_refuel "KM odometer saat pengisian"
    varchar station_name "Nama SPBU / lokasi"
    varchar receipt_photo "Path foto struk"
    enum status "pending, approved, rejected"
    bigint approved_by FK "Koordinator, relasi ke mova_users.id"
    timestamp approved_at
    text rejection_reason
    text notes
    timestamp created_at
    timestamp updated_at
  }

  EXPENSE_REPORTS {
    bigint id PK "Primary key, auto increment"
    bigint customer_id FK "Relasi ke mova_customers.id"
    bigint trip_id FK "Relasi ke mova_trips.id"
    bigint vehicle_id FK "Relasi ke mova_vehicles.id"
    bigint reported_by FK "Driver atau Koordinator, relasi ke mova_users.id"
    date expense_date
    varchar category "tol, parkir, retribusi, penyeberangan, lainnya"
    varchar description "Keterangan singkat pengeluaran"
    decimal amount "Nominal biaya dalam Rupiah"
    varchar receipt_photo "Path foto struk / bukti pembayaran"
    enum status "pending, approved, rejected"
    bigint approved_by FK "Koordinator, relasi ke mova_users.id"
    timestamp approved_at
    text rejection_reason
    timestamp created_at
    timestamp updated_at
  }

  MAINTENANCE_SCHEDULES {
    bigint id PK "Primary key, auto increment"
    bigint customer_id FK "Relasi ke mova_customers.id"
    bigint vehicle_id FK "Relasi ke mova_vehicles.id"
    varchar service_type "e.g. Ganti Oli, Servis 20.000 KM"
    enum trigger_type "km_based atau date_based"
    int km_threshold "KM batas servis, jika trigger km_based"
    date scheduled_date "Tanggal jadwal, jika trigger date_based"
    int reminder_days_before "Default 7 hari"
    enum status "scheduled, overdue, in_progress, completed, cancelled"
    text notes
    bigint created_by FK "Relasi ke mova_users.id"
    timestamp created_at
    timestamp updated_at
  }

  MAINTENANCE_LOGS {
    bigint id PK "Primary key, auto increment"
    bigint schedule_id FK "Opsional, relasi ke mova_maintenance_schedules.id"
    bigint customer_id FK "Relasi ke mova_customers.id"
    bigint vehicle_id FK "Relasi ke mova_vehicles.id"
    varchar service_type "Jenis servis yang dilakukan"
    date service_date
    int km_at_service "KM kendaraan saat servis"
    varchar workshop_name "Nama bengkel / tempat servis"
    decimal cost "Biaya servis, informasi"
    text notes
    int next_service_km "Target KM servis berikutnya"
    date next_service_date "Target tanggal servis berikutnya"
    bigint logged_by FK "Relasi ke mova_users.id"
    timestamp created_at
  }
```

---

## 3. Notifikasi (Tabel Polymorphic)

`mova_notifications` sengaja dipisah dari dua diagram di atas karena bersifat **polymorphic** — kolom `reference_type` dan `reference_id` dapat merujuk ke tabel manapun (vehicle_requests, fuel_reports, maintenance_schedules, issue_reports, dll), sehingga tidak digambarkan sebagai FK tetap.

```mermaid
erDiagram
  NOTIFICATIONS {
    bigint id PK "Primary key, auto increment"
    bigint user_id FK "Penerima notifikasi, relasi ke mova_users.id"
    bigint customer_id FK "Konteks customer terkait, relasi ke mova_customers.id"
    varchar type "vehicle_request, fuel_report, maintenance, issue, trip"
    varchar title "Judul notifikasi"
    text message "Isi pesan notifikasi"
    enum channel "in_app, email, telegram"
    varchar reference_type "Nama tabel yang dirujuk, polymorphic"
    bigint reference_id "ID record yang dirujuk, polymorphic"
    boolean is_read "Default 0"
    timestamp sent_at
    timestamp failed_at
    timestamp created_at
  }
```

**Aturan polymorphic reference:**

| `reference_type` | Tabel yang dirujuk |
|---|---|
| `vehicle_request` | `mova_vehicle_requests` |
| `fuel_report` | `mova_fuel_reports` |
| `expense_report` | `mova_expense_reports` |
| `maintenance_schedule` | `mova_maintenance_schedules` |
| `issue_report` | `mova_issue_reports` |
| `trip` | `mova_trips` |

---

## 4. Ringkasan Relasi Antar Tabel

| Tabel Induk | Relasi | Tabel Anak | Kardinalitas |
|---|---|---|---|
| `mova_regions` | contains | `mova_branches` | 1 → N |
| `mova_branches` | contains | `mova_customers` | 1 → N |
| `mova_branches` | scopes staff | `mova_user_branch_access` | 1 → N |
| `mova_subscription_plans` | assigned to | `mova_customers` | 1 → N |
| `mova_customers` | overrides via | `mova_customer_configs` | 1 → 1 |
| `mova_customers` | employs | `mova_users` | 1 → N |
| `mova_customers` | owns | `mova_vehicles` | 1 → N |
| `mova_roles` | assigned to | `mova_users` | 1 → N |
| `mova_users` | granted via | `mova_user_branch_access` | 1 → N |
| `mova_vehicles` | assigned to | `mova_vehicle_requests` | 1 → N |
| `mova_vehicle_requests` | generates | `mova_trips` | 1 → 0/1 |
| `mova_vehicles` | used in | `mova_trips` | 1 → N |
| `mova_trips` | checked via | `mova_trip_checklists` | 1 → N |
| `mova_trips` | documented by | `mova_trip_photos` | 1 → N |
| `mova_trips` | logs | `mova_fuel_reports` | 1 → N |
| `mova_trips` | logs | `mova_expense_reports` | 1 → N |
| `mova_vehicles` | reported for | `mova_issue_reports` | 1 → N |
| `mova_vehicles` | scheduled for | `mova_maintenance_schedules` | 1 → N |
| `mova_maintenance_schedules` | fulfilled by | `mova_maintenance_logs` | 1 → N |
| `mova_users` | receives | `mova_notifications` | 1 → N |

---

## 5. Catatan untuk Sistem / Code Generation

- Semua tabel transaksional (`vehicle_requests`, `trips`, `fuel_reports`, `expense_reports`, `issue_reports`, `maintenance_schedules`, `maintenance_logs`, `vehicles`) **wajib** memiliki kolom `customer_id` dan setiap query harus difilter berdasarkan kolom ini.
- Kolom bertipe `PK` adalah `BIGINT UNSIGNED AUTO_INCREMENT`.
- Kolom bertipe `FK` mengikuti tipe data kolom `PK` yang dirujuk (`BIGINT UNSIGNED`).
- Kolom `UK` (Unique Key) wajib memiliki index unique di level database.
- Kolom `json` memakai tipe native `JSON` (MySQL 5.7+ / MariaDB 10.2+).
- Kolom `enum` memakai tipe native `ENUM(...)`, nilai yang valid tercantum di kolom deskripsi.
- Semua tabel disarankan memiliki `created_at` dan `updated_at` kecuali dinyatakan lain.
- Nomor otomatis (`request_number`, `trip_number`, `report_number`) di-generate di sisi server dengan format `[PREFIX]-[YEAR]-[4DIGIT_SEQUENCE]`.

---

*Dokumen ini adalah pendamping teknis dari `MOVA-PRD-v1.0.docx`. Untuk deskripsi bisnis, alur approval, dan subscription plan, rujuk ke dokumen PRD utama.*
