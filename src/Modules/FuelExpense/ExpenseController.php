<?php

class ExpenseController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');
        $repo = new FuelExpenseRepository();
        $expenses = $repo->findExpenseReports();

        ob_start();
        ?>
        <div class="card">
            <div class="card-header">
                <h3>Expense Report</h3>
                <a href="/customer/expenses/create" class="btn btn-primary btn-sm">+ Tambah Biaya</a>
            </div>
            <div class="card-body">
                <?php if (empty($expenses)): ?>
                    <div class="empty-state"><p>Belum ada laporan biaya.</p></div>
                <?php else: ?>
                <div class="table-wrap"><table>
                    <thead><tr>
                        <th>Tanggal</th><th>Kendaraan</th><th>Kategori</th><th>Deskripsi</th><th>Jumlah</th><th>Status</th><th>Aksi</th>
                    </tr></thead>
                    <tbody>
                        <?php $roleName = $_SESSION['_user']['role_name'] ?? $_SESSION['role'] ?? ''; ?>
                        <?php $canApprove = in_array($roleName, ['koordinator','supervisor','super_admin']); ?>
                        <?php foreach ($expenses as $e): ?>
                        <tr>
                            <td><?= $e['expense_date'] ?></td>
                            <td><?= e($e['plate_number']) ?></td>
                            <td><?= e($e['category']) ?></td>
                            <td><?= e($e['description']) ?></td>
                            <td>Rp <?= number_format((float)$e['amount'], 0, ',', '.') ?></td>
                            <td><span class="badge badge-<?= e($e['status']) ?>"><?= e($e['status']) ?></span></td>
                            <td>
                                <?php if ($canApprove && $e['status'] === 'pending'): ?>
                                <form method="post" action="/customer/expenses/<?= $e['id'] ?>/approve" style="display:inline">
                                    <?= csrf_field() ?><button type="submit" class="btn btn-success btn-sm">Setuju</button>
                                </form>
                                <form method="post" action="/customer/expenses/<?= $e['id'] ?>/reject" style="display:inline">
                                    <?= csrf_field() ?><button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Tolak?')">Tolak</button>
                                </form>
                                <?php endif; ?>
                                <a href="/customer/expenses/<?= $e['id'] ?>/edit" class="btn btn-outline btn-sm">Edit</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../CustomerPanel/Views/layout.php';
        renderCustomerLayout('Expense Report', $content, ['active' => 'expenses']);
    }

    public function create(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');
        $tenant = SessionMiddleware::getTenantContext();
        $customerId = $tenant->getCustomerId();

        $db = Database::getConnection();
        if ($tenant->isSuperAdmin()) {
            $vehicles = $db->query("SELECT v.*, c.name as customer_name FROM mova_vehicles v LEFT JOIN mova_customers c ON c.id = v.customer_id WHERE v.is_active = 1 ORDER BY c.name, v.plate_number")->fetchAll();
            $trips = $db->query("SELECT * FROM mova_trips WHERE status IN ('in_progress','completed') ORDER BY trip_date DESC LIMIT 50")->fetchAll();
        } else {
            $vehicles = $db->prepare("SELECT * FROM mova_vehicles WHERE customer_id = ? AND is_active = 1");
            $vehicles->execute([$customerId]); $vehicles = $vehicles->fetchAll();
            $trips = $db->prepare("SELECT * FROM mova_trips WHERE customer_id = ? AND status IN ('in_progress','completed') ORDER BY trip_date DESC LIMIT 20");
            $trips->execute([$customerId]); $trips = $trips->fetchAll();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthMiddleware::validateCsrf();
            if ($tenant->isSuperAdmin()) {
                $vStmt = $db->prepare("SELECT customer_id FROM mova_vehicles WHERE id = ?");
                $vStmt->execute([$_POST['vehicle_id']]);
                $customerId = $vStmt->fetchColumn();
            }
            $repo = new FuelExpenseRepository();
            $repo->createExpenseReport([
                'customer_id' => $customerId,
                'trip_id' => $_POST['trip_id'] ?? null,
                'vehicle_id' => $_POST['vehicle_id'],
                'reported_by' => $_SESSION['user_id'],
                'expense_date' => $_POST['expense_date'],
                'category' => $_POST['category'],
                'description' => $_POST['description'],
                'amount' => (float)$_POST['amount'],
            ]);
            $_SESSION['_flash']['success'] = 'Biaya berhasil dilaporkan';
            redirect('/customer/expenses');
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Tambah Biaya</h3></div>
            <div class="card-body">
                <form method="post" style="max-width:600px">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Kendaraan *</label>
                            <select name="vehicle_id" class="form-control" required>
                                <?php foreach ($vehicles as $v): ?>
                                <option value="<?= $v['id'] ?>"><?= isset($v['customer_name']) ? '[' . e($v['customer_name']) . '] ' : '' ?><?= e($v['plate_number']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Kategori *</label>
                            <select name="category" class="form-control" required>
                                <option value="">-- Pilih --</option>
                                <?php foreach (configOptions('expense_category') as $opt): ?>
                                <option value="<?= e($opt['value']) ?>"><?= e($opt['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Deskripsi *</label>
                        <input type="text" name="description" class="form-control" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tanggal *</label>
                            <input type="date" name="expense_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Jumlah (Rp) *</label>
                            <input type="number" step="100" name="amount" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Trip (opsional)</label>
                        <select name="trip_id" class="form-control">
                            <option value="">-- Tidak terkait trip --</option>
                            <?php foreach ($trips as $t): ?>
                            <option value="<?= $t['id'] ?>"><?= e($t['trip_number']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="margin-top:20px">
                        <button type="submit" class="btn btn-success">Simpan</button>
                        <a href="/customer/expenses" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../CustomerPanel/Views/layout.php';
        renderCustomerLayout('Tambah Biaya', $content, ['active' => 'expenses']);
    }

    public function edit(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');
        $tenant = SessionMiddleware::getTenantContext();
        $customerId = $tenant->getCustomerId();
        $db = Database::getConnection();

        if ($tenant->isSuperAdmin()) {
            $exp = $db->prepare("SELECT * FROM mova_expense_reports WHERE id = ?");
            $exp->execute([$id]); $exp = $exp->fetch();
            if ($exp) $customerId = $exp['customer_id'];
            $vehicles = $db->query("SELECT v.*, c.name as customer_name FROM mova_vehicles v LEFT JOIN mova_customers c ON c.id = v.customer_id WHERE v.is_active = 1 ORDER BY c.name, v.plate_number")->fetchAll();
        } else {
            $exp = $db->prepare("SELECT * FROM mova_expense_reports WHERE id = ? AND customer_id = ?");
            $exp->execute([$id, $customerId]); $exp = $exp->fetch();
            $vehicles = $db->prepare("SELECT * FROM mova_vehicles WHERE customer_id = ? AND is_active = 1");
            $vehicles->execute([$customerId]); $vehicles = $vehicles->fetchAll();
        }
        if (!$exp) { $_SESSION['_flash']['error'] = 'Data tidak ditemukan'; redirect('/customer/expenses'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthMiddleware::validateCsrf();
            $stmt = $db->prepare("UPDATE mova_expense_reports SET vehicle_id=?, expense_date=?, category=?, description=?, amount=? WHERE id=? AND customer_id=?");
            $stmt->execute([$_POST['vehicle_id'], $_POST['expense_date'], $_POST['category'], $_POST['description'], (float)$_POST['amount'], $id, $customerId]);
            $_SESSION['_flash']['success'] = 'Biaya diupdate';
            redirect('/customer/expenses');
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Edit Biaya</h3></div>
            <div class="card-body">
                <form method="post" style="max-width:600px">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Kendaraan *</label>
                            <select name="vehicle_id" class="form-control" required>
                                <?php foreach ($vehicles as $v): ?>
                                <option value="<?= $v['id'] ?>" <?= $v['id'] == $exp['vehicle_id'] ? 'selected' : '' ?>><?= isset($v['customer_name']) ? '[' . e($v['customer_name']) . '] ' : '' ?><?= e($v['plate_number']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Tanggal *</label>
                            <input type="date" name="expense_date" class="form-control" value="<?= $exp['expense_date'] ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Kategori *</label>
                            <?php $curC = $exp['category'] ?? ''; $optsC = configOptions('expense_category'); ?>
                            <select name="category" class="form-control" required>
                                <?php if ($curC !== '' && !in_array($curC, array_column($optsC, 'value'), true)): ?>
                                <option value="<?= e($curC) ?>" selected><?= e(ucfirst($curC)) ?></option>
                                <?php endif; ?>
                                <?php foreach ($optsC as $opt): ?>
                                <option value="<?= e($opt['value']) ?>" <?= $curC === $opt['value'] ? 'selected' : '' ?>><?= e($opt['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Jumlah (Rp) *</label>
                            <input type="number" step="1000" name="amount" class="form-control" value="<?= $exp['amount'] ?>" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Deskripsi *</label>
                        <textarea name="description" class="form-control" required><?= e($exp['description']) ?></textarea>
                    </div>
                    <div style="margin-top:20px">
                        <button type="submit" class="btn btn-success">Simpan</button>
                        <a href="/customer/expenses" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../CustomerPanel/Views/layout.php';
        renderCustomerLayout('Edit Biaya', $content, ['active' => 'expenses']);
    }

    public function approve(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::validateCsrf();

        $repo = new FuelExpenseRepository();
        $report = $repo->findExpenseReport($id);
        $repo->approveExpenseReport($id, (int)$_SESSION['user_id']);

        if ($report) {
            createNotification([
                'user_id' => $report['reported_by'],
                'customer_id' => $report['customer_id'],
                'type' => 'expense_report',
                'title' => 'Biaya Disetujui',
                'message' => 'Biaya ' . ($report['category'] ?? '') . ' Rp' . number_format((float)$report['amount'], 0, ',', '.') . ' disetujui.',
                'reference_type' => 'expense_reports',
                'reference_id' => $id,
            ]);
        }
        $_SESSION['_flash']['success'] = 'Biaya disetujui';
        redirect('/customer/expenses');
    }

    public function reject(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::validateCsrf();

        $repo = new FuelExpenseRepository();
        $report = $repo->findExpenseReport($id);
        $reason = $_POST['reason'] ?? 'Ditolak';
        $repo->rejectExpenseReport($id, (int)$_SESSION['user_id'], $reason);

        if ($report) {
            createNotification([
                'user_id' => $report['reported_by'],
                'customer_id' => $report['customer_id'],
                'type' => 'expense_report',
                'title' => 'Biaya Ditolak',
                'message' => 'Biaya ditolak. Alasan: ' . $reason,
                'reference_type' => 'expense_reports',
                'reference_id' => $id,
            ]);
        }
        $_SESSION['_flash']['success'] = 'Biaya ditolak';
        redirect('/customer/expenses');
    }
}
