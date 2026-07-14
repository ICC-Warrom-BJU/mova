# Panduan Deploy MOVA ke Railway (Free Tier)

Panduan ini untuk deploy **prototype MOVA** secara gratis ke Railway.
Target: Management bisa akses aplikasi via URL `https://mova-namaproject.railway.app`.

---

## Daftar Isi

1. [Buat Akun Railway](#1-buat-akun-railway)
2. [Deploy Aplikasi dari GitHub](#2-deploy-aplikasi-dari-github)
3. [Tambah Database MySQL](#3-tambah-database-mysql)
4. [Set Variabel Lingkungan (Environment Variables)](#4-set-variabel-lingkungan-environment-variables)
5. [Import Database (Migration)](#5-import-database-migration)
6. [Akses Aplikasi](#6-akses-aplikasi)
7. [Deploy Ulang Setelah Update](#7-deploy-ulang-setelah-update)
8. [Troubleshooting](#8-troubleshooting)

---

## 1. Buat Akun Railway

1. Buka https://railway.app
2. Klik **Login with GitHub**
   - Railway akan minta izin akses ke GitHub kamu — klik **Authorize**
3. Setelah login, kamu akan masuk ke Dashboard Railway (kosong)

---

## 2. Deploy Aplikasi dari GitHub

### 2.1 Persiapkan Repo

Pastikan repo `mova` sudah di-push ke GitHub dengan branch `dev` yang berisi file ini:

```
mova/
├── nixpacks.toml        # SUDAH ADA
├── config/
│   ├── .env.example
│   └── database.php     # SUDAH DIUPDATE (support MYSQL_URL)
└── ... (file lainnya)
```

### 2.2 Deploy ke Railway

1. Di Dashboard Railway, klik tombol **New Project** (kanan atas)
2. Pilih **Deploy from GitHub repo**
   - Jika pertama kali, Railway minta install **Railway GitHub App**
   - Klik **Install & Authorize**
   - Pilih repo `ICC-Warrom-BJU/mova`
   - Klik **Install**
3. Setelah terinstall, Railway akan otomatis:
   - Membaca file `nixpacks.toml`
   - Menginstall **PHP 8.2** dengan ekstensi `pdo_mysql` dan `mbstring`
   - Menjalankan start command: `php -S ... -t public public/router.php`
4. Proses build akan terlihat di log (butuh ~2-3 menit)
   - **Tunggu sampai muncul tulisan** `deploy complete`
5. Setelah selesai, Railway kasih URL random: `https://mova-[random].railway.app`
   - **Jangan dibuka dulu** — karena database belum siap

---

## 3. Tambah Database MySQL

1. Di project yang sama, klik tombol **+ New** (sebelah kanan project name)
2. Pilih **Database** → **Add MySQL**
   - Railway akan membuat container MySQL baru
   - Proses butuh ~1 menit
3. Setelah jadi, kamu akan lihat **MySQL** sebagai service baru di dashboard

### 3.1 Cek Koneksi Database

1. Klik service **MySQL**
2. Buka tab **Connect**
3. Kamu akan lihat **Connection URL** seperti:
   ```
   mysql://root:xxxxxxxxxxxx@roundhouse.proxy.rlwy.net:12345/railway
   ```
4. Catat URL ini — tapi tidak perlu di-copy manual, Railway otomatis menyediakan **Environment Variable** bernama `MYSQL_URL` yang bisa dibaca oleh aplikasi PHP kita.

---

## 4. Set Variabel Lingkungan (Environment Variables)

Selain `MYSQL_URL` yang sudah auto-set oleh Railway MySQL, kita perlu menambah beberapa variabel lain:

1. Klik service **mova** (bukan MySQL) — service yang menjalankan PHP
2. Buka tab **Variables**
3. Klik **New Variable**
4. Tambah satu per satu:

| Key | Value | Contoh |
|---|---|---|
| `APP_ENV` | `production` | `production` |
| `APP_URL` | URL aplikasi | `https://mova-[random].railway.app` |
| `SESSION_LIFETIME` | `28800` | `28800` |
| `TELEGRAM_BOT_TOKEN` | (kosongkan dulu) | |
| `SMTP_HOST` | (kosongkan dulu) | |
| `SMTP_PORT` | (kosongkan dulu) | |
| `SMTP_USER` | (kosongkan dulu) | |
| `SMTP_PASS` | (kosongkan dulu) | |
| `SMTP_FROM` | (kosongkan dulu) | |

> **Catatan:** `MYSQL_URL` sudah ada secara otomatis setelah kita menambahkan MySQL. Tidak perlu ditambah manual.

5. Setelah selesai, daftar Variables akan terlihat seperti ini:

```
APP_ENV              production
APP_URL              https://mova-[random].railway.app
MYSQL_URL            mysql://root:xxx@roundhouse.proxy.rlwy.net:12345/railway
SESSION_LIFETIME     28800
SMTP_FROM            noreply@mova.com
TELEGRAM_BOT_TOKEN
...
```

### 4.1 Cara Cek Apakah Database Terkoneksi ke Aplikasi

Setiap service Railway punya tab **Variables** sendiri. Pastikan service **mova** (PHP app) punya akses ke `MYSQL_URL`:

- Jika MySQL dan App dalam **satu project**, Railway otomatis membagikan environment variable antar service.
- Tidak perlu konfigurasi tambahan.

---

## 5. Import Database (Migration)

Ada **2 cara** untuk menjalankan migration:

### Cara A: Via Railway CLI (Rekomendasi)

#### 5.1 Install Railway CLI

Buka terminal (PowerShell/CMD) di laptop kamu:

```powershell
# Install Railway CLI via npm
npm install -g @railway/cli

# Atau via PowerShell (Windows):
iwr -Uri https://railway.app/install.ps1 -UseBasicParsing | iex
```

#### 5.2 Login Railway CLI

```powershell
railway login
```
- Akan terbuka browser — klik **Authorize**
- Kembali ke terminal

#### 5.3 Link Project

```powershell
railway link
```
- Pilih project `mova` dari daftar
- Pilih service **MySQL**

#### 5.4 Jalankan Migration

Buka terminal di folder `mova/` (project   root):

```powershell
# Jalankan migration SQL
railway run "php migrations/run.php"
```

Perintah ini akan:
- Connect ke database MySQL Railway
- Membuat tabel `mova_migrations` (tracking)
- Menjalankan semua file `*.sql` di folder `migrations/` secara urut

#### 5.5 (Opsional) Seed Data Awal

```powershell
railway run "php migrations/seed.php"
```

Contoh output jika sukses:
```
=== MOVA Seed Data ===
[OK] Region: Sulawesi Selatan (ID: 1)
[OK] Branch: Makassar (ID: 1)
[OK] Customer: PT. Niaga Cipta Abadi (ID: 1)
...
```

---

### Cara B: Via MySQL Client (Alternatif)

Jika tidak bisa install Railway CLI, pakai cara manual:

1. Klik service **MySQL** di Railway
2. Buka tab **Connect**
3. Pilih **Public Network** → aktifkan **Publicly Accessible** (ada peringatan — ini aman untuk prototype, tapi matikan setelah selesai import)
4. Copy connection string, lalu connect dari laptop:

**Menggunakan TablePlus / DBeaver (GUI):**
- Host: `roundhouse.proxy.rlwy.net`
- Port: `12345` (lihat dari connection string)
- User: `root`
- Password: (lihat dari connection string)
- Database: `railway`

**Menggunakan Command Line:**
```powershell
mysql -h roundhouse.proxy.rlwy.net -P 12345 -u root -p railway
```
Masukkan password dari connection string.

Setelah connect, jalankan:

```sql
source migrations/001_foundation.sql;
source migrations/002_branches.sql;
source migrations/003_customers.sql;
... (dan seterusnya sampai 012_config_options.sql)
```

> **PENTING:** Jalankan file SQL **sesuai urutan** — dari 001 sampai 012.

---

## 6. Akses Aplikasi

### 6.1 Redeploy Aplikasi

Setelah migration selesai, aplikasi perlu di-restart agar koneksi database terbaca:

1. Klik service **mova** (PHP app)
2. Buka tab **Deployments**
3. Klik tombol **Redeploy** (icon 🔄)
4. Tunggu sampai status **Deploy Success**

### 6.2 Buka URL

1. Klik service **mova**
2. Buka tab **Settings** (atau langsung lihat **URL** di dashboard atas)
3. Klik URL: `https://mova-[random].railway.app`
4. Halaman login MOVA akan muncul

### 6.3 Login Pertama

- Username/Password tergantung data dari seed yang sudah dijalankan
- Jika sudah seed, coba:
  - **Login sebagai Super Admin:** (cek output seed.php untuk credential)
  - **Login sebagai Customer:** (cek output seed.php)

---

## 7. Deploy Ulang Setelah Update

Setiap kali kamu push perubahan ke branch `dev`:

```bash
git add -A
git commit -m "fitur baru yang kamu buat"
git push origin dev
```

Railway akan otomatis:
1. Mendeteksi push baru
2. Membuild ulang
3. Redeploy (biasanya selesai < 1 menit)

Tidak perlu setting apapun — sudah **auto-deploy** dari GitHub.

---

## 8. Troubleshooting

### ❌ Aplikasi Error "Connection refused" atau "Unknown database"

**Penyebab:** Migration belum dijalankan, atau environment variable `MYSQL_URL` tidak terbaca.

**Solusi:**
1. Cek tab **Variables** di service **mova** — pastikan ada `MYSQL_URL`
2. Jika tidak ada, tambah manual:
   - Buka service **MySQL** → tab **Connect** → copy **Connection URL**
   - Buka service **mova** → tab **Variables** → **New Variable**
   - Key: `MYSQL_URL`, Value: paste URL tadi
3. Redeploy

### ❌ Aplikasi muncul "404 Not Found"

**Penyebab:** Route tidak cocok, atau document root salah.

**Solusi:**
- Pastikan `nixpacks.toml` ada di root repo
- Start command harus: `php -S 0.0.0.0:$PORT -t public public/router.php`

### ❌ Build gagal dengan error PHP extension

**Penyebab:** Ekstensi PHP tidak tersedia.

**Solusi:**
- Cek file `nixpacks.toml` — pastikan ada `php82Extensions.pdo_mysql` dan `php82Extensions.mbstring`

### ❌ Seed data error "Table already exists"

**Penyebab:** Migration sudah dijalankan sebelumnya.

**Solusi:**
- Seed saja tanpa migration. Atau drop database dan ulang dari migration tahap awal.

### ❌ Railway CLI tidak terinstall

Coba alternatif:
- **Cara B** (MySQL client manual) — lihat bagian 5
- Atau install WSL dan coba via Linux

---

## Ringkasan Biaya (Free Tier)

| Service | Biaya |
|---|---|
| PHP App | ~$0.5/bulan (dari $5 credit gratis) |
| MySQL Database | ~$2/bulan (dari $5 credit gratis) |
| **Total** | **Gratis** selama masih dalam $5 credit/bulan |

Railway memberi **$5 credit gratis per bulan** — cukup untuk prototype.
Setelah lebih, tinggal upgrade atau pindah ke Hostinger VPS (infrastruktur final).

---

## Yang Harus Dihindari

- ❌ **Jangan commit** `config/.env` (sudah di gitignore)
- ❌ **Jangan aktifkan Public Network MySQL** secara permanen — hanya untuk import, lalu matikan
- ❌ **Jangan push secret key / token Telegram** ke GitHub — set via Railway Variables saja

---

*Dibuat: Juli 2026 — untuk prototype MOVA*  
*Dokumen ini ada di `docs/DEPLOY-RAILWAY.md`*
