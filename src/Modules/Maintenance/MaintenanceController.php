<?php

class MaintenanceController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');
        $repo = new MaintenanceRepository();
        $schedules = $repo->findSchedulesWithRelations();
        $overdue = $repo->findOverdue();

        ob_start();
        ?>
        <?php if (!empty($overdue)): ?>
        <div class="alert alert-danger">
            <strong><?= count($overdue) ?> jadwal maintenance overdue!</strong>
        </div>
        <?php endif; ?>
        <div class="card">
            <div class="card-header">
                <h3>Maintenance Schedule</h3>
                <a href="/customer/maintenance/create" class="btn btn-primary btn-sm">+ Buat Jadwal</a>
            </div>
            <div class="card-body">
                <?php if (empty($schedules)): ?>
                    <div class="empty-state"><p>Belum ada jadwal maintenance.</p></div>
                <?php else: ?>
                <div class="table-wrap"><table>
                    <thead><tr>
                        <th>Kendaraan</th><th>Servis</th><th>Tipe</th><th>Threshold</th><th>Status</th><th>Aksi</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($schedules as $s): ?>
                        <tr>
                            <td><?= e($s['plate_number']) ?></td>
                            <td><?= e($s['service_type']) ?></td>
                            <td><?= e($s['trigger_type']) ?></td>
                            <td><?= $s['trigger_type'] === 'km_based' ? number_format((int)$s['km_threshold']) . ' KM' : ($s['scheduled_date'] ?? '-') ?></td>
                            <td><span class="badge badge-<?= e($s['status']) ?>"><?= e($s['status']) ?></span></td>
                            <td>
                                <a href="/customer/maintenance/<?= $s['id'] ?>/edit" class="btn btn-outline btn-sm">Edit</a>
                                <?php if ($s['status'] !== 'completed'): ?>
                                <form method="post" action="/customer/maintenance/<?= $s['id'] ?>/log" style="display:inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-success btn-sm">Log Servis</button>
                                </form>
                                <?php endif; ?>
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
        renderCustomerLayout('Maintenance', $content, ['active' => 'maintenance']);
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
        } else {
            $vehicles = $db->prepare("SELECT * FROM mova_vehicles WHERE customer_id = ? AND is_active = 1");
            $vehicles->execute([$customerId]); $vehicles = $vehicles->fetchAll();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthMiddleware::validateCsrf();
            if ($tenant->isSuperAdmin()) {
                $vStmt = $db->prepare("SELECT customer_id FROM mova_vehicles WHERE id = ?");
                $vStmt->execute([$_POST['vehicle_id']]);
                $customerId = $vStmt->fetchColumn();
            }
            $repo = new MaintenanceRepository();
            $repo->createSchedule([
                'customer_id' => $customerId,
                'vehicle_id' => $_POST['vehicle_id'],
                'service_type' => $_POST['service_type'],
                'trigger_type' => $_POST['trigger_type'],
                'km_threshold' => $_POST['km_threshold'] ?? null,
                'scheduled_date' => $_POST['scheduled_date'] ?? null,
                'reminder_days_before' => $_POST['reminder_days_before'] ?? 7,
                'notes' => $_POST['notes'] ?? null,
                'created_by' => $_SESSION['user_id'],
            ]);
            $_SESSION['_flash']['success'] = 'Jadwal maintenance dibuat';
            redirect('/customer/maintenance');
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Buat Jadwal Maintenance</h3></div>
            <div class="card-body">
                <form method="post" style="max-width:600px">
                    <?= csrf_field() ?>
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
                        <label>Jenis Servis *</label>
                        <input type="text" name="service_type" class="form-control" placeholder="Ganti Oli, Servis 20.000 KM" required>
                    </div>
                    <div class="form-group">
                        <label>Tipe Trigger *</label>
                        <select name="trigger_type" class="form-control" required onchange="toggleTrigger(this.value)">
                            <option value="km_based">Berdasarkan KM</option>
                            <option value="date_based">Berdasarkan Tanggal</option>
                        </select>
                    </div>
                    <div class="form-row" id="km_fields">
                        <div class="form-group">
                            <label>KM Threshold</label>
                            <input type="number" name="km_threshold" class="form-control" placeholder="20000">
                        </div>
                    </div>
                    <div class="form-row" id="date_fields" style="display:none">
                        <div class="form-group">
                            <label>Tanggal Jadwal</label>
                            <input type="date" name="scheduled_date" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Pengingat (hari sebelumnya)</label>
                        <input type="number" name="reminder_days_before" class="form-control" value="7">
                    </div>
                    <div class="form-group">
                        <label>Catatan</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <div style="margin-top:20px">
                        <button type="submit" class="btn btn-success">Simpan</button>
                        <a href="/customer/maintenance" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <script>
        function toggleTrigger(v) {
            document.getElementById('km_fields').style.display = v === 'km_based' ? '' : 'none';
            document.getElementById('date_fields').style.display = v === 'date_based' ? '' : 'none';
        }
        </script>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../CustomerPanel/Views/layout.php';
        renderCustomerLayout('Buat Jadwal', $content, ['active' => 'maintenance']);
    }

    public function logService(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');
        $tenant = SessionMiddleware::getTenantContext();
        $customerId = $tenant->getCustomerId();
        $repo = new MaintenanceRepository();
        $schedule = $repo->find($id);

        if (!$schedule) { $_SESSION['_flash']['error'] = 'Jadwal tidak ditemukan'; redirect('/customer/maintenance'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthMiddleware::validateCsrf();
            $repo->createLog([
                'schedule_id' => $id,
                'customer_id' => $customerId,
                'vehicle_id' => $schedule['vehicle_id'],
                'service_type' => $_POST['service_type'],
                'service_date' => $_POST['service_date'],
                'km_at_service' => $_POST['km_at_service'] ?? null,
                'workshop_name' => $_POST['workshop_name'] ?? null,
                'cost' => $_POST['cost'] ?? null,
                'notes' => $_POST['notes'] ?? null,
                'next_service_km' => $_POST['next_service_km'] ?? null,
                'next_service_date' => $_POST['next_service_date'] ?? null,
                'logged_by' => $_SESSION['user_id'],
            ]);
            $repo->updateScheduleStatus($id, 'completed');
            $_SESSION['_flash']['success'] = 'Servis berhasil dicatat';
            redirect('/customer/maintenance');
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Log Servis: <?= e($schedule['service_type']) ?></h3></div>
            <div class="card-body">
                <form method="post" style="max-width:600px">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label>Jenis Servis *</label>
                        <input type="text" name="service_type" class="form-control" value="<?= e($schedule['service_type']) ?>" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tanggal Servis *</label>
                            <input type="date" name="service_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>KM Saat Servis</label>
                            <input type="number" name="km_at_service" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nama Workshop</label>
                            <input type="text" name="workshop_name" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Biaya (Rp)</label>
                            <input type="number" step="1000" name="cost" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Catatan</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Next Service KM</label>
                            <input type="number" name="next_service_km" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Next Service Date</label>
                            <input type="date" name="next_service_date" class="form-control">
                        </div>
                    </div>
                    <div style="margin-top:20px">
                        <button type="submit" class="btn btn-success">Simpan Log</button>
                        <a href="/customer/maintenance" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../CustomerPanel/Views/layout.php';
        renderCustomerLayout('Log Servis', $content, ['active' => 'maintenance']);
    }

    public function edit(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');
        $tenant = SessionMiddleware::getTenantContext();
        $customerId = $tenant->getCustomerId();
        $db = Database::getConnection();

        if ($tenant->isSuperAdmin()) {
            $schedule = $db->prepare("SELECT * FROM mova_maintenance_schedules WHERE id = ?");
            $schedule->execute([$id]); $schedule = $schedule->fetch();
            if ($schedule) $customerId = $schedule['customer_id'];
            $vehicles = $db->query("SELECT v.*, c.name as customer_name FROM mova_vehicles v LEFT JOIN mova_customers c ON c.id = v.customer_id WHERE v.is_active = 1 ORDER BY c.name, v.plate_number")->fetchAll();
        } else {
            $schedule = $db->prepare("SELECT * FROM mova_maintenance_schedules WHERE id = ? AND customer_id = ?");
            $schedule->execute([$id, $customerId]); $schedule = $schedule->fetch();
            $vehicles = $db->prepare("SELECT * FROM mova_vehicles WHERE customer_id = ? AND is_active = 1");
            $vehicles->execute([$customerId]); $vehicles = $vehicles->fetchAll();
        }
        if (!$schedule) { $_SESSION['_flash']['error'] = 'Data tidak ditemukan'; redirect('/customer/maintenance'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthMiddleware::validateCsrf();
            $stmt = $db->prepare("UPDATE mova_maintenance_schedules SET vehicle_id=?, service_type=?, trigger_type=?, km_threshold=?, scheduled_date=?, notes=? WHERE id=? AND customer_id=?");
            $stmt->execute([$_POST['vehicle_id'], $_POST['service_type'], $_POST['trigger_type'], $_POST['km_threshold'] ?? null, $_POST['scheduled_date'] ?? null, $_POST['notes'] ?? null, $id, $customerId]);
            $_SESSION['_flash']['success'] = 'Jadwal maintenance diupdate';
            redirect('/customer/maintenance');
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Edit Jadwal Maintenance</h3></div>
            <div class="card-body">
                <form method="post" style="max-width:600px">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label>Kendaraan *</label>
                        <select name="vehicle_id" class="form-control" required>
                            <?php foreach ($vehicles as $v): ?>
                            <option value="<?= $v['id'] ?>" <?= $v['id'] == $schedule['vehicle_id'] ? 'selected' : '' ?>><?= isset($v['customer_name']) ? '[' . e($v['customer_name']) . '] ' : '' ?><?= e($v['plate_number']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Jenis Servis *</label>
                        <input type="text" name="service_type" class="form-control" value="<?= e($schedule['service_type']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Tipe Trigger *</label>
                        <select name="trigger_type" class="form-control" required onchange="toggleTrigger(this.value)">
                            <option value="km_based" <?= $schedule['trigger_type'] === 'km_based' ? 'selected' : '' ?>>Berdasarkan KM</option>
                            <option value="date_based" <?= $schedule['trigger_type'] === 'date_based' ? 'selected' : '' ?>>Berdasarkan Tanggal</option>
                        </select>
                    </div>
                    <div class="form-row" id="km_fields" style="<?= $schedule['trigger_type'] === 'date_based' ? 'display:none' : '' ?>">
                        <div class="form-group">
                            <label>KM Threshold</label>
                            <input type="number" name="km_threshold" class="form-control" value="<?= $schedule['km_threshold'] ?>">
                        </div>
                    </div>
                    <div class="form-row" id="date_fields" style="<?= $schedule['trigger_type'] === 'km_based' ? 'display:none' : '' ?>">
                        <div class="form-group">
                            <label>Tanggal Jadwal</label>
                            <input type="date" name="scheduled_date" class="form-control" value="<?= $schedule['scheduled_date'] ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Catatan</label>
                        <textarea name="notes" class="form-control" rows="2"><?= e($schedule['notes'] ?? '') ?></textarea>
                    </div>
                    <div style="margin-top:20px">
                        <button type="submit" class="btn btn-success">Simpan</button>
                        <a href="/customer/maintenance" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <script>
        function toggleTrigger(v) {
            document.getElementById('km_fields').style.display = v === 'km_based' ? '' : 'none';
            document.getElementById('date_fields').style.display = v === 'date_based' ? '' : 'none';
        }
        </script>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../CustomerPanel/Views/layout.php';
        renderCustomerLayout('Edit Jadwal', $content, ['active' => 'maintenance']);
    }
}
