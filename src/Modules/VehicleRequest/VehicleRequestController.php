<?php

class VehicleRequestController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');
        $repo = new VehicleRequestRepository();
        $filters = [
            'date_start' => $_GET['date_start'] ?? date('Y-m-d'),
            'date_end' => $_GET['date_end'] ?? date('Y-m-d'),
        ];
        $requests = $repo->findWithRelations($filters);

        ob_start();
        ?>
        <div class="card">
            <div class="card-header">
                <div style="display:flex;align-items:center;gap:12px">
                    <h3>Vehicle Request</h3>
                    <?php require __DIR__ . '/../CustomerPanel/Views/date_filter.php'; ?>
                </div>
                <a href="/customer/requests/create" class="btn btn-primary btn-sm">+ Ajukan</a>
            </div>
            <div class="card-body">
                <?php if (empty($requests)): ?>
                    <div class="empty-state"><p>Belum ada request kendaraan.</p></div>
                <?php else: ?>
                <div class="table-wrap"><table>
                    <thead><tr>
                        <th>No. Request</th><th>Pemohon</th><th>Rute</th><th>Jadwal</th><th>Opsi</th><th>Status</th><th>Kendaraan</th><th>Aksi</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($requests as $r): ?>
                        <?php
                            $withDriver = ($r['driver_option'] ?? 'with_driver') === 'with_driver';
                            if (($r['duration_type'] ?? 'full_day') === 'half_day') {
                                $sched = e($r['departure_date']) . '<small>' . substr((string)$r['start_time'],0,5) . '–' . substr((string)$r['end_time'],0,5) . ' · Half Day</small>';
                            } else {
                                $sched = e($r['departure_date']) . '<small>s/d ' . e($r['return_date']) . ' · Full Day</small>';
                            }
                        ?>
                        <tr>
                            <td><strong><?= e($r['request_number']) ?></strong></td>
                            <td><?= e($r['requested_by_name']) ?></td>
                            <td><?= e($r['origin'] ?? '-') ?> → <?= e($r['destination']) ?></td>
                            <td><?= $sched ?></td>
                            <td><span class="badge <?= $withDriver ? 'badge-info' : 'badge-inactive' ?>"><?= $withDriver ? 'Dgn Driver' : 'Tanpa Driver' ?></span></td>
                            <td><span class="badge badge-<?= e($r['status']) ?>"><?= e($r['status']) ?></span></td>
                            <td><?= e($r['assigned_vehicle_plate'] ?? '-') ?><?php if ($withDriver && !empty($r['assigned_driver_name'])): ?><small><?= e($r['assigned_driver_name']) ?></small><?php elseif (!$withDriver && !empty($r['assigned_vehicle_plate'])): ?><small>tanpa driver</small><?php endif; ?></td>
                            <td>
                                <?php if ($r['status'] === 'pending'): ?>
                                <form method="post" action="/customer/requests/<?= $r['id'] ?>/approve" style="display:inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-success btn-sm">Setuju</button>
                                </form>
                                <form method="post" action="/customer/requests/<?= $r['id'] ?>/reject" style="display:inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Tolak request ini?')">Tolak</button>
                                </form>
                                <?php endif; ?>
                                <?php if (in_array($r['status'], ['approved_l1','approved'])): ?>
                                <a href="/customer/requests/<?= $r['id'] ?>/assign" class="btn btn-primary btn-sm">Assign</a>
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
        renderCustomerLayout('Vehicle Request', $content, ['active' => 'requests']);
    }

    public function create(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');
        $tenant = SessionMiddleware::getTenantContext();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthMiddleware::validateCsrf();

            $driverOption = ($_POST['driver_option'] ?? '') === 'without_driver' ? 'without_driver' : 'with_driver';
            $durationType = ($_POST['duration_type'] ?? '') === 'half_day' ? 'half_day' : 'full_day';
            $departure = trim($_POST['departure_date'] ?? '');

            if ($durationType === 'half_day') {
                $returnDate = $departure;                       // half day = satu tanggal
                $startTime  = trim($_POST['start_time'] ?? '') ?: null;
                $endTime    = trim($_POST['end_time'] ?? '') ?: null;
            } else {
                $returnDate = trim($_POST['return_date'] ?? '');
                $startTime  = $endTime = null;
            }

            // -- Validasi --
            $errors = [];
            if (empty($_POST['origin']))       $errors[] = 'Asal wajib diisi';
            if (empty($_POST['destination']))  $errors[] = 'Tujuan wajib diisi';
            if (empty($_POST['purpose']))      $errors[] = 'Keperluan wajib diisi';
            if (empty($departure))             $errors[] = 'Tanggal berangkat wajib diisi';
            if ($durationType === 'full_day') {
                if (empty($returnDate))                 $errors[] = 'Tanggal kembali wajib diisi';
                elseif ($returnDate < $departure)       $errors[] = 'Tanggal kembali tidak boleh sebelum tanggal berangkat';
            } else {
                if (empty($startTime) || empty($endTime)) $errors[] = 'Jam mulai & jam selesai wajib diisi untuk Half Day';
                elseif ($endTime <= $startTime)           $errors[] = 'Jam selesai harus setelah jam mulai';
            }

            if ($errors) {
                $_SESSION['_flash']['error'] = implode('. ', $errors);
                redirect('/customer/requests/create');
            }

            $repo = new VehicleRequestRepository();
            $repo->create([
                'customer_id'     => $tenant->getCustomerId() ?? (int)($_POST['customer_id'] ?? 0),
                'request_number'  => generateNumber('REQ'),
                'requested_by'    => $_SESSION['user_id'],
                'origin'          => $_POST['origin'],
                'destination'     => $_POST['destination'],
                'purpose'         => $_POST['purpose'],
                'driver_option'   => $driverOption,
                'duration_type'   => $durationType,
                'departure_date'  => $departure,
                'return_date'     => $returnDate,
                'start_time'      => $startTime,
                'end_time'        => $endTime,
                'passenger_count' => $_POST['passenger_count'] ?? 1,
                'vehicle_preference' => $_POST['vehicle_preference'] ?? null,
                'department'      => $_POST['department'] ?? null,
            ]);
            $_SESSION['_flash']['success'] = 'Request kendaraan berhasil diajukan';
            redirect('/customer/requests');
        }

        $vehicleTypes = ['MPV', 'Minibus', 'Pickup', 'Truck', 'Box', 'SUV', 'Bus', 'Lainnya'];

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Ajukan Vehicle Request</h3></div>
            <div class="card-body">
                <form method="post" style="max-width:640px">
                    <?= csrf_field() ?>
                    <?php if ($tenant->isSuperAdmin()): ?>
                    <?php $db = Database::getConnection(); $customers = $db->query("SELECT id, name FROM mova_customers")->fetchAll(); ?>
                    <div class="form-group">
                        <label>Customer *</label>
                        <select name="customer_id" class="form-control" required>
                            <option value="">-- Pilih Customer --</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Opsi driver -->
                    <div class="form-group">
                        <label>Opsi Driver *</label>
                        <div class="choice-group">
                            <label><input type="radio" name="driver_option" value="with_driver" checked><span class="choice">Dengan Driver</span></label>
                            <label><input type="radio" name="driver_option" value="without_driver"><span class="choice">Tanpa Driver</span></label>
                        </div>
                    </div>

                    <!-- Asal & Tujuan -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Asal *</label>
                            <input type="text" name="origin" class="form-control" placeholder="Lokasi keberangkatan" required>
                        </div>
                        <div class="form-group">
                            <label>Tujuan *</label>
                            <input type="text" name="destination" class="form-control" placeholder="Lokasi tujuan" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Keperluan *</label>
                        <textarea name="purpose" class="form-control" rows="3" placeholder="Deskripsi keperluan" required></textarea>
                    </div>

                    <!-- Durasi -->
                    <div class="form-group">
                        <label>Durasi *</label>
                        <div class="choice-group">
                            <label><input type="radio" name="duration_type" value="full_day" checked><span class="choice">Full Day</span></label>
                            <label><input type="radio" name="duration_type" value="half_day"><span class="choice">Half Day</span></label>
                        </div>
                    </div>

                    <!-- Tanggal berangkat selalu tampil -->
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tanggal Berangkat *</label>
                            <input type="date" name="departure_date" class="form-control" required>
                        </div>
                        <!-- Full day: tanggal kembali -->
                        <div class="form-group js-fullday">
                            <label>Tanggal Kembali *</label>
                            <input type="date" name="return_date" class="form-control" required>
                        </div>
                    </div>
                    <!-- Half day: jam mulai - selesai -->
                    <div class="form-row js-halfday" style="display:none">
                        <div class="form-group">
                            <label>Jam Mulai *</label>
                            <input type="time" name="start_time" class="form-control">
                        </div>
                        <div class="form-group">
                            <label>Jam Selesai *</label>
                            <input type="time" name="end_time" class="form-control">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Jumlah Penumpang</label>
                            <input type="number" name="passenger_count" class="form-control" value="1" min="1">
                        </div>
                        <div class="form-group">
                            <label>Type Kendaraan</label>
                            <select name="vehicle_preference" class="form-control">
                                <option value="">-- Pilih tipe --</option>
                                <?php foreach ($vehicleTypes as $vt): ?>
                                    <option value="<?= e($vt) ?>"><?= e($vt) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Departemen</label>
                        <input type="text" name="department" class="form-control" placeholder="Operasional, Logistik, dll">
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Ajukan</button>
                        <a href="/customer/requests" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <script>
        (function () {
            const radios = document.querySelectorAll('input[name="duration_type"]');
            const full = document.querySelector('.js-fullday');
            const half = document.querySelector('.js-halfday');
            const ret  = document.querySelector('[name="return_date"]');
            const st   = document.querySelector('[name="start_time"]');
            const et   = document.querySelector('[name="end_time"]');
            function apply() {
                const isHalf = document.querySelector('input[name="duration_type"]:checked').value === 'half_day';
                full.style.display = isHalf ? 'none' : '';
                half.style.display = isHalf ? '' : 'none';
                if (ret) ret.required = !isHalf;
                if (st)  st.required  = isHalf;
                if (et)  et.required  = isHalf;
            }
            radios.forEach(r => r.addEventListener('change', apply));
            apply();
        })();
        </script>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../CustomerPanel/Views/layout.php';
        renderCustomerLayout('Ajukan Request', $content, ['active' => 'requests']);
    }

    public function approve(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');
        AuthMiddleware::validateCsrf();

        $repo = new VehicleRequestRepository();
        $request = $repo->find($id);
        $repo->approveL1($id, (int)$_SESSION['user_id']);

        if ($request) {
            createNotification([
                'user_id' => $request['requested_by'],
                'customer_id' => $request['customer_id'],
                'type' => 'vehicle_request',
                'title' => 'Request Disetujui',
                'message' => 'Request ' . $request['request_number'] . ' ke ' . $request['destination'] . ' telah disetujui Level 1.',
                'reference_type' => 'vehicle_requests',
                'reference_id' => $id,
            ]);
        }
        $_SESSION['_flash']['success'] = 'Request disetujui (Level 1)';
        redirect('/customer/requests');
    }

    public function reject(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');
        AuthMiddleware::validateCsrf();

        $repo = new VehicleRequestRepository();
        $request = $repo->find($id);
        $reason = $_POST['reason'] ?? 'Ditolak';
        $repo->reject($id, (int)$_SESSION['user_id'], $reason);

        if ($request) {
            createNotification([
                'user_id' => $request['requested_by'],
                'customer_id' => $request['customer_id'],
                'type' => 'vehicle_request',
                'title' => 'Request Ditolak',
                'message' => 'Request ' . $request['request_number'] . ' ditolak. Alasan: ' . $reason,
                'reference_type' => 'vehicle_requests',
                'reference_id' => $id,
            ]);
        }
        $_SESSION['_flash']['success'] = 'Request ditolak';
        redirect('/customer/requests');
    }

    public function assign(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireRole(['koordinator', 'super_admin']);

        $repo = new VehicleRequestRepository();
        $request = $repo->find($id);
        if (!$request) { $_SESSION['_flash']['error'] = 'Request tidak ditemukan'; redirect('/customer/requests'); }

        $tenant = SessionMiddleware::getTenantContext();
        $customerId = $tenant->getCustomerId() ?: $request['customer_id'];
        $db = Database::getConnection();

        $vehicles = $db->prepare("SELECT * FROM mova_vehicles WHERE customer_id = ? AND is_active = 1");
        $vehicles->execute([$customerId]); $vehicles = $vehicles->fetchAll();

        $drivers = $db->prepare(
            "SELECT u.* FROM mova_users u JOIN mova_roles r ON r.id = u.role_id
             WHERE u.customer_id = ? AND r.name = 'driver' AND u.is_active = 1"
        );
        $drivers->execute([$customerId]); $drivers = $drivers->fetchAll();

        $withDriver = ($request['driver_option'] ?? 'with_driver') === 'with_driver';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthMiddleware::validateCsrf();
            $vehicleId = (int)($_POST['vehicle_id'] ?? 0);
            // Driver hanya untuk request "with_driver"; "without_driver" → NULL.
            $driverId = $withDriver ? ((int)($_POST['driver_id'] ?? 0) ?: null) : null;

            if (!$vehicleId) {
                $_SESSION['_flash']['error'] = 'Kendaraan wajib dipilih';
                redirect('/customer/requests/' . $id . '/assign');
            }
            if ($withDriver && !$driverId) {
                $_SESSION['_flash']['error'] = 'Driver wajib dipilih untuk request dengan driver';
                redirect('/customer/requests/' . $id . '/assign');
            }

            $repo->assign($id, $vehicleId, $driverId);

            $vStmt = $db->prepare("SELECT plate_number FROM mova_vehicles WHERE id = ?");
            $vStmt->execute([$vehicleId]); $plate = $vStmt->fetchColumn();

            $driverNote = '(tanpa driver)';
            if ($driverId) {
                $dStmt = $db->prepare("SELECT name FROM mova_users WHERE id = ?");
                $dStmt->execute([$driverId]);
                $driverNote = 'dengan driver ' . $dStmt->fetchColumn();
            }

            createNotification([
                'user_id' => $request['requested_by'],
                'customer_id' => $request['customer_id'],
                'type' => 'vehicle_request',
                'title' => 'Kendaraan Ditugaskan',
                'message' => 'Request ' . $request['request_number'] . ': kendaraan ' . $plate . ' ' . $driverNote . ' telah ditugaskan.',
                'reference_type' => 'vehicle_requests',
                'reference_id' => $id,
            ]);

            $_SESSION['_flash']['success'] = $withDriver ? 'Kendaraan & driver ditugaskan' : 'Kendaraan ditugaskan (tanpa driver)';
            redirect('/customer/requests');
        }

        // Jadwal untuk info header
        if (($request['duration_type'] ?? 'full_day') === 'half_day') {
            $sched = $request['departure_date'] . ' · ' . substr((string)$request['start_time'], 0, 5) . '–' . substr((string)$request['end_time'], 0, 5) . ' (Half Day)';
        } else {
            $sched = $request['departure_date'] . ' s/d ' . $request['return_date'] . ' (Full Day)';
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Assign — <?= e($request['request_number']) ?></h3></div>
            <div class="card-body">
                <div style="margin-bottom:16px;font-size:0.85rem;color:var(--text-2);line-height:1.7">
                    <div><strong style="color:var(--text)">Rute:</strong> <?= e($request['origin'] ?? '-') ?> → <?= e($request['destination']) ?></div>
                    <div><strong style="color:var(--text)">Jadwal:</strong> <?= e($sched) ?></div>
                    <div><strong style="color:var(--text)">Opsi:</strong>
                        <span class="badge <?= $withDriver ? 'badge-info' : 'badge-inactive' ?>"><?= $withDriver ? 'Dengan Driver' : 'Tanpa Driver' ?></span>
                    </div>
                </div>
                <form method="post" style="max-width:420px">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label>Kendaraan *</label>
                        <select name="vehicle_id" class="form-control" required>
                            <option value="">-- Pilih --</option>
                            <?php foreach ($vehicles as $v): ?>
                            <option value="<?= $v['id'] ?>"><?= e($v['plate_number']) ?> — <?= e($v['brand']) ?> <?= e($v['model'] ?? '') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($withDriver): ?>
                    <div class="form-group">
                        <label>Driver *</label>
                        <select name="driver_id" class="form-control" required>
                            <option value="">-- Pilih --</option>
                            <?php foreach ($drivers as $d): ?>
                            <option value="<?= $d['id'] ?>"><?= e($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                    <p style="font-size:0.82rem;color:var(--text-2);margin-bottom:16px">Request ini tanpa driver — cukup pilih kendaraan.</p>
                    <?php endif; ?>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Assign</button>
                        <a href="/customer/requests" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../CustomerPanel/Views/layout.php';
        renderCustomerLayout('Assign Request', $content, ['active' => 'requests']);
    }
}
