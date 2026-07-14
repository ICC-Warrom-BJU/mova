<?php

class ConfigController
{
    /** Grup yang bisa dikonfigurasi + nama tampilannya */
    private const GROUPS = [
        'trip_purpose'     => 'Tipe Perjalanan',
        'expense_category' => 'Kategori Biaya',
        'issue_category'   => 'Kategori Kerusakan',
        'vehicle_type'     => 'Tipe Kendaraan',
        'vehicle_status'   => 'Status Operasional Kendaraan',
    ];

    public function index(?string $groupKey = null): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(['super_admin']);

        $groupKey ??= $_GET['group'] ?? array_key_first(self::GROUPS);

        if (!isset(self::GROUPS[$groupKey])) {
            $groupKey = array_key_first(self::GROUPS);
        }

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT * FROM mova_config_options WHERE group_key = ? ORDER BY sort_order, label");
        $stmt->execute([$groupKey]);
        $items = $stmt->fetchAll();

        ob_start();
        ?>
        <p class="page-subtitle">Kelola daftar pilihan yang dipakai di form operasional. Nilai baru langsung muncul di dropdown terkait.</p>

        <div class="config-tabs">
            <?php foreach (self::GROUPS as $key => $title): ?>
            <a href="/config?group=<?= e($key) ?>" class="config-tab <?= $key === $groupKey ? 'active' : '' ?>">
                <?= e($title) ?>
            </a>
            <?php endforeach; ?>
        </div>

        <div class="card">
            <div class="card-header">
                <h3><?= e(self::GROUPS[$groupKey]) ?></h3>
            </div>
            <div class="card-body">
                <form method="post" action="/config" class="flex gap-2" style="margin-bottom:24px">
                    <?= csrf_field() ?>
                    <input type="hidden" name="group_key" value="<?= e($groupKey) ?>">
                    <input type="text" name="label" class="form-control flex-1" placeholder="Tambah pilihan baru..." required>
                    <button type="submit" class="btn btn-primary btn-sm">+ Tambah</button>
                </form>
                <?php if (empty($items)): ?>
                    <div class="empty-state"><p>Belum ada pilihan untuk grup ini.</p></div>
                <?php else: ?>
                <div class="table-wrap">
                <table>
                    <thead><tr><th>Label</th><th>Nilai</th><th>Status</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $o): ?>
                        <tr>
                            <td><strong><?= e($o['label']) ?></strong></td>
                            <td><code class="text-muted"><?= e($o['value']) ?></code></td>
                            <td><span class="badge <?= $o['is_active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $o['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                            <td>
                                <form method="post" action="/config/<?= $o['id'] ?>/toggle" class="form-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-outline btn-sm"><?= $o['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <style>
        .config-tabs { display: flex; gap: 4px; margin-bottom: 16px; flex-wrap: wrap; }
        .config-tab { padding: 8px 16px; border-radius: 6px 6px 0 0; font-size: 0.82rem; font-weight: 500; color: var(--text-2, #475569); background: var(--bg-1, #f1f5f9); border: 1px solid var(--border, #e2e8f0); border-bottom: none; text-decoration: none; transition: all 0.15s; }
        .config-tab:hover { background: var(--bg-2, #f8fafc); color: var(--text, #1e293b); }
        .config-tab.active { background: var(--card-bg, #fff); color: var(--brand, #2563eb); border-color: var(--border, #e2e8f0); margin-bottom: -1px; }
        </style>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/Views/layout.php';
        renderLayout('Konfigurasi', $content, ['active' => 'config']);
    }

    public function store(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(['super_admin']);
        AuthMiddleware::validateCsrf();

        $group = $_POST['group_key'] ?? '';
        $label = trim($_POST['label'] ?? '');

        if (!isset(self::GROUPS[$group]) || $label === '') {
            $_SESSION['_flash']['error'] = 'Grup tidak valid atau label kosong';
            redirect('/config' . ($group ? '?group=' . urlencode($group) : ''));
        }

        // value = slug dari label (huruf kecil, non-alfanumerik → underscore)
        $value = trim(preg_replace('/_+/', '_', preg_replace('/[^a-z0-9]+/', '_', strtolower($label))), '_');
        if ($value === '') { $value = 'opt_' . substr(md5($label), 0, 6); }

        $db = Database::getConnection();
        // sort_order = max+1 dalam grup
        $stmt = $db->prepare("SELECT COALESCE(MAX(sort_order),0)+1 FROM mova_config_options WHERE group_key = ?");
        $stmt->execute([$group]);
        $sort = (int)$stmt->fetchColumn();

        $ins = $db->prepare("INSERT INTO mova_config_options (group_key, value, label, sort_order) VALUES (?, ?, ?, ?)
                             ON DUPLICATE KEY UPDATE label = VALUES(label), is_active = 1");
        $ins->execute([$group, $value, $label, $sort]);

        $_SESSION['_flash']['success'] = 'Pilihan "' . $label . '" ditambahkan';
        redirect('/config?group=' . urlencode($group));
    }

    public function toggle(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(['super_admin']);
        AuthMiddleware::validateCsrf();

        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT group_key FROM mova_config_options WHERE id = ?");
        $stmt->execute([$id]);
        $item = $stmt->fetch();

        $stmt = $db->prepare("UPDATE mova_config_options SET is_active = 1 - is_active WHERE id = ?");
        $stmt->execute([$id]);

        $redirectUrl = '/config';
        if ($item) {
            $redirectUrl .= '?group=' . urlencode($item['group_key']);
        }

        $_SESSION['_flash']['success'] = 'Status pilihan diperbarui';
        redirect($redirectUrl);
    }
}
