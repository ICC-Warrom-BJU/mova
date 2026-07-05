# MOVA — Fleet & Driver Management Platform

**MOVA** adalah platform Fleet & Driver Management berbasis web SaaS multi-tenant oleh PT. Bumi Jasa Utama (BJU) / Kalla Transport & Logistics. Diberikan gratis sebagai nilai tambah untuk customer rental kendaraan BJU.

## Tech Stack

| Layer | Teknologi |
|---|---|
| Backend | Native PHP 8.x |
| Database | MySQL 8.0 / MariaDB 10.6+ |
| Frontend | HTML5 + CSS3 + Vanilla JS (PWA) |
| Auth | PHP Session + JWT (API) |
| Email | PHPMailer + SMTP |
| Notifikasi | Telegram Bot API |

## Struktur Folder

```
mova/
├── src/               # Core PHP (tidak accessible dari browser)
│   ├── Core/          # TenantContext, BaseRepository, AuthMiddleware
│   ├── Modules/       # VehicleRequest, TripLog, Maintenance, dll
│   ├── Middleware/
│   └── Helpers/
├── public/            # Document root
│   ├── index.php      # Entry point & router
│   ├── assets/        # CSS, JS, images
│   └── uploads/       # File uploads
├── migrations/        # Migration SQL terurut
├── config/            # Konfigurasi (.env, database.php)
├── docs/              # Dokumentasi project
└── tests/             # Unit / integration tests
```

## Requirements

- PHP 8.0+
- MySQL 8.0 / MariaDB 10.6+
- Composer (untuk dependency)
- mod_rewrite (Apache) / equivalent (Nginx)

## Instalasi

```bash
git clone https://github.com/ICC-Warrom-BJU/mova.git
cd mova
composer install
cp config/.env.example config/.env
# edit config/.env sesuai environment
```

Jalankan migration:

```bash
# Import file migration dari folder migrations/ secara berurutan
mysql -u root -p mova < migrations/001_...
```

Atur document root web server ke `public/`.

## Branch Strategy

- `main` — production-ready
- `staging` — pre-production / UAT
- `dev` — pengembangan harian

Fitur baru → branching dari `dev`, PR ke `dev` → `staging` → `main`.

## Dokumentasi

Dokumen referensi (PRD, ERD, Security, Glossary) tersedia di `docs/`.

## Lisensi

Proprietary — PT. Bumi Jasa Utama (BJU) / Kalla Transport & Logistics.
