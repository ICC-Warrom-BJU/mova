<?php

class TripController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');
        $repo = new TripRepository();
        $filters = [
            'date_start' => $_GET['date_start'] ?? date('Y-m-d'),
            'date_end' => $_GET['date_end'] ?? date('Y-m-d'),
        ];
        $trips = $repo->findWithRelations($filters);

        ob_start();
        ?>
        <div class="card">
            <div class="card-header">
                <div style="display:flex;align-items:center;gap:12px">
                    <h3>Trip Log</h3>
                    <?php require __DIR__ . '/../CustomerPanel/Views/date_filter.php'; ?>
                </div>
                <a href="/customer/trips/create" class="btn btn-primary btn-sm">+ Input Trip</a>
            </div>
            <div class="card-body">
                <?php if (empty($trips)): ?>
                    <div class="empty-state"><p>Belum ada trip.</p></div>
                <?php else: ?>
                <div class="table-wrap"><table>
                    <thead><tr>
                        <th>No. Trip</th><th>Kendaraan</th><th>Driver</th><th>Rute</th><th>Tanggal</th><th>KM</th><th>Status</th><th>Aksi</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($trips as $t): ?>
                        <tr>
                            <td><strong><a href="/customer/trips/<?= $t['id'] ?>" style="color:var(--brand);text-decoration:none"><?= e($t['trip_number']) ?></a></strong></td>
                            <td><?= e($t['plate_number']) ?></td>
                            <td><?= e($t['driver_name']) ?></td>
                            <td><?= e($t['origin']) ?> → <?= e($t['destination']) ?></td>
                            <td><?= $t['trip_date'] ?></td>
                            <td><?= $t['km_start'] ? number_format((int)$t['km_start']) : '-' ?> / <?= $t['km_end'] ? number_format((int)$t['km_end']) : '...' ?></td>
                            <td><span class="badge badge-<?= e($t['status']) ?>"><?= e($t['status']) ?></span></td>
                            <td>
                                <?php if ($t['status'] === 'draft'): ?>
                                <a href="/customer/trips/<?= $t['id'] ?>/start" class="btn btn-success btn-sm">Mulai</a>
                                <a href="/customer/trips/<?= $t['id'] ?>/edit" class="btn btn-outline btn-sm">Edit</a>
                                <?php elseif ($t['status'] === 'in_progress'): ?>
                                <a href="/customer/trips/<?= $t['id'] ?>/complete" class="btn btn-warning btn-sm">Selesai</a>
                                <?php endif; ?>
                                <a href="/customer/trips/<?= $t['id'] ?>" class="btn btn-outline btn-sm">Detail</a>
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
        renderCustomerLayout('Trip Log', $content, ['active' => 'trips']);
    }

    public function create(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');
        $tenant = SessionMiddleware::getTenantContext();
        $customerId = $tenant->getCustomerId();

        $db = Database::getConnection();
        if ($tenant->isSuperAdmin()) {
            $vehicles = $db->query("SELECT v.*, c.name as customer_name, c.code as customer_code FROM mova_vehicles v LEFT JOIN mova_customers c ON c.id = v.customer_id WHERE v.is_active = 1 ORDER BY c.name, v.plate_number")->fetchAll();
            $drivers = $db->query("SELECT u.*, c.name as customer_name FROM mova_users u JOIN mova_roles r ON r.id = u.role_id LEFT JOIN mova_customers c ON c.id = u.customer_id WHERE r.name IN ('koordinator','driver') AND u.is_active = 1 ORDER BY c.name, u.name")->fetchAll();
        } else {
            $vehicles = $db->prepare("SELECT * FROM mova_vehicles WHERE customer_id = ? AND is_active = 1 ORDER BY plate_number");
            $vehicles->execute([$customerId]); $vehicles = $vehicles->fetchAll();
            $drivers = $db->prepare("SELECT u.* FROM mova_users u JOIN mova_roles r ON r.id = u.role_id WHERE u.customer_id = ? AND r.name IN ('koordinator','driver') AND u.is_active = 1 ORDER BY u.name");
            $drivers->execute([$customerId]); $drivers = $drivers->fetchAll();
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthMiddleware::validateCsrf();
            if ($tenant->isSuperAdmin()) {
                $vStmt = $db->prepare("SELECT customer_id FROM mova_vehicles WHERE id = ?");
                $vStmt->execute([$_POST['vehicle_id']]);
                $customerId = $vStmt->fetchColumn();
            }
            $repo = new TripRepository();
            $repo->create([
                'customer_id' => $customerId,
                'vehicle_id' => $_POST['vehicle_id'],
                'driver_id' => $_POST['driver_id'],
                'trip_number' => generateNumber('TRP'),
                'origin' => $_POST['origin'],
                'destination' => $_POST['destination'],
                'trip_date' => $_POST['trip_date'],
                'purpose_type' => $_POST['purpose_type'],
                'notes' => $_POST['notes'] ?? null,
                'input_by' => $_SESSION['user_id'],
            ]);
            $_SESSION['_flash']['success'] = 'Trip berhasil dibuat';
            redirect('/customer/trips');
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Input Trip Baru</h3></div>
            <div class="card-body">
                <form method="post" style="max-width:600px">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Kendaraan *</label>
                            <select name="vehicle_id" class="form-control" required>
                                <option value="">-- Pilih --</option>
                                <?php foreach ($vehicles as $v): ?>
                                <option value="<?= $v['id'] ?>"><?= isset($v['customer_code']) ? '[' . e($v['customer_code']) . '] ' : '' ?><?= e($v['plate_number']) ?> - <?= e($v['brand']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Driver *</label>
                            <select name="driver_id" class="form-control" required>
                                <option value="">-- Pilih --</option>
                                <?php foreach ($drivers as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= isset($d['customer_name']) ? '[' . e($d['customer_name']) . '] ' : '' ?><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Asal *</label>
                            <input type="text" name="origin" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Tujuan *</label>
                            <input type="text" name="destination" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tanggal *</label>
                            <input type="date" name="trip_date" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Tipe Perjalanan *</label>
                            <select name="purpose_type" class="form-control" required>
                                <option value="">-- Pilih --</option>
                                <?php foreach (configOptions('trip_purpose') as $opt): ?>
                                <option value="<?= e($opt['value']) ?>"><?= e($opt['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Catatan</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <div style="margin-top:20px">
                        <button type="submit" class="btn btn-success">Simpan</button>
                        <a href="/customer/trips" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../CustomerPanel/Views/layout.php';
        renderCustomerLayout('Input Trip', $content, ['active' => 'trips']);
    }

    public function start(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');

        $repo = new TripRepository();
        $trip = $repo->find($id);
        if (!$trip) { $_SESSION['_flash']['error'] = 'Trip tidak ditemukan'; redirect('/customer/trips'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthMiddleware::validateCsrf();
            $repo->startTrip($id, (int)$_POST['km_start'], $_POST['departure_time']);
            $_SESSION['_flash']['success'] = 'Trip dimulai';
            redirect('/customer/trips');
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Mulai Trip #<?= e($trip['trip_number']) ?></h3></div>
            <div class="card-body">
                <form method="post" style="max-width:400px">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label>KM Awal *</label>
                        <input type="number" name="km_start" class="form-control" required min="0">
                    </div>
                    <div class="form-group">
                        <label>Jam Berangkat</label>
                        <input type="time" name="departure_time" class="form-control">
                    </div>
                    <div style="margin-top:20px">
                        <button type="submit" class="btn btn-success">Mulai Trip</button>
                        <a href="/customer/trips" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../CustomerPanel/Views/layout.php';
        renderCustomerLayout('Mulai Trip', $content, ['active' => 'trips']);
    }

    public function complete(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');

        $repo = new TripRepository();
        $trip = $repo->find($id);
        if (!$trip) { $_SESSION['_flash']['error'] = 'Trip tidak ditemukan'; redirect('/customer/trips'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthMiddleware::validateCsrf();
            $kmEnd = (int)$_POST['km_end'];
            $kmStart = (int)$trip['km_start'];
            $distance = $kmEnd - $kmStart;
            $repo->completeTrip($id, $kmEnd, $distance, $_POST['return_time'] ?? null);
            $_SESSION['_flash']['success'] = 'Trip selesai (jarak: ' . number_format($distance) . ' km)';
            redirect('/customer/trips');
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Selesaikan Trip #<?= e($trip['trip_number']) ?></h3></div>
            <div class="card-body">
                <p style="margin-bottom:16px">KM awal: <strong><?= number_format((int)$trip['km_start']) ?></strong></p>
                <form method="post" style="max-width:400px">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label>KM Akhir *</label>
                        <input type="number" name="km_end" class="form-control" required min="<?= (int)$trip['km_start'] + 1 ?>">
                    </div>
                    <div class="form-group">
                        <label>Jam Kembali</label>
                        <input type="time" name="return_time" class="form-control">
                    </div>
                    <div style="margin-top:20px">
                        <button type="submit" class="btn btn-success">Selesaikan</button>
                        <a href="/customer/trips" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../CustomerPanel/Views/layout.php';
        renderCustomerLayout('Selesaikan Trip', $content, ['active' => 'trips']);
    }

    public function edit(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');
        $tenant = SessionMiddleware::getTenantContext();
        $customerId = $tenant->getCustomerId();

        $db = Database::getConnection();
        $trip = $db->prepare("SELECT * FROM mova_trips WHERE id = ? AND customer_id = ?");
        $trip->execute([$id, $customerId]); $trip = $trip->fetch();
        if (!$trip) { $_SESSION['_flash']['error'] = 'Trip tidak ditemukan'; redirect('/customer/trips'); }
        if ($trip['status'] !== 'draft') { $_SESSION['_flash']['error'] = 'Hanya trip draft yang bisa diedit'; redirect('/customer/trips'); }

        $vehicles = $db->prepare("SELECT * FROM mova_vehicles WHERE customer_id = ? AND is_active = 1 ORDER BY plate_number");
        $vehicles->execute([$customerId]); $vehicles = $vehicles->fetchAll();
        $drivers = $db->prepare("SELECT u.* FROM mova_users u JOIN mova_roles r ON r.id = u.role_id WHERE u.customer_id = ? AND r.name IN ('koordinator','driver') AND u.is_active = 1 ORDER BY u.name");
        $drivers->execute([$customerId]); $drivers = $drivers->fetchAll();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthMiddleware::validateCsrf();
            $stmt = $db->prepare("UPDATE mova_trips SET vehicle_id=?, driver_id=?, origin=?, destination=?, trip_date=?, purpose_type=?, notes=? WHERE id=? AND customer_id=? AND status='draft'");
            $stmt->execute([$_POST['vehicle_id'], $_POST['driver_id'], $_POST['origin'], $_POST['destination'], $_POST['trip_date'], $_POST['purpose_type'], $_POST['notes'] ?? null, $id, $customerId]);
            $_SESSION['_flash']['success'] = 'Trip berhasil diupdate';
            redirect('/customer/trips/' . $id);
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Edit Trip #<?= e($trip['trip_number']) ?></h3></div>
            <div class="card-body">
                <form method="post" style="max-width:600px">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Kendaraan *</label>
                            <select name="vehicle_id" class="form-control" required>
                                <?php foreach ($vehicles as $v): ?>
                                <option value="<?= $v['id'] ?>" <?= $v['id'] == $trip['vehicle_id'] ? 'selected' : '' ?>><?= e($v['plate_number']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Driver *</label>
                            <select name="driver_id" class="form-control" required>
                                <?php foreach ($drivers as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= $d['id'] == $trip['driver_id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Asal *</label>
                            <input type="text" name="origin" class="form-control" value="<?= e($trip['origin']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Tujuan *</label>
                            <input type="text" name="destination" class="form-control" value="<?= e($trip['destination']) ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tanggal *</label>
                            <input type="date" name="trip_date" class="form-control" value="<?= $trip['trip_date'] ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Tipe Perjalanan *</label>
                            <?php $curP = $trip['purpose_type'] ?? ''; $optsP = configOptions('trip_purpose'); ?>
                            <select name="purpose_type" class="form-control" required>
                                <?php if ($curP !== '' && !in_array($curP, array_column($optsP, 'value'), true)): ?>
                                <option value="<?= e($curP) ?>" selected><?= e(ucfirst($curP)) ?></option>
                                <?php endif; ?>
                                <?php foreach ($optsP as $opt): ?>
                                <option value="<?= e($opt['value']) ?>" <?= $curP === $opt['value'] ? 'selected' : '' ?>><?= e($opt['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Catatan</label>
                        <textarea name="notes" class="form-control" rows="2"><?= e($trip['notes'] ?? '') ?></textarea>
                    </div>
                    <div style="margin-top:20px">
                        <button type="submit" class="btn btn-success">Simpan</button>
                        <a href="/customer/trips/<?= $id ?>" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../CustomerPanel/Views/layout.php';
        renderCustomerLayout('Edit Trip', $content, ['active' => 'trips']);
    }

    public function detail(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');

        $db = Database::getConnection();
        $trip = $db->prepare(
            "SELECT t.*, v.plate_number, v.brand, v.model, driver.name AS driver_name, inputter.name AS input_by_name
             FROM mova_trips t
             JOIN mova_vehicles v ON v.id = t.vehicle_id
             JOIN mova_users driver ON driver.id = t.driver_id
             JOIN mova_users inputter ON inputter.id = t.input_by
             WHERE t.id = ?"
        );
        $trip->execute([$id]); $trip = $trip->fetch();
        if (!$trip) { $_SESSION['_flash']['error'] = 'Trip tidak ditemukan'; redirect('/customer/trips'); }

        $checklists = $db->prepare("SELECT * FROM mova_trip_checklists WHERE trip_id = ?");
        $checklists->execute([$id]); $checklists = $checklists->fetchAll();

        $photos = $db->prepare("SELECT * FROM mova_trip_photos WHERE trip_id = ? ORDER BY uploaded_at DESC");
        $photos->execute([$id]); $photos = $photos->fetchAll();

        $customerId = $trip['customer_id'];
        $fuels = $db->prepare("SELECT * FROM mova_fuel_reports WHERE customer_id = ? AND trip_id = ? ORDER BY fuel_date DESC");
        $fuels->execute([$customerId, $id]); $fuels = $fuels->fetchAll();

        $expenses = $db->prepare("SELECT * FROM mova_expense_reports WHERE customer_id = ? AND trip_id = ? ORDER BY expense_date DESC");
        $expenses->execute([$customerId, $id]); $expenses = $expenses->fetchAll();

        $preTrip = array_values(array_filter($checklists, fn($c) => $c['check_type'] === 'pre_trip'));
        $postTrip = array_values(array_filter($checklists, fn($c) => $c['check_type'] === 'post_trip'));

        ob_start();
        ?>
        <div class="card">
            <div class="card-header">
                <h3>Trip Detail — <?= e($trip['trip_number']) ?></h3>
                <div>
                    <?php if ($trip['status'] === 'draft'): ?>
                    <a href="/customer/trips/<?= $id ?>/start" class="btn btn-success btn-sm">Mulai</a>
                    <a href="/customer/trips/<?= $id ?>/edit" class="btn btn-outline btn-sm">Edit</a>
                    <?php elseif ($trip['status'] === 'in_progress'): ?>
                    <a href="/customer/trips/<?= $id ?>/complete" class="btn btn-warning btn-sm">Selesaikan</a>
                    <?php endif; ?>
                    <a href="/customer/checklists/<?= $id ?>" class="btn btn-outline btn-sm">Checklist</a>
                    <a href="/customer/trips" class="btn btn-outline btn-sm">Kembali</a>
                </div>
            </div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                    <div>
                        <table style="font-size:13px">
                            <tr><td style="padding:6px 12px;color:var(--text-2);width:120px">Kendaraan</td><td style="padding:6px 12px"><strong><?= e($trip['plate_number']) ?></strong> <?= e($trip['brand'] ?? '') ?> <?= e($trip['model'] ?? '') ?></td></tr>
                            <tr><td style="padding:6px 12px;color:var(--text-2)">Driver</td><td style="padding:6px 12px"><strong><?= e($trip['driver_name']) ?></strong></td></tr>
                            <tr><td style="padding:6px 12px;color:var(--text-2)">Rute</td><td style="padding:6px 12px"><?= e($trip['origin']) ?> → <?= e($trip['destination']) ?></td></tr>
                            <tr><td style="padding:6px 12px;color:var(--text-2)">Tanggal</td><td style="padding:6px 12px"><?= $trip['trip_date'] ?></td></tr>
                            <tr><td style="padding:6px 12px;color:var(--text-2)">Tipe</td><td style="padding:6px 12px"><?= e($trip['purpose_type'] ?? '-') ?></td></tr>
                        </table>
                    </div>
                    <div>
                        <table style="font-size:13px">
                            <tr><td style="padding:6px 12px;color:var(--text-2);width:120px">Status</td><td style="padding:6px 12px"><span class="badge badge-<?= e($trip['status']) ?>"><?= e($trip['status']) ?></span></td></tr>
                            <tr><td style="padding:6px 12px;color:var(--text-2)">KM Awal</td><td style="padding:6px 12px"><?= $trip['km_start'] ? number_format((int)$trip['km_start']) : '-' ?></td></tr>
                            <tr><td style="padding:6px 12px;color:var(--text-2)">KM Akhir</td><td style="padding:6px 12px"><?= $trip['km_end'] ? number_format((int)$trip['km_end']) : '-' ?></td></tr>
                            <tr><td style="padding:6px 12px;color:var(--text-2)">Jarak</td><td style="padding:6px 12px"><?= $trip['distance_km'] ? number_format((int)$trip['distance_km']) . ' km' : '-' ?></td></tr>
                            <tr><td style="padding:6px 12px;color:var(--text-2)">Input Oleh</td><td style="padding:6px 12px"><?= e($trip['input_by_name'] ?? '-') ?></td></tr>
                        </table>
                    </div>
                </div>
                <?php if ($trip['notes']): ?>
                <div style="margin-top:16px;padding:12px;background:#f8f9fc;border-radius:6px;font-size:13px">
                    <strong>Catatan:</strong> <?= e($trip['notes']) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
            <div class="card">
                <div class="card-header"><h3>Pre-Trip Checklist</h3></div>
                <div class="card-body">
                    <?php if (!$preTrip): ?>
                    <a href="/customer/checklists/<?= $id ?>/create/pre_trip" class="btn btn-primary btn-sm">Isi Checklist</a>
                    <?php else: $items = json_decode($preTrip[0]['items'], true); ?>
                    <p>Kondisi: <span class="badge badge-<?= $preTrip[0]['overall_condition'] === 'good' ? 'active' : 'danger' ?>"><?= e($preTrip[0]['overall_condition']) ?></span></p>
                    <table style="font-size:12px;margin-top:8px">
                        <?php foreach ($items as $item): ?>
                        <tr><td style="padding:3px 8px"><?= e($item['name'] ?? '') ?></td><td style="padding:3px 8px"><?= ($item['status'] ?? '') === 'ok' ? '✅' : '❌' ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3>Post-Trip Checklist</h3></div>
                <div class="card-body">
                    <?php if (!$postTrip): ?>
                    <a href="/customer/checklists/<?= $id ?>/create/post_trip" class="btn btn-primary btn-sm">Isi Checklist</a>
                    <?php else: $items = json_decode($postTrip[0]['items'], true); ?>
                    <p>Kondisi: <span class="badge badge-<?= $postTrip[0]['overall_condition'] === 'good' ? 'active' : 'danger' ?>"><?= e($postTrip[0]['overall_condition']) ?></span></p>
                    <table style="font-size:12px;margin-top:8px">
                        <?php foreach ($items as $item): ?>
                        <tr><td style="padding:3px 8px"><?= e($item['name'] ?? '') ?></td><td style="padding:3px 8px"><?= ($item['status'] ?? '') === 'ok' ? '✅' : '❌' ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($photos): ?>
        <div class="card">
            <div class="card-header"><h3>Foto (<?= count($photos) ?>)</h3><a href="/customer/checklists/<?= $id ?>/photos" class="btn btn-outline btn-sm">Tambah Foto</a></div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px">
                    <?php foreach ($photos as $p): ?>
                    <a href="/<?= e($p['file_path']) ?>" target="_blank">
                        <img src="/<?= e($p['file_path']) ?>" style="width:100%;height:100px;object-fit:cover;border-radius:6px;border:1px solid var(--border)">
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="card">
                <div class="card-header"><h3>BBM (<?= count($fuels) ?>)</h3></div>
                <div class="card-body">
                    <?php if (empty($fuels)): ?>
                    <div class="empty-state"><p>Tidak ada laporan BBM untuk trip ini.</p></div>
                    <?php else: ?>
                    <table style="font-size:12px">
                        <thead><tr><th>Tanggal</th><th>Liter</th><th>Biaya</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($fuels as $f): ?>
                            <tr><td><?= $f['fuel_date'] ?></td><td><?= $f['liters'] ?>L</td><td>Rp <?= number_format((float)$f['total_cost'], 0, ',', '.') ?></td><td><span class="badge badge-<?= e($f['status']) ?>"><?= e($f['status']) ?></span></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3>Biaya (<?= count($expenses) ?>)</h3></div>
                <div class="card-body">
                    <?php if (empty($expenses)): ?>
                    <div class="empty-state"><p>Tidak ada biaya untuk trip ini.</p></div>
                    <?php else: ?>
                    <table style="font-size:12px">
                        <thead><tr><th>Tanggal</th><th>Kategori</th><th>Jumlah</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($expenses as $e): ?>
                            <tr><td><?= $e['expense_date'] ?></td><td><?= e($e['category']) ?></td><td>Rp <?= number_format((float)$e['amount'], 0, ',', '.') ?></td><td><span class="badge badge-<?= e($e['status']) ?>"><?= e($e['status']) ?></span></td></tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../CustomerPanel/Views/layout.php';
        renderCustomerLayout('Trip Detail', $content, ['active' => 'trips']);
    }
}
