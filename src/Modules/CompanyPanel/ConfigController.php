<?php

class ConfigController
{
    /** Grup yang bisa dikonfigurasi + nama tampilannya */
    private const GROUPS = [
        'trip_purpose'     => 'Tipe Perjalanan',
        'expense_category' => 'Kategori Biaya',
        'issue_category'   => 'Kategori Kerusakan',
    ];

    public function index(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(['super_admin']);

        $db = Database::getConnection();
        $rows = $db->query("SELECT * FROM mova_config_options ORDER BY group_key, sort_order, label")->fetchAll();
        $byGroup = [];
        foreach (self::GROUPS as $key => $_) { $byGroup[$key] = []; }
        foreach ($rows as $r) { $byGroup[$r['group_key']][] = $r; }

        ob_start();
        ?>
        <p class="page-subtitle">Kelola daftar pilihan yang dipakai di form operasional. Nilai baru langsung muncul di dropdown terkait.</p>
        <div class="grid-auto-fit">
            <?php foreach (self::GROUPS as $key => $title): ?>
            <div class="card">
                <div class="card-header"><h3><?= e($title) ?></h3></div>
                <div class="card-body">
                    <form method="post" action="/config" class="flex gap-2 mb-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="group_key" value="<?= e($key) ?>">
                        <input type="text" name="label" class="form-control flex-1" placeholder="Tambah pilihan baru..." required>
                        <button type="submit" class="btn btn-primary btn-sm">+ Tambah</button>
                    </form>
                    <?php if (empty($byGroup[$key])): ?>
                        <div class="empty-state"><p>Belum ada pilihan.</p></div>
                    <?php else: ?>
                    <div class="table-wrap">
                    <table>
                        <thead><tr><th>Label</th><th>Nilai</th><th>Status</th><th>Aksi</th></tr></thead>
                        <tbody>
                            <?php foreach ($byGroup[$key] as $o): ?>
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
            <?php endforeach; ?>
        </div>
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
            redirect('/config');
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
        redirect('/config');
    }

    public function toggle(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(['super_admin']);
        AuthMiddleware::validateCsrf();

        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE mova_config_options SET is_active = 1 - is_active WHERE id = ?");
        $stmt->execute([$id]);

        $_SESSION['_flash']['success'] = 'Status pilihan diperbarui';
        redirect('/config');
    }
}
