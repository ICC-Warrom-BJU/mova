<?php

class FuelController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');
        $repo = new FuelExpenseRepository();
        $filters = [
            'date_start' => $_GET['date_start'] ?? date('Y-m-d'),
            'date_end' => $_GET['date_end'] ?? date('Y-m-d'),
        ];
        $fuels = $repo->findFuelReports($filters);

        ob_start();
        ?>
        <div class="card">
            <div class="card-header">
                <div style="display:flex;align-items:center;gap:12px">
                    <h3>Fuel Report</h3>
                    <?php require __DIR__ . '/../CustomerPanel/Views/date_filter.php'; ?>
                </div>
                <a href="/customer/fuel/create" class="btn btn-primary btn-sm">+ Lapor BBM</a>
            </div>
            <div class="card-body">
                <?php if (empty($fuels)): ?>
                    <div class="empty-state"><p>Belum ada laporan BBM.</p></div>
                <?php else: ?>
                <div class="table-wrap"><table>
                    <thead><tr>
                        <th>Tanggal</th><th>Kendaraan</th><th>Jenis</th><th>Liter</th><th>Total Biaya</th><th>KM</th><th>Status</th><th>Aksi</th>
                    </tr></thead>
                    <tbody>
                        <?php $roleName = $_SESSION['_user']['role_name'] ?? $_SESSION['role'] ?? ''; ?>
                        <?php $canApprove = in_array($roleName, ['koordinator','supervisor','super_admin']); ?>
                        <?php foreach ($fuels as $f): ?>
                        <tr>
                            <td><?= $f['fuel_date'] ?></td>
                            <td><?= e($f['plate_number']) ?></td>
                            <td><?= e($f['fuel_type']) ?></td>
                            <td><?= number_format((float)$f['liters'], 1) ?> L</td>
                            <td>Rp <?= number_format((float)$f['total_cost'], 0, ',', '.') ?></td>
                            <td><?= $f['km_at_refuel'] ? number_format((int)$f['km_at_refuel']) : '-' ?></td>
                            <td><span class="badge badge-<?= e($f['status']) ?>"><?= e($f['status']) ?></span></td>
                            <td>
                                <?php if ($canApprove && $f['status'] === 'pending'): ?>
                                <form method="post" action="/customer/fuel/<?= $f['id'] ?>/approve" style="display:inline">
                                    <?= csrf_field() ?><button type="submit" class="btn btn-success btn-sm">Setuju</button>
                                </form>
                                <form method="post" action="/customer/fuel/<?= $f['id'] ?>/reject" style="display:inline">
                                    <?= csrf_field() ?><button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Tolak?')">Tolak</button>
                                </form>
                                <?php endif; ?>
                                <a href="/customer/fuel/<?= $f['id'] ?>/edit" class="btn btn-outline btn-sm">Edit</a>
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
        renderCustomerLayout('Fuel Report', $content, ['active' => 'fuel']);
    }

    public function create(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');
        $tenant = SessionMiddleware::getTenantContext();
        $customerId = $tenant->getCustomerId();

        $db = Database::getConnection();
        if ($tenant->isSuperAdmin()) {
            $vehicles = $db->query("SELECT v.*, c.name as customer_name FROM mova_vehicles v LEFT JOIN mova_customers c ON c.id = v.customer_id WHERE v.is_active = 1")->fetchAll();
            $trips = $db->query("SELECT t.*, v.plate_number FROM mova_trips t LEFT JOIN mova_vehicles v ON v.id = t.vehicle_id WHERE t.status IN ('in_progress','completed') ORDER BY t.trip_date DESC LIMIT 50")->fetchAll();
        } else {
            $vehicles = $db->prepare("SELECT * FROM mova_vehicles WHERE customer_id = ? AND is_active = 1");
            $vehicles->execute([$customerId]); $vehicles = $vehicles->fetchAll();
            $trips = $db->prepare("SELECT t.*, v.plate_number FROM mova_trips t LEFT JOIN mova_vehicles v ON v.id = t.vehicle_id WHERE t.customer_id = ? AND t.status IN ('in_progress','completed') ORDER BY t.trip_date DESC LIMIT 50");
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
            $repo->createFuelReport([
                'customer_id' => $customerId,
                'trip_id' => $_POST['trip_id'] ?? null,
                'vehicle_id' => $_POST['vehicle_id'],
                'reported_by' => $_SESSION['user_id'],
                'fuel_date' => $_POST['fuel_date'],
                'fuel_type' => $_POST['fuel_type'],
                'liters' => (float)$_POST['liters'],
                'price_per_liter' => (float)$_POST['price_per_liter'],
                'km_at_refuel' => $_POST['km_at_refuel'] ?? null,
                'station_name' => $_POST['station_name'] ?? null,
            ]);
            $_SESSION['_flash']['success'] = 'Laporan BBM berhasil disimpan';
            redirect('/customer/fuel');
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Lapor BBM</h3></div>
            <div class="card-body">
                <form method="post" style="max-width:600px">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Kendaraan *</label>
                            <select name="vehicle_id" class="form-control" required>
                                <option value="">-- Pilih --</option>
                                <?php foreach ($vehicles as $v): ?>
                                <option value="<?= $v['id'] ?>"><?= isset($v['customer_name']) ? '[' . e($v['customer_name']) . '] ' : '' ?><?= e($v['plate_number']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Trip (opsional)</label>
                            <select name="trip_id" class="form-control">
                                <option value="">-- Tidak terkait trip --</option>
                                <?php foreach ($trips as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= e($t['trip_number']) ?> — <?= $t['trip_date'] ?> — <?= e($t['plate_number'] ?? '-') ?> — <?= e($t['destination']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tanggal *</label>
                            <input type="date" name="fuel_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Jenis BBM *</label>
                            <select name="fuel_type" class="form-control" required>
                                <option value="pertalite">Pertalite</option>
                                <option value="pertamax">Pertamax</option>
                                <option value="solar">Solar</option>
                                <option value="dexlite">Dexlite</option>
                                <option value="pertamina_dex">Pertamina Dex</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Liter *</label>
                            <input type="number" step="0.01" name="liters" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Harga/Liter *</label>
                            <input type="number" step="50" name="price_per_liter" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>KM saat isi</label>
                            <input type="number" name="km_at_refuel" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Nama SPBU</label>
                            <input type="text" name="station_name" class="form-control">
                        </div>
                    </div>
                    <div style="margin-top:20px">
                        <button type="submit" class="btn btn-success">Simpan</button>
                        <a href="/customer/fuel" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../CustomerPanel/Views/layout.php';
        renderCustomerLayout('Lapor BBM', $content, ['active' => 'fuel']);
    }

    public function edit(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');
        $tenant = SessionMiddleware::getTenantContext();
        $customerId = $tenant->getCustomerId();
        $db = Database::getConnection();

        if ($tenant->isSuperAdmin()) {
            $fuel = $db->prepare("SELECT * FROM mova_fuel_reports WHERE id = ?");
            $fuel->execute([$id]); $fuel = $fuel->fetch();
            if ($fuel) $customerId = $fuel['customer_id'];
            $vehicles = $db->query("SELECT v.*, c.name as customer_name FROM mova_vehicles v LEFT JOIN mova_customers c ON c.id = v.customer_id WHERE v.is_active = 1")->fetchAll();
        } else {
            $fuel = $db->prepare("SELECT * FROM mova_fuel_reports WHERE id = ? AND customer_id = ?");
            $fuel->execute([$id, $customerId]); $fuel = $fuel->fetch();
            $vehicles = $db->prepare("SELECT * FROM mova_vehicles WHERE customer_id = ? AND is_active = 1");
            $vehicles->execute([$customerId]); $vehicles = $vehicles->fetchAll();
        }
        if (!$fuel) { $_SESSION['_flash']['error'] = 'Data tidak ditemukan'; redirect('/customer/fuel'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthMiddleware::validateCsrf();
            $totalCost = (float)$_POST['liters'] * (float)$_POST['price_per_liter'];
            $stmt = $db->prepare("UPDATE mova_fuel_reports SET vehicle_id=?, fuel_date=?, fuel_type=?, liters=?, price_per_liter=?, total_cost=?, km_at_refuel=?, station_name=? WHERE id=? AND customer_id=?");
            $stmt->execute([$_POST['vehicle_id'], $_POST['fuel_date'], $_POST['fuel_type'], (float)$_POST['liters'], (float)$_POST['price_per_liter'], $totalCost, $_POST['km_at_refuel'] ?? null, $_POST['station_name'] ?? null, $id, $customerId]);
            $_SESSION['_flash']['success'] = 'Laporan BBM diupdate';
            redirect('/customer/fuel');
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Edit Laporan BBM</h3></div>
            <div class="card-body">
                <form method="post" style="max-width:600px">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Kendaraan *</label>
                            <select name="vehicle_id" class="form-control" required>
                                <?php foreach ($vehicles as $v): ?>
                                <option value="<?= $v['id'] ?>" <?= $v['id'] == $fuel['vehicle_id'] ? 'selected' : '' ?>><?= isset($v['customer_name']) ? '[' . e($v['customer_name']) . '] ' : '' ?><?= e($v['plate_number']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Tanggal *</label>
                            <input type="date" name="fuel_date" class="form-control" value="<?= $fuel['fuel_date'] ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Jenis BBM *</label>
                            <select name="fuel_type" class="form-control" required>
                                <?php foreach (['Pertalite','Pertamax','Solar','Dexlite'] as $ft): ?>
                                <option value="<?= $ft ?>" <?= ($fuel['fuel_type'] ?? '') === $ft ? 'selected' : '' ?>><?= $ft ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Liter *</label>
                            <input type="number" step="0.1" name="liters" class="form-control" value="<?= $fuel['liters'] ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Harga/Liter *</label>
                            <input type="number" step="100" name="price_per_liter" class="form-control" value="<?= $fuel['price_per_liter'] ?>" required>
                        </div>
                        <div class="form-group">
                            <label>KM Saat Isi</label>
                            <input type="number" name="km_at_refuel" class="form-control" value="<?= $fuel['km_at_refuel'] ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Nama SPBU</label>
                        <input type="text" name="station_name" class="form-control" value="<?= e($fuel['station_name'] ?? '') ?>">
                    </div>
                    <div style="margin-top:20px">
                        <button type="submit" class="btn btn-success">Simpan</button>
                        <a href="/customer/fuel" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../CustomerPanel/Views/layout.php';
        renderCustomerLayout('Edit BBM', $content, ['active' => 'fuel']);
    }

    public function approve(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::validateCsrf();

        $repo = new FuelExpenseRepository();
        $report = $repo->findFuelReport($id);
        $repo->approveFuelReport($id, (int)$_SESSION['user_id']);

        if ($report) {
            createNotification([
                'user_id' => $report['reported_by'],
                'customer_id' => $report['customer_id'],
                'type' => 'fuel_report',
                'title' => 'Laporan BBM Disetujui',
                'message' => 'Laporan BBM ' . ($report['fuel_type'] ?? '') . ' ' . ($report['liters'] ?? 0) . 'L disetujui.',
                'reference_type' => 'fuel_reports',
                'reference_id' => $id,
            ]);
        }
        $_SESSION['_flash']['success'] = 'Laporan BBM disetujui';
        redirect('/customer/fuel');
    }

    public function reject(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::validateCsrf();

        $repo = new FuelExpenseRepository();
        $report = $repo->findFuelReport($id);
        $reason = $_POST['reason'] ?? 'Ditolak';
        $repo->rejectFuelReport($id, (int)$_SESSION['user_id'], $reason);

        if ($report) {
            createNotification([
                'user_id' => $report['reported_by'],
                'customer_id' => $report['customer_id'],
                'type' => 'fuel_report',
                'title' => 'Laporan BBM Ditolak',
                'message' => 'Laporan BBM ditolak. Alasan: ' . $reason,
                'reference_type' => 'fuel_reports',
                'reference_id' => $id,
            ]);
        }
        $_SESSION['_flash']['success'] = 'Laporan BBM ditolak';
        redirect('/customer/fuel');
    }
}
