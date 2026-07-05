<?php

class CustomerController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('company');

        $repo = new CustomerRepository();
        $customers = $repo->findWithBranch();

        ob_start();
        ?>
        <div class="card">
            <div class="card-header">
                <h3>Daftar Customer</h3>
                <a href="/customers/create" class="btn btn-primary btn-sm">+ Tambah Customer</a>
            </div>
            <div class="card-body">
                <?php if (empty($customers)): ?>
                    <div class="empty-state"><p>Belum ada customer.</p></div>
                <?php else: ?>
                <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Kode</th>
                            <th>Nama</th>
                            <th>Branch</th>
                            <th>Plan</th>
                            <th>Unit</th>
                            <th>Kontak</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customers as $c): ?>
                        <?php
                            $planName = strtolower($c['plan_name'] ?? '');
                            $planClass = 'badge-free';
                            if (str_contains($planName, 'premium')) $planClass = 'badge-premium';
                            elseif (str_contains($planName, 'enterprise')) $planClass = 'badge-enterprise';
                        ?>
                        <tr>
                            <td><strong><?= e($c['code']) ?></strong></td>
                            <td><?= e($c['name']) ?></td>
                            <td><?= e($c['branch_name']) ?></td>
                            <td><span class="badge <?= $planClass ?>"><?= e($c['plan_name'] ?? '-') ?></span></td>
                            <td><?= (int)$c['total_units'] ?></td>
                            <td><?= e($c['pic_name'] ?? '-') ?><br><small><?= e($c['pic_phone'] ?? '') ?></small></td>
                            <td><span class="badge badge-<?= $c['is_active'] ? 'active' : 'inactive' ?>"><?= $c['is_active'] ? 'Aktif' : 'Nonaktif' ?></span></td>
                            <td>
                                <a href="/customers/<?= $c['id'] ?>/edit" class="btn btn-warning btn-sm">Edit</a>
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
        renderLayout('Customer', $content, ['active' => 'customers']);
    }

    public function create(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('company');
        $tenant = SessionMiddleware::getTenantContext();

        $branchRepo = new BranchRepository();
        if ($tenant->isSuperAdmin()) {
            $branches = $branchRepo->findActive();
        } else {
            $branchIds = $tenant->getBranchIds();
            $branches = !empty($branchIds) ? $branchRepo->findByIds($branchIds) : [];
        }

        $db = Database::getConnection();
        $plans = $db->query("SELECT * FROM mova_subscription_plans WHERE is_active = 1 ORDER BY name")->fetchAll();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthMiddleware::validateCsrf($_POST['_csrf'] ?? '');
            $repo = new CustomerRepository();

            if (empty($_POST['name']) || empty($_POST['code']) || empty($_POST['branch_id']) || empty($_POST['subscription_plan_id'])) {
                $_SESSION['_flash']['error'] = 'Nama, kode, branch, dan plan wajib diisi';
                redirect('/customers/create');
            }

            $repo->create($_POST);
            $_SESSION['_flash']['success'] = 'Customer berhasil dibuat';
            redirect('/customers');
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Tambah Customer</h3></div>
            <div class="card-body">
                <form method="post" class="form-wide">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Kode *</label>
                            <input type="text" name="code" class="form-control" placeholder="CUST-001" required>
                        </div>
                        <div class="form-group">
                            <label>Nama Perusahaan *</label>
                            <input type="text" name="name" class="form-control" placeholder="PT. Contoh" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Branch *</label>
                            <select name="branch_id" class="form-control" required>
                                <option value="">-- Pilih Branch --</option>
                                <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['id'] ?>"><?= e($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Subscription Plan *</label>
                            <select name="subscription_plan_id" class="form-control" required>
                                <option value="">-- Pilih Plan --</option>
                                <?php foreach ($plans as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>PIC Name</label>
                            <input type="text" name="pic_name" class="form-control" placeholder="Nama contact person">
                        </div>
                        <div class="form-group">
                            <label>PIC Phone</label>
                            <input type="text" name="pic_phone" class="form-control" placeholder="0812xxxx">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>PIC Email</label>
                        <input type="email" name="pic_email" class="form-control" placeholder="pic@example.com">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Contract Start</label>
                            <input type="date" name="contract_start" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Contract End</label>
                            <input type="date" name="contract_end" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Total Unit</label>
                            <input type="number" name="total_units" class="form-control" value="0" min="0">
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
                        <a href="/customers" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/Views/layout.php';
        renderLayout('Tambah Customer', $content, ['active' => 'customers']);
    }

    public function edit(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('company');
        $tenant = SessionMiddleware::getTenantContext();

        $repo = new CustomerRepository();
        $customer = $repo->find($id);

        if (!$customer) {
            $_SESSION['_flash']['error'] = 'Customer tidak ditemukan';
            redirect('/customers');
        }

        $branchRepo = new BranchRepository();
        if ($tenant->isSuperAdmin()) {
            $branches = $branchRepo->findActive();
        } else {
            $branchIds = $tenant->getBranchIds();
            $branches = !empty($branchIds) ? $branchRepo->findByIds($branchIds) : [];
        }

        $db = Database::getConnection();
        $plans = $db->query("SELECT * FROM mova_subscription_plans WHERE is_active = 1 ORDER BY name")->fetchAll();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthMiddleware::validateCsrf($_POST['_csrf'] ?? '');

            if (empty($_POST['name']) || empty($_POST['code']) || empty($_POST['branch_id']) || empty($_POST['subscription_plan_id'])) {
                $_SESSION['_flash']['error'] = 'Nama, kode, branch, dan plan wajib diisi';
                redirect('/customers/' . $id . '/edit');
            }

            $repo->update($id, $_POST);
            $_SESSION['_flash']['success'] = 'Customer berhasil diupdate';
            redirect('/customers');
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Edit Customer</h3></div>
            <div class="card-body">
                <form method="post" class="form-wide">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Kode</label>
                            <input type="text" name="code" class="form-control" value="<?= e($customer['code']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Nama Perusahaan</label>
                            <input type="text" name="name" class="form-control" value="<?= e($customer['name']) ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Branch</label>
                            <select name="branch_id" class="form-control" required>
                                <?php foreach ($branches as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= $customer['branch_id'] == $b['id'] ? 'selected' : '' ?>><?= e($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Subscription Plan</label>
                            <select name="subscription_plan_id" class="form-control" required>
                                <?php foreach ($plans as $p): ?>
                                <option value="<?= $p['id'] ?>" <?= $customer['subscription_plan_id'] == $p['id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>PIC Name</label>
                            <input type="text" name="pic_name" class="form-control" value="<?= e($customer['pic_name'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>PIC Phone</label>
                            <input type="text" name="pic_phone" class="form-control" value="<?= e($customer['pic_phone'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>PIC Email</label>
                        <input type="email" name="pic_email" class="form-control" value="<?= e($customer['pic_email'] ?? '') ?>">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Contract Start</label>
                            <input type="date" name="contract_start" class="form-control" value="<?= e($customer['contract_start'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Contract End</label>
                            <input type="date" name="contract_end" class="form-control" value="<?= e($customer['contract_end'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Total Unit</label>
                            <input type="number" name="total_units" class="form-control" value="<?= (int)$customer['total_units'] ?>" min="0">
                        </div>
                        <div class="form-group">
                            <div class="form-check form-group--align-end">
                                <input type="checkbox" name="is_active" value="1" <?= $customer['is_active'] ? 'checked' : '' ?> id="is_active">
                                <label for="is_active">Aktif</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Simpan</button>
                        <a href="/customers" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/Views/layout.php';
        renderLayout('Edit Customer', $content, ['active' => 'customers']);
    }
}
