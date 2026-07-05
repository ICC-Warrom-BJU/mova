<?php

class VehicleController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('company');

        $repo = new VehicleRepository();
        $vehicles = $repo->findWithCustomer();

        ob_start();
        ?>
        <div class="card">
            <div class="card-header">
                <h3>Daftar Vehicle</h3>
                <a href="/vehicles/create" class="btn btn-primary btn-sm">+ Tambah Vehicle</a>
            </div>
            <div class="card-body">
                <?php if (empty($vehicles)): ?>
                    <div class="empty-state"><p>Belum ada kendaraan.</p></div>
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
                            <th>Status</th>
                            <th>STNK</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($vehicles as $v): ?>
                        <tr>
                            <td><strong><?= e($v['plate_number']) ?></strong></td>
                            <td><?= e($v['brand']) ?> <?= e($v['model'] ?? '') ?></td>
                            <td><?= e($v['year'] ?? '-') ?></td>
                            <td><?= e($v['customer_name'] ?? '-') ?></td>
                            <td><?= number_format((int)$v['current_km']) ?></td>
                            <td><span class="badge badge-<?= $v['status'] === 'active' ? 'active' : 'inactive' ?>"><?= e($v['status']) ?></span></td>
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
                                <option value="pickup">Pickup</option>
                                <option value="box">Box</option>
                                <option value="wingbox">Wingbox</option>
                                <option value="fuso">Fuso</option>
                                <option value="tronton">Tronton</option>
                                <option value="trailer">Trailer</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="active">Active</option>
                                <option value="maintenance">Maintenance</option>
                                <option value="inactive">Inactive</option>
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
                    <div class="form-check">
                        <input type="checkbox" name="is_active" value="1" checked id="is_active">
                        <label for="is_active">Aktif</label>
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
                                <?php foreach (['pickup','box','wingbox','fuso','tronton','trailer'] as $t): ?>
                                <option value="<?= $t ?>" <?= ($vehicle['vehicle_type'] ?? '') === $t ? 'selected' : '' ?>><?= ucfirst($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Status</label>
                            <select name="status" class="form-control">
                                <option value="active" <?= $vehicle['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="maintenance" <?= $vehicle['status'] === 'maintenance' ? 'selected' : '' ?>>Maintenance</option>
                                <option value="inactive" <?= $vehicle['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
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
                    <div class="form-check">
                        <input type="checkbox" name="is_active" value="1" <?= $vehicle['is_active'] ? 'checked' : '' ?> id="is_active">
                        <label for="is_active">Aktif</label>
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

        $repo = new VehicleRepository();
        $repo->delete($id);

        $_SESSION['_flash']['success'] = 'Kendaraan berhasil dihapus';
        redirect('/vehicles');
    }
}
