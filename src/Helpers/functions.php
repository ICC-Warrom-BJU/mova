<?php

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function csrf_field(): string
{
    $token = AuthMiddleware::generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

function redirect(string $url): void
{
    header('Location: ' . $url);
    exit;
}

function jsonResponse(mixed $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    die(json_encode($data));
}

function jsonError(string $message, int $statusCode = 400): void
{
    jsonResponse(['error' => $message], $statusCode);
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

function asset(string $path): string
{
    return '/assets/' . ltrim($path, '/');
}

function view(string $view, array $data = []): void
{
    extract($data);
    $viewPath = __DIR__ . '/../Views/' . $view . '.php';
    if (!file_exists($viewPath)) {
        throw new RuntimeException("View not found: $view");
    }
    require $viewPath;
}

function generateNumber(string $prefix): string
{
    return $prefix . '-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
}

/* -------------------------------------------------------------------------
 * Hak akses modul Maintenance (RBAC)
 * ---------------------------------------------------------------------- */
function maintenanceCanView(): bool
{
    $t = SessionMiddleware::getTenantContext();
    if ($t->getLayer() === 'customer' || $t->isSuperAdmin()) return true;
    return in_array($t->getRole(), ['operation', 'management'], true); // company view-only
}
function maintenanceCanManage(): bool // buat & edit jadwal
{
    $t = SessionMiddleware::getTenantContext();
    return $t->isSuperAdmin() || in_array($t->getRole(), ['koordinator', 'supervisor', 'driver', 'operation'], true);
}
function maintenanceCanClose(): bool // log servis / tutup aktivitas
{
    $t = SessionMiddleware::getTenantContext();
    return $t->isSuperAdmin() || in_array($t->getRole(), ['koordinator', 'supervisor', 'driver', 'operation'], true);
}

/**
 * Ambil label tampilan untuk sebuah value config (termasuk yang nonaktif),
 * fallback ke value mentah bila tak ditemukan.
 */
function configLabel(string $group, ?string $value): string
{
    if ($value === null || $value === '') return '-';
    static $maps = [];
    if (!isset($maps[$group])) {
        $maps[$group] = [];
        try {
            $s = Database::getConnection()->prepare("SELECT value, label FROM mova_config_options WHERE group_key = ?");
            $s->execute([$group]);
            foreach ($s as $r) $maps[$group][$r['value']] = $r['label'];
        } catch (\Throwable $e) { /* ignore */ }
    }
    return $maps[$group][$value] ?? $value;
}

/**
 * Tone badge (warna) untuk status operasional yang konfigurable.
 * Heuristik berdasarkan kata kunci → success/warning/inactive/info.
 */
function statusTone(?string $value): string
{
    $v = strtolower((string)$value);
    if (preg_match('/maintenance|service|servis|perbaikan|repair/', $v)) return 'warning';
    if (preg_match('/not|non|inactive|nonaktif|rusak|off|stop|tidak/', $v)) return 'inactive';
    if (preg_match('/ready|active|siap|ok|jalan|aktif/', $v)) return 'active';
    return 'info';
}

/**
 * Katalog modul Premium & Enterprise (belum dibangun) untuk mode DEMO.
 * Ditampilkan di sidebar dengan flag tier + "Under Development", dan dipakai
 * halaman placeholder. Sumber tunggal supaya sidebar & placeholder konsisten.
 */
/**
 * Checkbox "Tampilkan nonaktif" untuk list master data.
 * Menoggle query param ?show_inactive=1 (param lain seperti pencarian dipertahankan).
 */
function inactiveToggle(bool $show): string
{
    $checked = $show ? ' checked' : '';
    // autocomplete="off": cegah browser me-restore state checkbox (form restoration)
    // yang membuat centang tak sinkron dengan URL sebenarnya.
    // Catatan: di inline handler, `URL` tertutup oleh document.URL (string), jadi pakai window.URL.
    $js = "var u=new window.URL(window.location.href);this.checked?u.searchParams.set('show_inactive','1'):u.searchParams.delete('show_inactive');window.location.href=u.href;";
    return '<label class="inactive-toggle"><input type="checkbox" autocomplete="off"' . $checked . ' onchange="' . e($js) . '"><span>Tampilkan nonaktif</span></label>';
}

function moduleCatalog(): array
{
    return [
        ['key' => 'analytics', 'tier' => 'premium', 'label' => 'Analitik & Laporan',
         'desc' => 'Dashboard analitik lanjutan: tren biaya, utilisasi armada, dan performa driver.',
         'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>'],
        ['key' => 'supervisor_approval', 'tier' => 'premium', 'label' => 'Approval Bertingkat',
         'desc' => 'Tambahan approval Level 2 oleh Supervisor sebelum request/laporan final.',
         'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg>'],
        ['key' => 'export', 'tier' => 'premium', 'label' => 'Export Data',
         'desc' => 'Ekspor laporan trip, BBM, dan biaya ke Excel & PDF.',
         'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>'],
        ['key' => 'gps_monitoring', 'tier' => 'enterprise', 'label' => 'GPS Monitoring',
         'desc' => 'Pelacakan posisi kendaraan secara real-time di peta.',
         'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>'],
        ['key' => 'custom_branding', 'tier' => 'enterprise', 'label' => 'Custom Branding',
         'desc' => 'Logo, warna, dan domain sesuai identitas perusahaan Anda.',
         'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>'],
        ['key' => 'api_integration', 'tier' => 'enterprise', 'label' => 'API Integration',
         'desc' => 'Integrasi data via REST API dengan sistem internal Anda.',
         'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>'],
        ['key' => 'custom_approval', 'tier' => 'enterprise', 'label' => 'Custom Approval Flow',
         'desc' => 'Rancang alur approval sesuai kebijakan perusahaan.',
         'icon' => '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>'],
    ];
}

/**
 * Ambil opsi konfigurasi aktif untuk sebuah grup (mis. trip_purpose,
 * expense_category, issue_category). Return: [['value'=>, 'label'=>], ...].
 */
function configOptions(string $group): array
{
    static $cache = [];
    if (isset($cache[$group])) return $cache[$group];
    try {
        $stmt = Database::getConnection()->prepare(
            "SELECT value, label FROM mova_config_options WHERE group_key = ? AND is_active = 1 ORDER BY sort_order, label"
        );
        $stmt->execute([$group]);
        return $cache[$group] = $stmt->fetchAll();
    } catch (\Throwable $e) {
        return $cache[$group] = [];
    }
}

function createNotification(array $data): void
{
    $db = Database::getConnection();
    $stmt = $db->prepare("
        INSERT INTO mova_notifications (user_id, customer_id, type, title, message, channel, reference_type, reference_id, is_read, created_at)
        VALUES (?, ?, ?, ?, ?, 'in_app', ?, ?, 0, NOW())
    ");
    $stmt->execute([
        $data['user_id'],
        $data['customer_id'] ?? null,
        $data['type'] ?? 'general',
        $data['title'],
        $data['message'],
        $data['reference_type'] ?? null,
        $data['reference_id'] ?? null,
    ]);
}

function getUnreadNotificationCount(): int
{
    $db = Database::getConnection();
    $userId = (int)$_SESSION['user_id'];
    $stmt = $db->prepare("SELECT COUNT(*) FROM mova_notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

function getNotifications(int $limit = 20): array
{
    $db = Database::getConnection();
    $userId = (int)$_SESSION['user_id'];
    $stmt = $db->prepare("SELECT * FROM mova_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT " . (int)$limit);
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}
