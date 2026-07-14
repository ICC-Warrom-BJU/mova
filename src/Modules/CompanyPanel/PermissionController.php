<?php

class PermissionController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(['super_admin']);

        $repo = new PermissionRepository();
        $roles = $repo->getAllRoles();
        $matrix = $repo->getPermissionsMatrix();
        $companyGroups = $repo->getModuleGroups('company');
        $customerGroups = $repo->getModuleGroups('customer');

        ob_start();
        ?>
        <style>
        .perm-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; }
        .perm-table th { text-align: left; padding: 8px 10px; background: var(--bg-2, #f8fafc); border-bottom: 2px solid var(--border, #e2e8f0); white-space: nowrap; }
        .perm-table td { padding: 6px 10px; border-bottom: 1px solid var(--border, #e2e8f0); vertical-align: middle; }
        .perm-table .role-name { font-weight: 600; white-space: nowrap; }
        .perm-table .role-layer { font-size: 0.7rem; color: var(--text-muted, #94a3b8); display: block; }
        .perm-group-header { background: var(--bg-1, #f1f5f9); font-weight: 600; color: var(--text-2, #475569); font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; }
        .perm-check { width: 15px; height: 15px; accent-color: var(--brand, #2563eb); cursor: pointer; display: block; margin: 0 auto; }
        .perm-check:disabled { opacity: 0.4; cursor: not-allowed; }
        .perm-table th .mod-label { writing-mode: vertical-lr; transform: rotate(180deg); font-size: 0.72rem; font-weight: 500; padding: 4px 2px; white-space: nowrap; text-align: center; }
        .perm-section { margin-bottom: 24px; }
        .perm-section h3 { font-size: 0.9rem; font-weight: 600; margin: 0 0 8px 0; color: var(--text, #1e293b); }
        .perm-section .layer-badge { display: inline-block; font-size: 0.7rem; padding: 2px 8px; border-radius: 4px; margin-left: 8px; }
        .perm-section .layer-company { background: var(--info-bg, #dbeafe); color: var(--info, #2563eb); }
        .perm-section .layer-customer { background: var(--success-bg, #dcfce7); color: var(--success, #16a34a); }
        .scroll-wrap { overflow-x: auto; border: 1px solid var(--border, #e2e8f0); border-radius: 8px; }
        </style>

        <div class="page-header">
            <p class="page-subtitle">Atur akses modul untuk setiap role. Centang modul yang boleh diakses oleh role terkait.</p>
        </div>

        <form method="post" action="/permissions">
            <?= csrf_field() ?>

            <?php foreach (['company' => 'Company Layer', 'customer' => 'Customer Layer'] as $layer => $layerTitle):
                $groups = $layer === 'company' ? $companyGroups : $customerGroups;
                $layerRoles = array_filter($roles, fn($r) => $r['layer'] === $layer);
                if (empty($layerRoles) || empty($groups)) continue;
            ?>
            <div class="perm-section">
                <h3>
                    <?= e($layerTitle) ?>
                    <span class="layer-badge layer-<?= e($layer) ?>"><?= e($layer) ?></span>
                </h3>
                <div class="scroll-wrap">
                    <table class="perm-table">
                        <thead>
                            <tr>
                                <th style="min-width:140px">Role</th>
                                <?php foreach ($groups as $group => $mods): ?>
                                <th class="perm-group-header" colspan="<?= count($mods) ?>"><?= e($group) ?></th>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <th></th>
                                <?php foreach ($groups as $mods): ?>
                                <?php foreach ($mods as $m): ?>
                                <th><div class="mod-label"><?= e($m['name']) ?></div></th>
                                <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($layerRoles as $role):
                                $perms = $matrix[$role['id']] ?? [];
                            ?>
                            <tr>
                                <td class="role-name">
                                    <?= e(ucfirst(str_replace('_', ' ', $role['name']))) ?>
                                    <span class="role-layer"><?= e($role['description'] ?? '') ?></span>
                                </td>
                                <?php foreach ($groups as $mods):
                                    foreach ($mods as $m):
                                        $checked = !empty($perms[$m['key']]);
                                ?>
                                <td style="text-align:center">
                                    <input type="checkbox" name="perm[<?= $role['id'] ?>][<?= e($m['key']) ?>]" value="1" class="perm-check" <?= $checked ? 'checked' : '' ?>>
                                </td>
                                <?php endforeach; endforeach; ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="form-actions">
                <button type="submit" class="btn btn-success">Simpan Permission</button>
            </div>
        </form>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/Views/layout.php';
        renderLayout('Permission', $content, ['active' => 'permissions']);
    }

    public function update(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(['super_admin']);
        AuthMiddleware::validateCsrf();

        $repo = new PermissionRepository();
        $permissions = $_POST['perm'] ?? [];

        foreach ($permissions as $roleId => $modules) {
            $roleId = (int) $roleId;
            foreach ($modules as $moduleKey => $value) {
                $repo->setPermission($roleId, $moduleKey, true);
            }
            // Unchecked modules are NOT in $_POST, so we need to unset them
            // Get all module keys for this role and disable unchecked ones
            $existing = $repo->getRolePermissions($roleId);
            $postedKeys = array_keys($modules);
            foreach ($existing as $key => $val) {
                if (!in_array($key, $postedKeys, true)) {
                    $repo->setPermission($roleId, $key, false);
                }
            }
        }

        $_SESSION['_flash']['success'] = 'Permission berhasil disimpan';
        redirect('/permissions');
    }
}
