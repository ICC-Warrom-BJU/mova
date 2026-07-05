# MOVA — Security Implementation Guidelines

Dokumen ini adalah instruksi teknis wajib untuk seluruh proses development MOVA (Native PHP). Berbeda dari framework seperti Laravel yang punya proteksi bawaan, **native PHP tidak memberikan pengaman otomatis** — setiap poin di dokumen ini harus diimplementasikan secara eksplisit dan sadar.

- **Target pembaca**: Developer (termasuk penggunaan AI coding assistant / vibe coding)
- **Sifat dokumen**: Wajib dipatuhi, bukan opsional
- **Prioritas**: Diurutkan dari yang paling kritis untuk arsitektur multi-tenant SaaS

---

## Cara Menggunakan Dokumen Ini

Setiap developer (atau AI assistant yang membantu coding) **wajib membaca dokumen ini sebelum menulis modul baru**. Setiap poin memiliki:
- **Level** — tingkat kekritisan (Kritis / Penting / Jangka Panjang)
- **Kapan diterapkan** — di titik mana dalam development
- **Implementasi** — contoh kode konkret
- **Alasan** — kenapa ini tidak boleh dilewatkan

---

## Level 1 — Kritis (Wajib Ada Sejak Baris Kode Pertama)

Poin di level ini **tidak boleh ditunda ke fase "nanti dirapikan"**. Ini adalah fondasi arsitektur, bukan fitur tambahan — retrofit setelah banyak modul jadi jauh lebih mahal dan berisiko dibanding membangunnya sejak awal.

### 1.1 Isolasi Data Multi-Tenant (Tenant Scoping)

**Level**: Kritis — risiko tertinggi di seluruh sistem
**Kapan diterapkan**: Sebelum modul CRUD pertama dibuat

**Masalah yang dicegah**: Kebocoran data lintas customer akibat query yang lupa memfilter `customer_id`. Satu query yang lolos tanpa filter ini berarti data seluruh customer bisa terekspos ke satu tenant.

**Aturan wajib**:
- Setiap tabel transaksional (`vehicle_requests`, `trips`, `fuel_reports`, `expense_reports`, `issue_reports`, `maintenance_schedules`, `maintenance_logs`, `vehicles`, dst.) **wajib** memiliki kolom `customer_id`.
- Tidak boleh ada satupun query manual yang menyusun filter `customer_id` secara bebas di setiap file. Gunakan **base class terpusat** yang mewajibkan filter ini secara struktural.

**Implementasi wajib — TenantContext**:
```php
<?php
// src/Core/TenantContext.php

class TenantContext {
    private ?int $customerId;
    private ?int $branchId;
    private string $layer; // "company" atau "customer"
    private array $accessibleCustomerIds = [];

    public function __construct(array $sessionData) {
        $this->layer = $sessionData['layer'];
        $this->customerId = $sessionData['customer_id'] ?? null;
        $this->branchId = $sessionData['branch_id'] ?? null;

        if ($this->layer === 'company') {
            // Operation/Marketing Staff: hitung daftar customer_id
            // yang berada di branch mereka (bukan hardcode di query manual)
            $this->accessibleCustomerIds = $this->resolveCustomersByBranch($this->branchId);
        }
    }

    public function getCustomerId(): ?int {
        return $this->customerId;
    }

    public function getAccessibleCustomerIds(): array {
        if ($this->layer === 'customer') {
            return [$this->customerId];
        }
        return $this->accessibleCustomerIds;
    }

    public function isSuperAdmin(): bool {
        return $this->layer === 'company' && $this->role === 'super_admin';
    }
}
```

**Implementasi wajib — BaseRepository**:
```php
<?php
// src/Core/BaseRepository.php

abstract class BaseRepository {
    protected PDO $db;
    protected TenantContext $tenant;
    protected string $table;

    public function __construct(PDO $db, TenantContext $tenant) {
        $this->db = $db;
        $this->tenant = $tenant;
    }

    /**
     * Semua query SELECT wajib lewat method ini.
     * customer_id ter-inject otomatis, tidak bisa dilewati oleh caller.
     */
    protected function scopedSelect(string $whereClause = '', array $params = []): array {
        if ($this->tenant->isSuperAdmin()) {
            $sql = "SELECT * FROM {$this->table} " . ($whereClause ? "WHERE $whereClause" : '');
        } else {
            $ids = $this->tenant->getAccessibleCustomerIds();
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "SELECT * FROM {$this->table} WHERE customer_id IN ($placeholders)"
                 . ($whereClause ? " AND $whereClause" : '');
            $params = array_merge($ids, $params);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Semua INSERT wajib lewat method ini — customer_id otomatis diisi
     * dari tenant context, tidak boleh diterima dari input user/form.
     */
    protected function scopedInsert(array $data): int {
        if (!$this->tenant->isSuperAdmin()) {
            $data['customer_id'] = $this->tenant->getCustomerId();
        }
        $columns = implode(',', array_keys($data));
        $placeholders = implode(',', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($data));
        return (int) $this->db->lastInsertId();
    }
}
```

**Aturan tambahan**:
- `customer_id` **tidak pernah** diterima dari input form/request body. Selalu diisi dari session/`TenantContext`, tidak pernah dari `$_POST['customer_id']`.
- Setiap Repository baru **wajib** extends `BaseRepository` — tidak boleh ada query mentah di luar pola ini kecuali untuk kasus khusus yang direview manual.
- Operation Staff dan Marketing Staff BJU harus melalui resolusi `accessibleCustomerIds` berbasis `branch_id`, bukan daftar manual.

---

### 1.2 SQL Injection Prevention

**Level**: Kritis
**Kapan diterapkan**: Setiap kali menulis query database, tanpa pengecualian

**Aturan mutlak**: Tidak ada satupun variabel yang boleh digabungkan langsung ke string SQL menggunakan concatenation atau interpolation.

```php
// ❌ DILARANG KERAS — rentan SQL injection
$db->query("SELECT * FROM mova_users WHERE email = '$email'");
$db->query("SELECT * FROM mova_vehicles WHERE plate_number = '" . $plate . "'");

// ✅ WAJIB — prepared statement dengan parameter binding
$stmt = $db->prepare("SELECT * FROM mova_users WHERE email = ?");
$stmt->execute([$email]);

// ✅ WAJIB — named parameter juga diterima
$stmt = $db->prepare("SELECT * FROM mova_users WHERE email = :email");
$stmt->execute(['email' => $email]);
```

**Konfigurasi PDO wajib**:
```php
$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_EMULATE_PREPARES => false, // wajib false — pakai native prepared statement
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
```

**Catatan untuk AI coding assistant**: Jika sedang generate kode query database, selalu gunakan prepared statement. Jangan pernah menyarankan atau menulis string SQL yang mengandung variabel langsung, bahkan untuk kode "sementara" atau "contoh".

---

### 1.3 Autentikasi & Manajemen Password

**Level**: Kritis
**Kapan diterapkan**: Modul login/autentikasi pertama

**Aturan wajib**:

```php
// Hashing password — WAJIB bcrypt atau argon2id, TIDAK PERNAH plain text atau MD5/SHA1
$hashed = password_hash($plainPassword, PASSWORD_ARGON2ID);

// Verifikasi login
if (password_verify($inputPassword, $storedHash)) {
    // Regenerate session ID setiap kali login berhasil
    // Mencegah session fixation attack
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['layer'] = $user['layer'];
    $_SESSION['role'] = $user['role'];
}
```

**Konfigurasi session cookie wajib**:
```php
session_set_cookie_params([
    'lifetime' => 3600 * 8, // 8 jam
    'path' => '/',
    'domain' => '.mova-domain.com',
    'secure' => true,      // hanya dikirim lewat HTTPS
    'httponly' => true,    // tidak bisa diakses JavaScript, mencegah XSS mencuri session
    'samesite' => 'Strict' // mencegah CSRF via cross-site request
]);
```

**Rate limiting percobaan login**:
```php
// Simpan percobaan gagal di tabel mova_login_attempts atau cache
// Setelah 5x gagal dalam 15 menit, lock akun sementara

class LoginRateLimiter {
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    public function isLocked(string $email): bool {
        $attempts = $this->getRecentAttempts($email, self::LOCKOUT_MINUTES);
        return $attempts >= self::MAX_ATTEMPTS;
    }

    public function recordFailedAttempt(string $email): void {
        // insert ke mova_login_attempts dengan timestamp
    }

    public function clearAttempts(string $email): void {
        // hapus record setelah login berhasil
    }
}
```

**Kenapa penting**: MOVA punya 8 role berbeda yang login ke sistem yang sama — target permukaan serangan brute force jauh lebih besar dibanding aplikasi single-role.

---

### 1.4 Otorisasi Berlapis (RBAC Enforcement)

**Level**: Kritis
**Kapan diterapkan**: Setiap endpoint API/controller baru

**Aturan wajib**: Setiap endpoint harus melalui **dua lapis validasi**, tidak boleh hanya salah satu:

1. **Role check** — apakah role ini punya izin untuk aksi ini?
2. **Scope check** — apakah data yang diakses berada dalam scope user ini?

```php
<?php
// src/Core/AuthMiddleware.php

class AuthMiddleware {

    public function requireRole(array $allowedRoles): void {
        $userRole = $_SESSION['role'] ?? null;
        if (!in_array($userRole, $allowedRoles, true)) {
            http_response_code(403);
            die(json_encode(['error' => 'Forbidden: role tidak diizinkan']));
        }
    }

    public function requireOwnershipOrScope(int $resourceCustomerId, TenantContext $tenant): void {
        if ($tenant->isSuperAdmin()) return;

        if (!in_array($resourceCustomerId, $tenant->getAccessibleCustomerIds(), true)) {
            http_response_code(403);
            die(json_encode(['error' => 'Forbidden: di luar scope akses Anda']));
        }
    }
}

// Contoh penggunaan di endpoint approve vehicle request
$auth->requireRole(['koordinator', 'supervisor']);
$request = $vehicleRequestRepo->find($requestId);
$auth->requireOwnershipOrScope($request['customer_id'], $tenant);
// baru boleh lanjut proses approve
```

**Aturan krusial**: **Jangan pernah** mengandalkan validasi di sisi frontend/JavaScript saja untuk keamanan. Validasi tampilan (menyembunyikan tombol) boleh dilakukan di frontend untuk UX, tapi validasi otorisasi **wajib** diulang di backend — karena request API bisa dipanggil langsung lewat browser dev tools, Postman, atau curl tanpa melalui UI.

**Matriks referensi**: Gunakan Access Matrix yang sudah didefinisikan di PRD (bagian 3) sebagai acuan tunggal kebenaran (source of truth) saat implementasi setiap endpoint.

---

## Level 2 — Penting (Sebelum Rilis ke Customer Pertama)

### 2.1 Cross-Site Scripting (XSS) Prevention

**Level**: Penting
**Kapan diterapkan**: Setiap kali menampilkan input user di halaman HTML

MOVA punya banyak input teks bebas: catatan trip, keluhan kerusakan, alasan penolakan, deskripsi laporan biaya. Semua wajib di-escape saat ditampilkan.

```php
// Wajib di setiap output HTML dari data user
function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

// Penggunaan di template
echo "<p>" . e($trip['notes']) . "</p>";
```

Untuk data yang di-passing ke JavaScript (misalnya lewat `<script>` inline), gunakan `json_encode` dengan flag aman:
```php
echo "<script>const tripData = " . json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) . ";</script>";
```

---

### 2.2 CSRF (Cross-Site Request Forgery) Protection

**Level**: Penting
**Kapan diterapkan**: Setiap form yang melakukan perubahan data (POST/PUT/DELETE)

```php
// Generate token per session, sekali saat login
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Sisipkan di setiap form
echo '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token']) . '">';

// Validasi di setiap POST handler
function validateCsrf(): void {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        die(json_encode(['error' => 'Invalid CSRF token']));
    }
}
```

**Wajib diterapkan khusus di**: approve/reject Vehicle Request, approve laporan BBM/biaya, ubah konfigurasi subscription (Super Admin), submit checklist.

---

### 2.3 File Upload Security

**Level**: Penting
**Kapan diterapkan**: Modul upload foto (checklist, struk BBM, foto kerusakan)

```php
<?php
class SecureFileUpload {
    private const ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];
    private const MAX_SIZE_BYTES = 5 * 1024 * 1024; // 5MB

    public function handle(array $file, int $customerId, string $module): string {
        // 1. Validasi ukuran
        if ($file['size'] > self::MAX_SIZE_BYTES) {
            throw new RuntimeException('File terlalu besar, maksimal 5MB');
        }

        // 2. Validasi MIME type dari ISI file, bukan dari nama/ekstensi
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $actualMime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($actualMime, self::ALLOWED_MIME, true)) {
            throw new RuntimeException('Tipe file tidak diizinkan');
        }

        // 3. Rename ke nama random — cegah path traversal & overwrite
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safeFilename = bin2hex(random_bytes(16)) . '.' . $extension;

        // 4. Simpan di path terstruktur per tenant, di luar webroot jika bisa
        $path = "/uploads/{$customerId}/{$module}/" . date('Y/m') . "/{$safeFilename}";

        move_uploaded_file($file['tmp_name'], $storageRoot . $path);
        return $path;
    }
}
```

**Konfigurasi folder upload wajib** (`.htaccess` di folder uploads):
```apache
# Cegah file di folder upload dieksekusi sebagai PHP
<FilesMatch "\.(php|phtml|php3|php4|php5|pht)$">
    Require all denied
</FilesMatch>
php_flag engine off
```

---

### 2.4 Security Headers

**Level**: Penting
**Kapan diterapkan**: Setup awal server/aplikasi

```php
// Di bootstrap/entry point aplikasi (index.php atau middleware global)
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; script-src 'self'");
header("X-Frame-Options: DENY");
header("X-Content-Type-Options: nosniff");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
```

---

### 2.5 HTTPS Wajib di Semua Environment

**Level**: Penting
**Kapan diterapkan**: Setup server, termasuk staging

- SSL/TLS via Let's Encrypt (gratis, auto-renewal via certbot)
- Redirect paksa HTTP → HTTPS di level Apache/Nginx
- Jangan pernah kirim credential, session cookie, atau token lewat koneksi HTTP plain — termasuk di environment staging/development yang bisa diakses dari luar.

---

## Level 3 — Jangka Panjang (Sejalan dengan Pertumbuhan Roadmap)

### 3.1 Secrets Management

**Level**: Jangka panjang, tapi mulai dari commit pertama
**Kapan diterapkan**: Sejak repository Git dibuat

```bash
# .gitignore — wajib ada sejak commit pertama
.env
.env.local
/uploads/*
!/uploads/.gitkeep
```

```php
// .env (tidak pernah masuk Git)
DB_HOST=localhost
DB_NAME=mova_production
DB_USER=mova_app
DB_PASS=***
TELEGRAM_BOT_TOKEN=***
SMTP_PASSWORD=***

// Load via library ringan (vlucas/phpdotenv) atau parser manual
$env = parse_ini_file('.env');
```

**Aturan tambahan**: Jika `.env` pernah ter-commit sebelumnya secara tidak sengaja, ganti semua credential yang ada di dalamnya — menghapus dari commit terbaru saja tidak cukup karena riwayat Git tetap menyimpannya.

---

### 3.2 Audit Logging

**Level**: Jangka panjang
**Kapan diterapkan**: Bersamaan dengan modul approval workflow

Catat log yang tidak bisa dihapus oleh user biasa (`INSERT`-only, tanpa privilege `DELETE`/`UPDATE` dari aplikasi):

**Wajib dicatat**:
- Setiap approval/rejection (Vehicle Request, BBM, Biaya) — siapa, kapan, dari IP mana
- Perubahan konfigurasi subscription oleh Super Admin
- Setiap akses Super Admin/Management ke data customer tertentu (karena mereka satu-satunya role lintas tenant)
- Percobaan login gagal berulang

```sql
CREATE TABLE mova_audit_logs (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(100) NOT NULL,
    resource_type VARCHAR(50),
    resource_id BIGINT UNSIGNED,
    ip_address VARCHAR(45),
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

### 3.3 Rate Limiting API

**Level**: Jangka panjang — makin penting saat Enterprise tier dengan API publik aktif

```php
// Sederhana berbasis cache/tabel — batasi request per user per menit
class ApiRateLimiter {
    private const MAX_REQUESTS_PER_MINUTE = 60;

    public function checkLimit(int $userId): bool {
        $key = "rate_limit:{$userId}:" . date('YmdHi');
        $count = $this->incrementAndGet($key);
        return $count <= self::MAX_REQUESTS_PER_MINUTE;
    }
}
```

---

### 3.4 Dependency Security

**Level**: Jangka panjang — rutin
**Kapan diterapkan**: Setiap kali menambah package Composer baru, dan berkala (bulanan)

```bash
composer audit
composer outdated
```

---

## Checklist Implementasi per Fase

### Sebelum modul pertama ditulis
- [ ] `TenantContext` class sudah dibuat dan diuji
- [ ] `BaseRepository` dengan scoped query sudah dibuat
- [ ] Konfigurasi PDO dengan prepared statement wajib sudah diset
- [ ] Struktur session (`session_set_cookie_params`) sudah diset dengan flag aman
- [ ] `.gitignore` sudah mencakup `.env` dan folder sensitif

### Sebelum modul autentikasi dianggap selesai
- [ ] Password hashing pakai `PASSWORD_ARGON2ID` atau `PASSWORD_BCRYPT`
- [ ] Session regenerate saat login
- [ ] Rate limiting percobaan login aktif
- [ ] Logout menghapus session sepenuhnya (`session_destroy()` + hapus cookie)

### Sebelum setiap endpoint API dianggap selesai
- [ ] Role check diterapkan (`requireRole`)
- [ ] Scope check diterapkan (`requireOwnershipOrScope`)
- [ ] Semua query pakai prepared statement
- [ ] CSRF token divalidasi jika endpoint menerima POST/PUT/DELETE

### Sebelum modul upload dianggap selesai
- [ ] MIME type divalidasi dari isi file, bukan ekstensi
- [ ] Ukuran file dibatasi
- [ ] Nama file di-random-kan
- [ ] Folder upload tidak bisa eksekusi PHP

### Sebelum rilis ke customer pertama (go-live checklist)
- [ ] HTTPS aktif di semua environment (termasuk staging)
- [ ] Security headers terpasang
- [ ] `.env` tidak pernah ter-commit ke Git riwayat manapun
- [ ] Audit log aktif untuk approval workflow dan akses Super Admin
- [ ] Backup database terjadwal dan teruji proses restore-nya
- [ ] Composer dependency sudah di-audit (`composer audit`)

---

## Catatan Khusus untuk AI-Assisted Development (Vibe Coding)

Karena development MOVA menggunakan AI coding assistant (OpenCode / Claude Code), berikut instruksi tambahan yang perlu diberlakukan konsisten di setiap sesi coding:

1. **Selalu instruksikan AI untuk menggunakan `BaseRepository`/`TenantContext`** saat meminta generate modul CRUD baru — jangan biarkan AI membuat query langsung ke database tanpa melalui layer ini.
2. **Selalu review query yang di-generate AI** untuk memastikan tidak ada string concatenation di SQL, sebelum kode di-commit.
3. **Jangan copy-paste contoh kode dari AI tanpa menyesuaikan tenant scoping** — AI assistant secara default tidak tahu konteks multi-tenant MOVA kecuali diberi tahu eksplisit di setiap prompt atau lewat file instruksi project (seperti dokumen ini).
4. **Simpan dokumen ini sebagai referensi project** (misalnya `SECURITY.md` di root repository) agar AI assistant bisa membacanya sebagai konteks saat membantu coding di sesi-sesi berikutnya.

---

*Dokumen ini adalah pendamping teknis dari `MOVA-PRD-v1.0.docx` dan `MOVA-ERD.md`. Untuk struktur database dan alur bisnis, rujuk kedua dokumen tersebut.*
