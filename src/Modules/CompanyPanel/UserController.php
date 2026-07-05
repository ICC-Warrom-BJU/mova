<?php

class UserController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('company');

        $repo = new UserRepository();
        $users = $repo->findWithRole();

        ob_start();
        ?>
        <div class="card">
            <div class="card-header">
                <h3>Daftar User</h3>
                <a href="/users/create" class="btn btn-primary btn-sm">+ Tambah User</a>
            </div>
            <div class="card-body">
                <?php if (empty($users)): ?>
                    <div class="empty-state"><p>Belum ada user.</p></div>
                <?php else: ?>
                <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Telepon</th>
                            <th>Terakhir Login</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><strong><?= e($u['name']) ?></strong></td>
                            <td><?= e($u['email']) ?></td>
                            <td><span class="badge badge-<?= e($u['role_name'] ?? '') ?>"><?= e($u['role_name'] ?? '-') ?></span></td>
                            <td><?= e($u['phone'] ?? '-') ?></td>
                            <td><?= $u['last_login_at'] ? date('d/m/Y H:i', strtotime($u['last_login_at'])) : '-' ?></td>
                            <td><span class="badge badge-<?= $u['is_active'] ? 'active' : 'inactive' ?>"><?= $u['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                            <td>
                                <a href="/users/<?= $u['id'] ?>/edit" class="btn btn-warning btn-sm">Edit</a>
                                <form method="post" action="/users/<?= $u['id'] ?>/delete" class="form-inline" onsubmit="return confirm('Hapus user ini?')">
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
        renderLayout('User', $content, ['active' => 'users']);
    }

    public function create(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('company');
        $tenant = SessionMiddleware::getTenantContext();

        $db = Database::getConnection();

        $roles = $db->query("SELECT * FROM mova_roles ORDER BY name")->fetchAll();

        $customerRepo = new CustomerRepository();
        if ($tenant->isSuperAdmin()) {
            $customers = $customerRepo->findActive();
        } else {
            $customerIds = $tenant->getAccessibleCustomerIds();
            $customers = !empty($customerIds) ? $customerRepo->findByIds($customerIds) : [];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthMiddleware::validateCsrf($_POST['_csrf'] ?? '');
            $repo = new UserRepository();

            if (empty($_POST['name']) || empty($_POST['email']) || empty($_POST['role_id'])) {
                $_SESSION['_flash']['error'] = 'Nama, email, dan role wajib diisi';
                redirect('/users/create');
            }

            if ($repo->findByEmail($_POST['email'])) {
                $_SESSION['_flash']['error'] = 'Email sudah digunakan';
                redirect('/users/create');
            }

            $repo->create($_POST);

            $passwordPlain = $_POST['password'] ?? 'auto-generated';
            $_SESSION['_flash']['success'] = 'User berhasil dibuat' . ($passwordPlain === 'auto-generated' ? ' (password dikirim via email/telegram)' : '');
            redirect('/users');
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Tambah User</h3></div>
            <div class="card-body">
                <form method="post" class="form-wide">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nama *</label>
                            <input type="text" name="name" class="form-control" placeholder="Nama lengkap" required>
                        </div>
                        <div class="form-group">
                            <label>Email *</label>
                            <input type="email" name="email" class="form-control" placeholder="user@example.com" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Role *</label>
                            <select name="role_id" class="form-control" required>
                                <option value="">-- Pilih Role --</option>
                                <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= e($r['name']) ?> (<?= e($r['layer']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Customer (jika Customer Layer)</label>
                            <select name="customer_id" class="form-control">
                                <option value="">-- BJU Internal --</option>
                                <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Password (kosongkan untuk auto-generate)</label>
                            <input type="password" name="password" class="form-control" placeholder="Min. 8 karakter">
                        </div>
                        <div class="form-group">
                            <label>Telepon</label>
                            <input type="text" name="phone" class="form-control" placeholder="0812xxxx">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Telegram Chat ID (untuk notifikasi)</label>
                        <input type="text" name="telegram_chat_id" class="form-control" placeholder="123456789">
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" value="1" checked id="is_active">
                        <label for="is_active">Aktif</label>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Simpan</button>
                        <a href="/users" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/Views/layout.php';
        renderLayout('Tambah User', $content, ['active' => 'users']);
    }

    public function edit(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('company');
        $tenant = SessionMiddleware::getTenantContext();

        $repo = new UserRepository();
        $user = $repo->find($id);

        if (!$user) {
            $_SESSION['_flash']['error'] = 'User tidak ditemukan';
            redirect('/users');
        }

        $db = Database::getConnection();
        $roles = $db->query("SELECT * FROM mova_roles ORDER BY name")->fetchAll();

        $customerRepo = new CustomerRepository();
        if ($tenant->isSuperAdmin()) {
            $customers = $customerRepo->findActive();
        } else {
            $customerIds = $tenant->getAccessibleCustomerIds();
            $customers = !empty($customerIds) ? $customerRepo->findByIds($customerIds) : [];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthMiddleware::validateCsrf($_POST['_csrf'] ?? '');

            if (empty($_POST['name']) || empty($_POST['email']) || empty($_POST['role_id'])) {
                $_SESSION['_flash']['error'] = 'Nama, email, dan role wajib diisi';
                redirect('/users/' . $id . '/edit');
            }

            $existing = $repo->findByEmail($_POST['email']);
            if ($existing && $existing['id'] != $id) {
                $_SESSION['_flash']['error'] = 'Email sudah digunakan';
                redirect('/users/' . $id . '/edit');
            }

            $repo->update($id, $_POST);
            $_SESSION['_flash']['success'] = 'User berhasil diupdate';
            redirect('/users');
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Edit User</h3></div>
            <div class="card-body">
                <form method="post" class="form-wide">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nama</label>
                            <input type="text" name="name" class="form-control" value="<?= e($user['name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" value="<?= e($user['email']) ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Role</label>
                            <select name="role_id" class="form-control" required>
                                <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id'] ?>" <?= $user['role_id'] == $r['id'] ? 'selected' : '' ?>><?= e($r['name']) ?> (<?= e($r['layer']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Customer</label>
                            <select name="customer_id" class="form-control">
                                <option value="">-- BJU Internal --</option>
                                <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $user['customer_id'] == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Password baru (kosongkan jika tidak diubah)</label>
                            <input type="password" name="password" class="form-control" placeholder="Min. 8 karakter">
                        </div>
                        <div class="form-group">
                            <label>Telepon</label>
                            <input type="text" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" name="is_active" value="1" <?= $user['is_active'] ? 'checked' : '' ?> id="is_active">
                        <label for="is_active">Aktif</label>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Simpan</button>
                        <a href="/users" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/Views/layout.php';
        renderLayout('Edit User', $content, ['active' => 'users']);
    }

    public function delete(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('company');
        AuthMiddleware::validateCsrf($_POST['_csrf'] ?? '');

        $repo = new UserRepository();
        $repo->delete($id);

        $_SESSION['_flash']['success'] = 'User berhasil dihapus';
        redirect('/users');
    }
}
