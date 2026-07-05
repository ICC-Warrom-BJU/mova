<?php

class CustomerVehicleController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');
        $tenant = SessionMiddleware::getTenantContext();
        $customerId = $tenant->getCustomerId();

        $db = Database::getConnection();
        $vehicles = $db->prepare("SELECT * FROM mova_vehicles WHERE customer_id = ? AND is_active = 1 ORDER BY plate_number");
        $vehicles->execute([$customerId]); $vehicles = $vehicles->fetchAll();

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Kendaraan Saya</h3></div>
            <div class="card-body">
                <?php if (empty($vehicles)): ?>
                    <div class="empty-state"><p>Belum ada kendaraan.</p></div>
                <?php else: ?>
                <div class="table-wrap"><table>
                    <thead><tr>
                        <th>Plat</th><th>Merk / Model</th><th>Tahun</th><th>Warna</th><th>Tipe</th><th>KM</th><th>Status</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($vehicles as $v): ?>
                        <tr>
                            <td><strong><a href="/customer/vehicles/<?= $v['id'] ?>" style="color:var(--brand);text-decoration:none"><?= e($v['plate_number']) ?></a></strong></td>
                            <td><?= e($v['brand']) ?> <?= e($v['model'] ?? '') ?></td>
                            <td><?= e($v['year'] ?? '-') ?></td>
                            <td><?= e($v['color'] ?? '-') ?></td>
                            <td><?= e($v['vehicle_type'] ?? '-') ?></td>
                            <td><?= number_format((int)$v['current_km']) ?> KM</td>
                            <td><span class="badge badge-<?= e($v['status']) ?>"><?= e($v['status']) ?></span></td>
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
        renderCustomerLayout('Kendaraan Saya', $content, ['active' => 'vehicles']);
    }

    public function detail(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');
        $tenant = SessionMiddleware::getTenantContext();
        $customerId = $tenant->getCustomerId();
        $db = Database::getConnection();

        $vehicle = $db->prepare("SELECT * FROM mova_vehicles WHERE id = ? AND customer_id = ?");
        $vehicle->execute([$id, $customerId]); $vehicle = $vehicle->fetch();
        if (!$vehicle) { $_SESSION['_flash']['error'] = 'Kendaraan tidak ditemukan'; redirect('/customer/vehicles'); }

        $trips = $db->prepare("SELECT * FROM mova_trips WHERE customer_id = ? AND vehicle_id = ? ORDER BY trip_date DESC LIMIT 10");
        $trips->execute([$customerId, $id]); $trips = $trips->fetchAll();

        $maintenance = $db->prepare("SELECT ms.*, ml.service_date, ml.km_at_service, ml.workshop_name, ml.cost FROM mova_maintenance_schedules ms LEFT JOIN mova_maintenance_logs ml ON ml.schedule_id = ms.id WHERE ms.customer_id = ? AND ms.vehicle_id = ? ORDER BY ml.service_date DESC LIMIT 10");
        $maintenance->execute([$customerId, $id]); $maintenance = $maintenance->fetchAll();

        $fuels = $db->prepare("SELECT * FROM mova_fuel_reports WHERE customer_id = ? AND vehicle_id = ? ORDER BY fuel_date DESC LIMIT 10");
        $fuels->execute([$customerId, $id]); $fuels = $fuels->fetchAll();

        ob_start();
        ?>
        <div class="card">
            <div class="card-header">
                <h3><?= e($vehicle['plate_number']) ?> — Detail Kendaraan</h3>
                <a href="/customer/vehicles" class="btn btn-outline btn-sm">Kembali</a>
            </div>
            <div class="card-body">
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px">
                    <div>
                        <table style="font-size:13px">
                            <tr><td style="padding:4px 8px;color:var(--text-2)">Merk</td><td style="padding:4px 8px"><?= e($vehicle['brand']) ?></td></tr>
                            <tr><td style="padding:4px 8px;color:var(--text-2)">Model</td><td style="padding:4px 8px"><?= e($vehicle['model'] ?? '-') ?></td></tr>
                            <tr><td style="padding:4px 8px;color:var(--text-2)">Tahun</td><td style="padding:4px 8px"><?= e($vehicle['year'] ?? '-') ?></td></tr>
                        </table>
                    </div>
                    <div>
                        <table style="font-size:13px">
                            <tr><td style="padding:4px 8px;color:var(--text-2)">Warna</td><td style="padding:4px 8px"><?= e($vehicle['color'] ?? '-') ?></td></tr>
                            <tr><td style="padding:4px 8px;color:var(--text-2)">Tipe</td><td style="padding:4px 8px"><?= e($vehicle['vehicle_type'] ?? '-') ?></td></tr>
                            <tr><td style="padding:4px 8px;color:var(--text-2)">KM Saat Ini</td><td style="padding:4px 8px"><strong><?= number_format((int)$vehicle['current_km']) ?> KM</strong></td></tr>
                        </table>
                    </div>
                    <div>
                        <table style="font-size:13px">
                            <tr><td style="padding:4px 8px;color:var(--text-2)">No. Rangka</td><td style="padding:4px 8px"><?= e($vehicle['vin'] ?? '-') ?></td></tr>
                            <tr><td style="padding:4px 8px;color:var(--text-2)">No. Mesin</td><td style="padding:4px 8px"><?= e($vehicle['engine_number'] ?? '-') ?></td></tr>
                            <tr><td style="padding:4px 8px;color:var(--text-2)">Status</td><td style="padding:4px 8px"><span class="badge badge-<?= e($vehicle['status']) ?>"><?= e($vehicle['status']) ?></span></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px">
            <div class="card">
                <div class="card-header"><h3>Trip Terakhir (<?= count($trips) ?>)</h3></div>
                <div class="card-body">
                    <?php if (empty($trips)): ?>
                    <div class="empty-state"><p>Belum ada trip.</p></div>
                    <?php else: ?>
                    <table style="font-size:12px">
                        <thead><tr><th>No</th><th>Rute</th><th>Tanggal</th><th>KM</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($trips as $t): ?>
                            <tr>
                                <td><a href="/customer/trips/<?= $t['id'] ?>" style="color:var(--brand)"><?= e($t['trip_number']) ?></a></td>
                                <td><?= e($t['origin']) ?> → <?= e($t['destination']) ?></td>
                                <td><?= $t['trip_date'] ?></td>
                                <td><?= $t['km_start'] ? number_format((int)$t['km_start']) . '-' . number_format((int)$t['km_end']) : '-' ?></td>
                                <td><span class="badge badge-<?= e($t['status']) ?>"><?= e($t['status']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3>Maintenance (<?= count($maintenance) ?>)</h3></div>
                <div class="card-body">
                    <?php if (empty($maintenance)): ?>
                    <div class="empty-state"><p>Belum ada jadwal maintenance.</p></div>
                    <?php else: ?>
                    <table style="font-size:12px">
                        <thead><tr><th>Servis</th><th>Tanggal</th><th>KM</th><th>Biaya</th></tr></thead>
                        <tbody>
                            <?php foreach ($maintenance as $m): ?>
                            <tr>
                                <td><?= e($m['service_type']) ?></td>
                                <td><?= $m['service_date'] ?? '-' ?></td>
                                <td><?= $m['km_at_service'] ? number_format((int)$m['km_at_service']) : '-' ?></td>
                                <td><?= $m['cost'] ? 'Rp ' . number_format((float)$m['cost'], 0, ',', '.') : '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3>BBM Terakhir (<?= count($fuels) ?>)</h3></div>
            <div class="card-body">
                <?php if (empty($fuels)): ?>
                <div class="empty-state"><p>Belum ada laporan BBM.</p></div>
                <?php else: ?>
                <table style="font-size:12px">
                    <thead><tr><th>Tanggal</th><th>Jenis</th><th>Liter</th><th>Biaya</th><th>KM</th></tr></thead>
                    <tbody>
                        <?php foreach ($fuels as $f): ?>
                        <tr>
                            <td><?= $f['fuel_date'] ?></td>
                            <td><?= e($f['fuel_type']) ?></td>
                            <td><?= $f['liters'] ?>L</td>
                            <td>Rp <?= number_format((float)$f['total_cost'], 0, ',', '.') ?></td>
                            <td><?= $f['km_at_refuel'] ? number_format((int)$f['km_at_refuel']) : '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../CustomerPanel/Views/layout.php';
        renderCustomerLayout('Detail Kendaraan', $content, ['active' => 'vehicles']);
    }
}
