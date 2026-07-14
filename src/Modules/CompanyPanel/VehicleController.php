<?php

class VehicleController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('company');

        $search = $_GET['q'] ?? null;
        $repo = new VehicleRepository();
        $vehicles = $repo->findWithCustomer($search);

        $showInactive = !empty($_GET['show_inactive']);
        if (!$showInactive) {
            $vehicles = array_values(array_filter($vehicles, fn($v) => (int)($v['is_active'] ?? 1) === 1));
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header">
                <h3>Daftar Vehicle</h3>
                <div style="display:flex;gap:8px">
                    <form method="get" action="/vehicles" class="form-inline" style="margin:0">
                        <input type="text" name="q" class="form-control" placeholder="Cari plat, merk, model, customer..." value="<?= e($search ?? '') ?>" style="width:260px">
                        <button type="submit" class="btn btn-outline btn-sm" style="margin-left:4px">🔍</button>
                        <?php if ($search): ?>
                        <a href="/vehicles" class="btn btn-outline btn-sm" style="margin-left:4px">✕</a>
                        <?php endif; ?>
                    </form>
                    <a href="/vehicles/import" class="btn btn-outline btn-sm">📥 Import Data</a>
                    <?= inactiveToggle($showInactive) ?>
                    <a href="/vehicles/create" class="btn btn-primary btn-sm">+ Tambah Vehicle</a>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($vehicles)): ?>
                    <div class="empty-state"><p><?= $search ? 'Tidak ada kendaraan yang cocok dengan "' . e($search) . '".' : 'Belum ada kendaraan.' ?></p></div>
                <?php else: ?>
                <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Plat</th>
                            <th>Merk / Model</th>
                            <th>Tahun</th>
                            <th>Customer</th>
                            <th>KM</th>
                            <th>Status Operasional</th>
                            <th>Status</th>
                            <th>STNK</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vehicles as $v): ?>
                        <tr class="<?= !$v['is_active'] ? 'row-inactive' : '' ?>">
                            <td><strong><?= e($v['plate_number']) ?></strong></td>
                            <td><?= e($v['brand']) ?> <?= e($v['model'] ?? '') ?></td>
                            <td><?= e($v['year'] ?? '-') ?></td>
                            <td><?= e($v['customer_name'] ?? '-') ?></td>
                            <td><?= number_format((int)$v['current_km']) ?></td>
                            <td><span class="badge badge-<?= statusTone($v['status']) ?>"><?= e(configLabel('vehicle_status', $v['status'])) ?></span></td>
                            <td><span class="badge badge-<?= $v['is_active'] ? 'active' : 'inactive' ?>"><?= $v['is_active'] ? 'Active' : 'Nonaktif' ?></span></td>
                            <td><?= $v['stnk_expiry'] ? date('d/m/Y', strtotime($v['stnk_expiry'])) : '-' ?></td>
                            <td>
                                <a href="/vehicles/<?= $v['id'] ?>/edit" class="btn btn-warning btn-sm">Edit</a>
                                <form method="post" action="/vehicles/<?= $v['id'] ?>/delete" class="form-inline" onsubmit="return confirm('Hapus kendaraan ini?')">
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
        renderLayout('Vehicle', $content, ['active' => 'vehicles']);
    }

    public function create(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('company');
        $tenant = SessionMiddleware::getTenantContext();

        $customerRepo = new CustomerRepository();
        if ($tenant->isSuperAdmin()) {
            $customers = $customerRepo->findActive();
        } else {
            $customerIds = $tenant->getAccessibleCustomerIds();
            $customers = !empty($customerIds) ? $customerRepo->findByIds($customerIds) : [];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthMiddleware::validateCsrf($_POST['_csrf'] ?? '');
            $repo = new VehicleRepository();

            if (empty($_POST['plate_number']) || empty($_POST['brand']) || empty($_POST['customer_id'])) {
                $_SESSION['_flash']['error'] = 'Plat nomor, merk, dan customer wajib diisi';
                redirect('/vehicles/create');
            }

            $data = $_POST;

            // Handle STNK photo upload
            if (!empty($_FILES['stnk_photo']['name'])) {
                try {
                    $uploader = new FileUploader();
                    $data['stnk_photo'] = $uploader->upload($_FILES['stnk_photo'], 'stnk');
                } catch (RuntimeException $e) {
                    $_SESSION['_flash']['error'] = 'Upload STNK: ' . $e->getMessage();
                    redirect('/vehicles/create');
                }
            }

            // Handle KIR photo upload
            if (!empty($_FILES['kir_photo']['name'])) {
                try {
                    $uploader = $uploader ?? new FileUploader();
                    $data['kir_photo'] = $uploader->upload($_FILES['kir_photo'], 'kir');
                } catch (RuntimeException $e) {
                    $_SESSION['_flash']['error'] = 'Upload KIR: ' . $e->getMessage();
                    redirect('/vehicles/create');
                }
            }

            $repo->create($data);
            $_SESSION['_flash']['success'] = 'Kendaraan berhasil ditambahkan';
            redirect('/vehicles');
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Tambah Vehicle</h3></div>
            <div class="card-body">
                <form method="post" class="form-wide" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Customer *</label>
                            <select name="customer_id" class="form-control" required>
                                <option value="">-- Pilih Customer --</option>
                                <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Plat Nomor *</label>
                            <input type="text" name="plate_number" class="form-control" placeholder="B 1234 XYZ" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Merk *</label>
                            <input type="text" name="brand" class="form-control" placeholder="Mitsubishi" required>
                        </div>
                        <div class="form-group">
                            <label>Model</label>
                            <input type="text" name="model" class="form-control" placeholder="L300">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tahun</label>
                            <input type="number" name="year" class="form-control" placeholder="2024">
                        </div>
                        <div class="form-group">
                            <label>Warna</label>
                            <input type="text" name="color" class="form-control" placeholder="Putih">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tipe Kendaraan</label>
                            <select name="vehicle_type" class="form-control">
                                <option value="">-- Pilih --</option>
                                <?php foreach (configOptions('vehicle_type') as $vt): ?>
                                <option value="<?= e($vt['value']) ?>"><?= e($vt['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status Operasional</label>
                            <select name="status" class="form-control">
                                <?php foreach (configOptions('vehicle_status') as $opt): ?>
                                <option value="<?= e($opt['value']) ?>"><?= e($opt['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>KM Saat Ini</label>
                            <input type="number" name="current_km" class="form-control" value="0" min="0">
                        </div>
                        <div class="form-group">
                            <label>STNK Expiry</label>
                            <input type="date" name="stnk_expiry" class="form-control">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Upload Foto STNK <span class="form-hint">(JPG/PNG/PDF, maks 5MB)</span></label>
                            <input type="file" name="stnk_photo" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf">
                        </div>
                        <div class="form-group">
                            <label>KIR Expiry</label>
                            <input type="date" name="kir_expiry" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Upload Foto KIR <span class="form-hint">(JPG/PNG/PDF, maks 5MB)</span></label>
                        <input type="file" name="kir_photo" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf">
                    </div>
                    <div class="form-group">
                        <label>Status (is_Active)</label>
                        <select name="is_active" class="form-control">
                            <option value="1">Active</option>
                            <option value="0">Nonaktif</option>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Simpan</button>
                        <a href="/vehicles" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/Views/layout.php';
        renderLayout('Tambah Vehicle', $content, ['active' => 'vehicles']);
    }

    public function edit(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('company');
        $tenant = SessionMiddleware::getTenantContext();

        $repo = new VehicleRepository();
        $vehicle = $repo->find($id);

        if (!$vehicle) {
            $_SESSION['_flash']['error'] = 'Kendaraan tidak ditemukan';
            redirect('/vehicles');
        }

        $customerRepo = new CustomerRepository();
        if ($tenant->isSuperAdmin()) {
            $customers = $customerRepo->findActive();
        } else {
            $customerIds = $tenant->getAccessibleCustomerIds();
            $customers = !empty($customerIds) ? $customerRepo->findByIds($customerIds) : [];
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthMiddleware::validateCsrf($_POST['_csrf'] ?? '');

            if (empty($_POST['plate_number']) || empty($_POST['brand']) || empty($_POST['customer_id'])) {
                $_SESSION['_flash']['error'] = 'Plat nomor, merk, dan customer wajib diisi';
                redirect('/vehicles/' . $id . '/edit');
            }

            $data = $_POST;

            // Handle STNK photo upload
            if (!empty($_FILES['stnk_photo']['name'])) {
                try {
                    $uploader = new FileUploader();
                    // Delete old file if exists
                    if (!empty($vehicle['stnk_photo'])) {
                        $uploader->delete($vehicle['stnk_photo']);
                    }
                    $data['stnk_photo'] = $uploader->upload($_FILES['stnk_photo'], 'stnk');
                } catch (RuntimeException $e) {
                    $_SESSION['_flash']['error'] = 'Upload STNK: ' . $e->getMessage();
                    redirect('/vehicles/' . $id . '/edit');
                }
            }

            // Handle KIR photo upload
            if (!empty($_FILES['kir_photo']['name'])) {
                try {
                    $uploader = $uploader ?? new FileUploader();
                    if (!empty($vehicle['kir_photo'])) {
                        $uploader->delete($vehicle['kir_photo']);
                    }
                    $data['kir_photo'] = $uploader->upload($_FILES['kir_photo'], 'kir');
                } catch (RuntimeException $e) {
                    $_SESSION['_flash']['error'] = 'Upload KIR: ' . $e->getMessage();
                    redirect('/vehicles/' . $id . '/edit');
                }
            }

            $repo->update($id, $data);
            $_SESSION['_flash']['success'] = 'Kendaraan berhasil diupdate';
            redirect('/vehicles');
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Edit Vehicle</h3></div>
            <div class="card-body">
                <form method="post" class="form-wide" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Customer</label>
                            <select name="customer_id" class="form-control" required>
                                <?php foreach ($customers as $c): ?>
                                <option value="<?= $c['id'] ?>" <?= $vehicle['customer_id'] == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Plat Nomor</label>
                            <input type="text" name="plate_number" class="form-control" value="<?= e($vehicle['plate_number']) ?>" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Merk</label>
                            <input type="text" name="brand" class="form-control" value="<?= e($vehicle['brand']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Model</label>
                            <input type="text" name="model" class="form-control" value="<?= e($vehicle['model'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tahun</label>
                            <input type="number" name="year" class="form-control" value="<?= e($vehicle['year'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label>Warna</label>
                            <input type="text" name="color" class="form-control" value="<?= e($vehicle['color'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tipe Kendaraan</label>
                            <select name="vehicle_type" class="form-control">
                                <option value="">-- Pilih --</option>
                                <?php $curVt = $vehicle['vehicle_type'] ?? ''; $optsVt = configOptions('vehicle_type'); ?>
                                <?php if ($curVt !== '' && !in_array($curVt, array_column($optsVt, 'value'), true)): ?>
                                <option value="<?= e($curVt) ?>" selected><?= e(ucfirst($curVt)) ?></option>
                                <?php endif; ?>
                                <?php foreach ($optsVt as $vt): ?>
                                <option value="<?= e($vt['value']) ?>" <?= $curVt === $vt['value'] ? 'selected' : '' ?>><?= e($vt['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <?php $curSt = $vehicle['status'] ?? ''; $optsSt = configOptions('vehicle_status'); ?>
                            <label>Status Operasional</label>
                            <select name="status" class="form-control">
                                <?php if ($curSt !== '' && !in_array($curSt, array_column($optsSt, 'value'), true)): ?>
                                <option value="<?= e($curSt) ?>" selected><?= e(configLabel('vehicle_status', $curSt)) ?></option>
                                <?php endif; ?>
                                <?php foreach ($optsSt as $opt): ?>
                                <option value="<?= e($opt['value']) ?>" <?= $curSt === $opt['value'] ? 'selected' : '' ?>><?= e($opt['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>KM Saat Ini</label>
                            <input type="number" name="current_km" class="form-control" value="<?= (int)$vehicle['current_km'] ?>" min="0">
                        </div>
                        <div class="form-group">
                            <label>STNK Expiry</label>
                            <input type="date" name="stnk_expiry" class="form-control" value="<?= e($vehicle['stnk_expiry'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Upload Foto STNK <span class="form-hint">(kosongkan jika tidak berubah)</span></label>
                            <?php if (!empty($vehicle['stnk_photo'])): ?>
                            <div class="mb-1">
                                <a href="/<?= e($vehicle['stnk_photo']) ?>" target="_blank" class="btn btn-outline btn-sm">📄 Lihat STNK saat ini</a>
                            </div>
                            <?php endif; ?>
                            <input type="file" name="stnk_photo" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf">
                        </div>
                        <div class="form-group">
                            <label>KIR Expiry</label>
                            <input type="date" name="kir_expiry" class="form-control" value="<?= e($vehicle['kir_expiry'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Upload Foto KIR <span class="form-hint">(kosongkan jika tidak berubah)</span></label>
                        <?php if (!empty($vehicle['kir_photo'])): ?>
                        <div class="mb-1">
                            <a href="/<?= e($vehicle['kir_photo']) ?>" target="_blank" class="btn btn-outline btn-sm">📄 Lihat KIR saat ini</a>
                        </div>
                        <?php endif; ?>
                        <input type="file" name="kir_photo" class="form-control" accept="image/jpeg,image/png,image/webp,application/pdf">
                    </div>
                    <div class="form-group">
                        <label>Status (is_Active)</label>
                        <select name="is_active" class="form-control">
                            <option value="1" <?= $vehicle['is_active'] ? 'selected' : '' ?>>Active</option>
                            <option value="0" <?= !$vehicle['is_active'] ? 'selected' : '' ?>>Nonaktif</option>
                        </select>
                    </div>
                    <div class="form-actions">
                        <button type="submit" class="btn btn-success">Simpan</button>
                        <a href="/vehicles" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/Views/layout.php';
        renderLayout('Edit Vehicle', $content, ['active' => 'vehicles']);
    }

    public function delete(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('company');
        AuthMiddleware::validateCsrf($_POST['_csrf'] ?? '');

        try {
            (new VehicleRepository())->softDelete($id);
            $_SESSION['_flash']['success'] = 'Kendaraan berhasil dinonaktifkan';
        } catch (\Throwable $e) {
            $_SESSION['_flash']['error'] = 'Gagal menonaktifkan kendaraan';
        }
        redirect('/vehicles');
    }

    public function downloadTemplate(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('company');

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Template Import Vehicle');

        $headers = [
            'customer_code' => 'Kode Customer *',
            'plate_number' => 'Plat Nomor *',
            'brand' => 'Merk *',
            'model' => 'Model',
            'year' => 'Tahun',
            'color' => 'Warna',
            'vehicle_type' => 'Tipe Kendaraan',
            'current_km' => 'KM Awal',
            'status' => 'Status (active/maintenance/inactive)',
            'stnk_expiry' => 'Masa Berlaku STNK (YYYY-MM-DD)',
            'kir_expiry' => 'Masa Berlaku KIR (YYYY-MM-DD)',
        ];

        $col = 1;
        foreach ($headers as $key => $label) {
            $coord = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col) . '1';
            $sheet->setCellValue($coord, $label);
            $col++;
        }

        $lastCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col - 1);
        $sheet->getStyle('A1:' . $lastCol . '1')->getFont()->setBold(true);

        for ($c = 1; $c < $col; $c++) {
            $sheet->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c))->setAutoSize(true);
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="template_import_vehicle.xlsx"');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
    }

    public function import(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('company');

        $db = Database::getConnection();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthMiddleware::validateCsrf($_POST['_csrf'] ?? '');

            if (empty($_FILES['file']['tmp_name'])) {
                $_SESSION['_flash']['error'] = 'Pilih file Excel terlebih dahulu';
                redirect('/vehicles/import');
            }

            try {
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($_FILES['file']['tmp_name']);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray();
            } catch (\Exception $e) {
                $_SESSION['_flash']['error'] = 'Gagal membaca file: ' . $e->getMessage();
                redirect('/vehicles/import');
            }

            if (count($rows) < 2) {
                $_SESSION['_flash']['error'] = 'File tidak memiliki data (minimal 1 baris data)';
                redirect('/vehicles/import');
            }

            $headerRow = array_shift($rows);
            $headerRow = array_map('trim', $headerRow);

            $headerMap = [
                'kode customer *' => 'customer_code',
                'plat nomor *' => 'plate_number',
                'merk *' => 'brand',
                'model' => 'model',
                'tahun' => 'year',
                'warna' => 'color',
                'tipe kendaraan' => 'vehicle_type',
                'km awal' => 'current_km',
                'status (active/maintenance/inactive)' => 'status',
                'masa berlaku stnk (yyyy-mm-dd)' => 'stnk_expiry',
                'masa berlaku kir (yyyy-mm-dd)' => 'kir_expiry',
            ];

            $mapped = [];
            foreach ($headerRow as $h) {
                $key = strtolower($h);
                $mapped[] = $headerMap[$key] ?? $key;
            }

            $required = ['customer_code', 'plate_number', 'brand'];
            $missing = array_diff($required, $mapped);
            if (!empty($missing)) {
                $_SESSION['_flash']['error'] = 'Kolom wajib tidak ditemukan: ' . implode(', ', $missing);
                redirect('/vehicles/import');
            }

            $repo = new VehicleRepository();
            $inserted = 0;
            $skipped = 0;
            $errors = [];
            $rowNum = 1;

            $customerCache = [];
            $stmtCustomer = $db->prepare("SELECT id FROM mova_customers WHERE code = ? AND is_active = 1");

            foreach ($rows as $row) {
                $rowNum++;
                $data = array_combine($mapped, $row);

                $plateNumber = trim($data['plate_number'] ?? '');
                $brand = trim($data['brand'] ?? '');
                $customerCode = trim($data['customer_code'] ?? '');

                if (empty($plateNumber)) {
                    $errors[] = "Baris $rowNum: Plat nomor kosong";
                    continue;
                }
                if (empty($brand)) {
                    $errors[] = "Baris $rowNum: Merk kosong";
                    continue;
                }
                if (empty($customerCode)) {
                    $errors[] = "Baris $rowNum: Kode customer kosong";
                    continue;
                }

                if (!isset($customerCache[$customerCode])) {
                    $stmtCustomer->execute([$customerCode]);
                    $cid = $stmtCustomer->fetchColumn();
                    if (!$cid) {
                        $errors[] = "Baris $rowNum: Customer \"$customerCode\" tidak ditemukan";
                        continue;
                    }
                    $customerCache[$customerCode] = (int)$cid;
                }
                $customerId = $customerCache[$customerCode];

                $check = $db->prepare("SELECT id FROM mova_vehicles WHERE plate_number = ?");
                $check->execute([$plateNumber]);
                if ($check->fetchColumn()) {
                    $skipped++;
                    continue;
                }

                $repo->create([
                    'customer_id' => $customerId,
                    'plate_number' => $plateNumber,
                    'brand' => $brand,
                    'model' => trim($data['model'] ?? ''),
                    'year' => !empty(trim($data['year'] ?? '')) ? (int)$data['year'] : null,
                    'color' => trim($data['color'] ?? ''),
                    'vehicle_type' => trim($data['vehicle_type'] ?? ''),
                    'current_km' => !empty(trim($data['current_km'] ?? '')) ? (int)$data['current_km'] : 0,
                    'status' => in_array(trim($data['status'] ?? ''), array_column(configOptions('vehicle_status'), 'value'), true) ? trim($data['status']) : (configOptions('vehicle_status')[0]['value'] ?? 'ready'),
                    'stnk_expiry' => !empty(trim($data['stnk_expiry'] ?? '')) ? trim($data['stnk_expiry']) : null,
                    'kir_expiry' => !empty(trim($data['kir_expiry'] ?? '')) ? trim($data['kir_expiry']) : null,
                    'is_active' => 1,
                ]);
                $inserted++;
            }

            ob_start();
            ?>
            <div class="card">
                <div class="card-header"><h3>Hasil Import</h3></div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:20px">
                        <div style="text-align:center;padding:20px;background:#e8f5e9;border-radius:8px">
                            <div style="font-size:28px;font-weight:700;color:#2e7d32"><?= $inserted ?></div>
                            <div style="font-size:13px;color:#555">Berhasil</div>
                        </div>
                        <div style="text-align:center;padding:20px;background:#fff3e0;border-radius:8px">
                            <div style="font-size:28px;font-weight:700;color:#e65100"><?= $skipped ?></div>
                            <div style="font-size:13px;color:#555">Skipped (duplikat plat)</div>
                        </div>
                        <div style="text-align:center;padding:20px;background:#ffebee;border-radius:8px">
                            <div style="font-size:28px;font-weight:700;color:#c62828"><?= count($errors) ?></div>
                            <div style="font-size:13px;color:#555">Gagal</div>
                        </div>
                    </div>
                    <?php if ($errors): ?>
                    <div style="margin-bottom:16px">
                        <strong>Detail Error:</strong>
                        <ul style="font-size:13px;color:#c62828;margin-top:8px">
                            <?php foreach ($errors as $e): ?>
                            <li><?= e($e) ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                    <a href="/vehicles" class="btn btn-primary">Kembali ke Daftar Vehicle</a>
                    <a href="/vehicles/import" class="btn btn-outline">Import Lagi</a>
                </div>
            </div>
            <?php
            $content = ob_get_clean();
            require __DIR__ . '/Views/layout.php';
            renderLayout('Hasil Import', $content, ['active' => 'vehicles']);
            return;
        }

        ob_start();
        ?>
        <div class="card" style="max-width:700px">
            <div class="card-header"><h3>Import Data Vehicle</h3></div>
            <div class="card-body">
                <div style="margin-bottom:20px;padding:16px;background:#fff8e1;border-radius:8px;font-size:13px;line-height:1.6">
                    <strong>Petunjuk:</strong>
                    <ol style="margin:8px 0 0 20px">
                        <li>Download template Excel terlebih dahulu.</li>
                        <li>Isi data sesuai kolom. Kolom dengan tanda * wajib diisi.</li>
                        <li>Kode Customer harus sesuai dengan <code>code</code> di data Master Customer.</li>
                        <li>Plat nomor duplikat akan otomatis dilewati (skip).</li>
                        <li>Upload file <strong>.xlsx</strong> maksimal 5MB.</li>
                    </ol>
                </div>
                <a href="/vehicles/import/template" class="btn btn-primary btn-sm" style="margin-bottom:20px">📥 Download Template Excel</a>
                <form method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label>Pilih File Excel *</label>
                        <input type="file" name="file" class="form-control" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
                    </div>
                    <div style="margin-top:20px">
                        <button type="submit" class="btn btn-success">Import</button>
                        <a href="/vehicles" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/Views/layout.php';
        renderLayout('Import Vehicle', $content, ['active' => 'vehicles']);
    }
}
