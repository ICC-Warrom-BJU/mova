<?php

function renderLayout(string $title, string $content, array $data = []): void
{
    $user = $_SESSION['_user'] ?? [];
    $roleName = $user['role_name'] ?? '';
    $userName = $user['name'] ?? '';
    $csrf = csrf_field();
    $notifCount = function_exists('getUnreadNotificationCount') ? getUnreadNotificationCount() : 0;
    $active = $data['active'] ?? '';
    ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="theme-color" content="#FFFFFF">
    <title><?= e($title) ?> - MOVA</title>
    <link rel="manifest" href="/manifest.json">
    <link rel="icon" type="image/png" href="/assets/favicon.png">
    <link rel="apple-touch-icon" href="/assets/icon-192.png">
    <link rel="stylesheet" href="/assets/css/main.css?v=<?= @filemtime(__DIR__ . '/../../../../public/assets/css/main.css') ?: time() ?>">
    <script src="/assets/js/chart.umd.min.js"></script>
    <script src="/assets/js/select-search.js" defer></script>
</head>
<body>
<div class="app">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="sidebar-logo">
                <img src="/assets/mova-logo.png" alt="MOVA" class="sidebar-logo-img">
                <div class="logo-text">
                    <small>Management Panel</small>
                </div>
            </div>
        </div>

        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-header">Overview</div>
                <a href="/dashboard" class="nav-item <?= $active === 'dashboard' ? 'active' : '' ?>" data-tooltip="Dashboard">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                    </span>
                    <span class="nav-label">Dashboard</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-header">Master Data</div>
                <a href="/regions" class="nav-item <?= str_starts_with($active, 'regions') ? 'active' : '' ?>" data-tooltip="Region">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                    </span>
                    <span class="nav-label">Region</span>
                </a>
                <a href="/branches" class="nav-item <?= str_starts_with($active, 'branches') ? 'active' : '' ?>" data-tooltip="Branch">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                    </span>
                    <span class="nav-label">Branch</span>
                </a>
                <a href="/customers" class="nav-item <?= str_starts_with($active, 'customers') ? 'active' : '' ?>" data-tooltip="Customer">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                    </span>
                    <span class="nav-label">Customer</span>
                </a>
                <a href="/vehicles" class="nav-item <?= str_starts_with($active, 'vehicles') ? 'active' : '' ?>" data-tooltip="Vehicle">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h3l3 3v4h-6V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                    </span>
                    <span class="nav-label">Vehicle</span>
                </a>
                <a href="/users" class="nav-item <?= str_starts_with($active, 'users') ? 'active' : '' ?>" data-tooltip="User">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20v-1a8 8 0 0 1 16 0v1"/></svg>
                    </span>
                    <span class="nav-label">User</span>
                </a>
                <a href="/config" class="nav-item <?= str_starts_with($active, 'config') ? 'active' : '' ?>" data-tooltip="Konfigurasi">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    </span>
                    <span class="nav-label">Konfigurasi</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-header">Operational</div>
                <a href="/customer/requests" class="nav-item <?= str_starts_with($active, 'requests') ? 'active' : '' ?>" data-tooltip="Vehicle Request">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><line x1="10" y1="9" x2="8" y2="9"/></svg>
                    </span>
                    <span class="nav-label">Vehicle Request</span>
                </a>
                <a href="/customer/trips" class="nav-item <?= str_starts_with($active, 'trips') ? 'active' : '' ?>" data-tooltip="Trip Log">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                    </span>
                    <span class="nav-label">Trip Log</span>
                </a>
                <a href="/customer/issues" class="nav-item <?= str_starts_with($active, 'issues') ? 'active' : '' ?>" data-tooltip="Issue Report">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                    </span>
                    <span class="nav-label">Issue Report</span>
                </a>
                <a href="/customer/fuel" class="nav-item <?= str_starts_with($active, 'fuel') ? 'active' : '' ?>" data-tooltip="Fuel Report">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 22V8l9-6 9 6v14"/><path d="M9 22V12h6v10"/><path d="M13 6h2v4h-2z"/></svg>
                    </span>
                    <span class="nav-label">Fuel Report</span>
                </a>
                <a href="/customer/expenses" class="nav-item <?= str_starts_with($active, 'expenses') ? 'active' : '' ?>" data-tooltip="Expense">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                    </span>
                    <span class="nav-label">Expense</span>
                </a>
            </div>

            <div class="nav-section">
                <div class="nav-header">Fleet</div>
                <a href="/customer/maintenance" class="nav-item <?= str_starts_with($active, 'maintenance') ? 'active' : '' ?>" data-tooltip="Maintenance">
                    <span class="nav-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/></svg>
                    </span>
                    <span class="nav-label">Maintenance</span>
                </a>
            </div>
        </nav>

        <div class="sidebar-footer">
            <div class="sidebar-user">
                <div class="sidebar-avatar"><?= strtoupper(substr($userName, 0, 1)) ?></div>
                <div class="sidebar-user-info">
                    <div class="user-name"><?= e($userName) ?></div>
                    <span class="role-badge <?= e($roleName) ?>"><?= e(str_replace('_', ' ', $roleName)) ?></span>
                </div>
            </div>
            <div class="sidebar-footer-links">
                <a href="/notifications" class="footer-link <?= $notifCount > 0 ? 'has-notif' : '' ?>" data-tooltip="Notifikasi">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    <?php if ($notifCount > 0): ?><span class="notif-dot"><?= $notifCount ?></span><?php endif; ?>
                </a>
                <a href="/logout" class="footer-link" data-tooltip="Logout">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                </a>
            </div>
        </div>
    </aside>

    <main class="main">
        <div class="topbar">
            <div class="topbar-left">
                <button class="mobile-toggle" aria-label="Toggle Menu">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"></line><line x1="3" y1="6" x2="21" y2="6"></line><line x1="3" y1="18" x2="21" y2="18"></line></svg>
                </button>
                <h2><?= e($title) ?></h2>
            </div>
            <div class="topbar-right">
                <div class="topbar-user-chip">
                    <div class="topbar-avatar"><?= strtoupper(substr($userName, 0, 1)) ?></div>
                    <div class="topbar-user-detail">
                        <span class="topbar-user-name"><?= e($userName) ?></span>
                        <span class="topbar-user-role"><?= e(str_replace('_', ' ', $roleName)) ?></span>
                    </div>
                </div>
                <div class="topbar-actions"><?= $data['actions'] ?? '' ?></div>
            </div>
        </div>
        <div class="content">
            <?php if ($flash = $_SESSION['_flash']['success'] ?? null): ?>
                <div class="alert alert-success"><?= e($flash) ?></div>
                <?php unset($_SESSION['_flash']['success']); ?>
            <?php endif; ?>
            <?php if ($flash = $_SESSION['_flash']['error'] ?? null): ?>
                <div class="alert alert-danger"><?= e($flash) ?></div>
                <?php unset($_SESSION['_flash']['error']); ?>
            <?php endif; ?>
            <?= $content ?>
        </div>
    </main>
</div>
<script>
    // Service worker dinonaktifkan selama pengembangan UI: lepas registrasi
    // lama & bersihkan cache agar CSS/JS selalu versi terbaru.
    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.getRegistrations().then(regs => regs.forEach(r => r.unregister()));
    }
    if (window.caches) {
        caches.keys().then(keys => keys.forEach(k => caches.delete(k)));
    }

    const mobileToggle = document.querySelector('.mobile-toggle');
    const sidebar = document.querySelector('.sidebar');
    if (mobileToggle && sidebar) {
        mobileToggle.addEventListener('click', () => {
            sidebar.classList.toggle('open');
        });
        document.addEventListener('click', (e) => {
            if (window.innerWidth <= 768 && !sidebar.contains(e.target) && !mobileToggle.contains(e.target)) {
                sidebar.classList.remove('open');
            }
        });
    }
</script>
</body>
</html>
<?php
}
