<?php

class RegionController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('company');

        $repo = new RegionRepository();
        $showInactive = !empty($_GET['show_inactive']);
        $all = $showInactive ? $repo->findAll() : $repo->findActive();

        ob_start();
        ?>
        <div class="card">
            <div class="card-header">
                <h3>Daftar Region</h3>
                <div class="header-actions">
                    <?= inactiveToggle($showInactive) ?>
                    <a href="/regions/create" class="btn btn-primary btn-sm">+ Tambah Region</a>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($all)): ?>
                    <div class="empty-state"><p>Belum ada region. Tambah region baru.</p></div>
                <?php else: ?>
                <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama Region</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all as $r): ?>
                        <tr>
                            <td><strong><?= e($r['code']) ?></strong></td>
                            <td><?= e($r['name']) ?></td>
                            <td><span class="badge badge-<?= $r['is_active'] ? 'active' : 'inactive' ?>"><?= $r['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                            <td>
                                <a href="/regions/<?= $r['id'] ?>/edit" class="btn btn-warning btn-sm">Edit</a>
                                <form method="post" action="/regions/<?= $r['id'] ?>/delete" class="form-inline" onsubmit="return confirm('Hapus region ini?')">
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
        renderLayout('Region', $content, ['active' => 'regions']);
    }

    public function create(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('company');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthMiddleware::validateCsrf($_POST['_csrf'] ?? '');
            $repo = new RegionRepository();

            if (empty($_POST['name']) || empty($_POST['code'])) {
                $_SESSION['_flash']['error'] = 'Nama dan kode region wajib diisi';
                redirect('/regions/create');
            }

            if ($repo->findByCode($_POST['code'])) {
                $_SESSION['_flash']['error'] = 'Kode region sudah digunakan';
                redirect('/regions/create');
            }

            $repo->create($_POST);
            $_SESSION['_flash']['success'] = 'Region berhasil dibuat';
            redirect('/regions');
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Tambah Region</h3></div>
            <div class="card-body">
                <form method="post" class="form-narrow">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label>Kode Region *</label>
                        <input type="text" name="code" class="form-control" placeholder="JKT" required>
                    </div>
                    <div class="form-group">
                        <label>Nama Region *</label>
                        <input type="text" name="name" class="form-control" placeholder="Jakarta" required>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" value="1" checked id="is_active">
                        <label for="is_active">Aktif</label>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Simpan</button>
                        <a href="/regions" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/Views/layout.php';
        renderLayout('Tambah Region', $content, ['active' => 'regions']);
    }

    public function edit(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('company');

        $repo = new RegionRepository();
        $region = $repo->find($id);

        if (!$region) {
            $_SESSION['_flash']['error'] = 'Region tidak ditemukan';
            redirect('/regions');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthMiddleware::validateCsrf($_POST['_csrf'] ?? '');

            if (empty($_POST['name']) || empty($_POST['code'])) {
                $_SESSION['_flash']['error'] = 'Nama dan kode region wajib diisi';
                redirect('/regions/' . $id . '/edit');
            }

            $existing = $repo->findByCode($_POST['code']);
            if ($existing && $existing['id'] != $id) {
                $_SESSION['_flash']['error'] = 'Kode region sudah digunakan';
                redirect('/regions/' . $id . '/edit');
            }

            $repo->update($id, $_POST);
            $_SESSION['_flash']['success'] = 'Region berhasil diupdate';
            redirect('/regions');
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Edit Region</h3></div>
            <div class="card-body">
                <form method="post" class="form-narrow">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label>Kode Region</label>
                        <input type="text" name="code" class="form-control" value="<?= e($region['code']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Nama Region</label>
                        <input type="text" name="name" class="form-control" value="<?= e($region['name']) ?>" required>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" value="1" <?= $region['is_active'] ? 'checked' : '' ?> id="is_active">
                        <label for="is_active">Aktif</label>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Simpan</button>
                        <a href="/regions" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/Views/layout.php';
        renderLayout('Edit Region', $content, ['active' => 'regions']);
    }

    public function delete(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('company');
        AuthMiddleware::validateCsrf($_POST['_csrf'] ?? '');

        try {
            (new RegionRepository())->softDelete($id);
            $_SESSION['_flash']['success'] = 'Region berhasil dinonaktifkan';
        } catch (\Throwable $e) {
            $_SESSION['_flash']['error'] = 'Gagal menonaktifkan region';
        }
        redirect('/regions');
    }
}
