<?php

class BranchController
{
    private function getRegionRepo(): RegionRepository
    {
        return new RegionRepository();
    }

    public function index(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('company');
        $tenant = SessionMiddleware::getTenantContext();

        $repo = new BranchRepository();
        if ($tenant->isSuperAdmin()) {
            $branches = $repo->findWithRegion();
        } else {
            $branchIds = $tenant->getBranchIds();
            $branches = !empty($branchIds) ? $repo->findByIds($branchIds) : [];
        }

        $showInactive = !empty($_GET['show_inactive']);
        if (!$showInactive) {
            $branches = array_values(array_filter($branches, fn($b) => (int)($b['is_active'] ?? 1) === 1));
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header">
                <h3>Daftar Branch</h3>
                <div class="header-actions">
                    <?= inactiveToggle($showInactive) ?>
                    <?php if ($tenant->isSuperAdmin()): ?>
                    <a href="/branches/create" class="btn btn-primary btn-sm">+ Tambah Branch</a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($branches)): ?>
                    <div class="empty-state"><p>Belum ada branch.</p></div>
                <?php else: ?>
                <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama Branch</th>
                            <th>Region</th>
                            <th>Telepon</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($branches as $b): ?>
                        <tr>
                            <td><strong><?= e($b['code']) ?></strong></td>
                            <td><?= e($b['name']) ?></td>
                            <td><?= e($b['region_name'] ?? '-') ?></td>
                            <td><?= e($b['phone'] ?? '-') ?></td>
                            <td><span class="badge badge-<?= $b['is_active'] ? 'active' : 'inactive' ?>"><?= $b['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                            <td>
                                <a href="/branches/<?= $b['id'] ?>/edit" class="btn btn-warning btn-sm">Edit</a>
                                <form method="post" action="/branches/<?= $b['id'] ?>/delete" class="form-inline" onsubmit="return confirm('Hapus branch ini?')">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
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
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/Views/layout.php';
        renderLayout('Branch', $content, ['active' => 'branches']);
    }

    public function create(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('company');

        $regionRepo = $this->getRegionRepo();
        $regions = $regionRepo->findActive();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthMiddleware::validateCsrf($_POST['_csrf'] ?? '');
            $repo = new BranchRepository();

            if (empty($_POST['name']) || empty($_POST['code']) || empty($_POST['region_id'])) {
                $_SESSION['_flash']['error'] = 'Nama, kode, dan region wajib diisi';
                redirect('/branches/create');
            }

            $repo->create($_POST);
            $_SESSION['_flash']['success'] = 'Branch berhasil dibuat';
            redirect('/branches');
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Tambah Branch</h3></div>
            <div class="card-body">
                <form method="post" class="form-narrow">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label>Region *</label>
                        <select name="region_id" class="form-control" required>
                            <option value="">-- Pilih Region --</option>
                            <?php foreach ($regions as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Kode Branch *</label>
                        <input type="text" name="code" class="form-control" placeholder="JKT-PST" required>
                    </div>
                    <div class="form-group">
                        <label>Nama Branch *</label>
                        <input type="text" name="name" class="form-control" placeholder="Jakarta Pusat" required>
                    </div>
                    <div class="form-group">
                        <label>Alamat</label>
                        <textarea name="address" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Telepon</label>
                            <input type="text" name="phone" class="form-control" placeholder="021-xxxx">
                        </div>
                        <div class="form-group">
                            <div class="form-check form-group--align-end">
                                <input type="checkbox" name="is_active" value="1" checked id="is_active">
                                <label for="is_active">Aktif</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Simpan</button>
                        <a href="/branches" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/Views/layout.php';
        renderLayout('Tambah Branch', $content, ['active' => 'branches']);
    }

    public function edit(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('company');

        $repo = new BranchRepository();
        $branch = $repo->find($id);

        if (!$branch) {
            $_SESSION['_flash']['error'] = 'Branch tidak ditemukan';
            redirect('/branches');
        }

        $regionRepo = $this->getRegionRepo();
        $regions = $regionRepo->findActive();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthMiddleware::validateCsrf($_POST['_csrf'] ?? '');

            if (empty($_POST['name']) || empty($_POST['code']) || empty($_POST['region_id'])) {
                $_SESSION['_flash']['error'] = 'Nama, kode, dan region wajib diisi';
                redirect('/branches/' . $id . '/edit');
            }

            $repo->update($id, $_POST);
            $_SESSION['_flash']['success'] = 'Branch berhasil diupdate';
            redirect('/branches');
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Edit Branch</h3></div>
            <div class="card-body">
                <form method="post" class="form-narrow">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label>Region *</label>
                        <select name="region_id" class="form-control" required>
                            <option value="">-- Pilih Region --</option>
                            <?php foreach ($regions as $r): ?>
                            <option value="<?= $r['id'] ?>" <?= $branch['region_id'] == $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Kode Branch</label>
                        <input type="text" name="code" class="form-control" value="<?= e($branch['code']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Nama Branch</label>
                        <input type="text" name="name" class="form-control" value="<?= e($branch['name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Alamat</label>
                        <textarea name="address" class="form-control" rows="2"><?= e($branch['address'] ?? '') ?></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Telepon</label>
                            <input type="text" name="phone" class="form-control" value="<?= e($branch['phone'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <div class="form-check form-group--align-end">
                                <input type="checkbox" name="is_active" value="1" <?= $branch['is_active'] ? 'checked' : '' ?> id="is_active">
                                <label for="is_active">Aktif</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Simpan</button>
                        <a href="/branches" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/Views/layout.php';
        renderLayout('Edit Branch', $content, ['active' => 'branches']);
    }

    public function delete(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('company');
        AuthMiddleware::validateCsrf($_POST['_csrf'] ?? '');

        try {
            (new BranchRepository())->softDelete($id);
            $_SESSION['_flash']['success'] = 'Branch berhasil dinonaktifkan';
        } catch (\Throwable $e) {
            $_SESSION['_flash']['error'] = 'Gagal menonaktifkan branch';
        }
        redirect('/branches');
    }
}
