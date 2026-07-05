<?php

class ChecklistController
{
    public function show(int $tripId): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');

        $db = Database::getConnection();
        $trip = $db->prepare(
            "SELECT t.*, v.plate_number, v.brand, u.name AS driver_name
             FROM mova_trips t
             JOIN mova_vehicles v ON v.id = t.vehicle_id
             JOIN mova_users u ON u.id = t.driver_id
             WHERE t.id = ?"
        );
        $trip->execute([$tripId]);
        $trip = $trip->fetch();
        if (!$trip) { $_SESSION['_flash']['error'] = 'Trip tidak ditemukan'; redirect('/customer/trips'); }

        $repo = new DriverSelfServiceRepository();
        $checklists = $repo->findChecklistsByTrip($tripId);
        $photos = $repo->findPhotosByTrip($tripId);

        $preTrip = array_values(array_filter($checklists, fn($c) => $c['check_type'] === 'pre_trip'));
        $postTrip = array_values(array_filter($checklists, fn($c) => $c['check_type'] === 'post_trip'));

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Checklist & Foto — <?= e($trip['trip_number']) ?></h3></div>
            <div class="card-body">
                <p style="margin-bottom:16px">
                    Kendaraan: <strong><?= e($trip['plate_number']) ?></strong> |
                    Driver: <strong><?= e($trip['driver_name']) ?></strong> |
                    Rute: <?= e($trip['origin']) ?> → <?= e($trip['destination']) ?>
                </p>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                    <div class="card">
                        <div class="card-header">
                            <h3>Pre-Trip Checklist</h3>
                            <?php if (empty($preTrip)): ?>
                            <a href="/customer/checklists/<?= $tripId ?>/create/pre_trip" class="btn btn-primary btn-sm">Isi</a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if ($preTrip): ?>
                                <?php $items = json_decode($preTrip[0]['items'], true); $overall = $preTrip[0]['overall_condition']; ?>
                                <p>Kondisi: <span class="badge badge-<?= $overall === 'good' ? 'active' : 'danger' ?>"><?= $overall ?></span></p>
                                <p style="font-size:12px;color:var(--text-2)">Oleh: <?= e($preTrip[0]['submitted_by_name']) ?> — <?= $preTrip[0]['submitted_at'] ?></p>
                                <table style="margin-top:8px;font-size:12px">
                                    <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td style="padding:4px 8px"><?= e($item['name'] ?? 'Item') ?></td>
                                        <td style="padding:4px 8px"><?= ($item['status'] ?? '') === 'ok' ? '✅' : '❌' ?></td>
                                        <td style="padding:4px 8px;color:var(--text-2)"><?= e($item['note'] ?? '') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </table>
                            <?php else: ?>
                                <div class="empty-state" style="padding:20px"><p>Belum diisi</p></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h3>Post-Trip Checklist</h3>
                            <?php if ($trip['status'] === 'completed' && empty($postTrip)): ?>
                            <a href="/customer/checklists/<?= $tripId ?>/create/post_trip" class="btn btn-primary btn-sm">Isi</a>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if ($postTrip): ?>
                                <?php $items = json_decode($postTrip[0]['items'], true); $overall = $postTrip[0]['overall_condition']; ?>
                                <p>Kondisi: <span class="badge badge-<?= $overall === 'good' ? 'active' : 'danger' ?>"><?= $overall ?></span></p>
                                <p style="font-size:12px;color:var(--text-2)">Oleh: <?= e($postTrip[0]['submitted_by_name']) ?> — <?= $postTrip[0]['submitted_at'] ?></p>
                                <table style="margin-top:8px;font-size:12px">
                                    <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td style="padding:4px 8px"><?= e($item['name'] ?? 'Item') ?></td>
                                        <td style="padding:4px 8px"><?= ($item['status'] ?? '') === 'ok' ? '✅' : '❌' ?></td>
                                        <td style="padding:4px 8px;color:var(--text-2)"><?= e($item['note'] ?? '') ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </table>
                            <?php else: ?>
                                <div class="empty-state" style="padding:20px"><p>Belum diisi</p></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card" style="margin-top:16px">
                    <div class="card-header">
                        <h3>Foto Kendaraan (<?= count($photos) ?>)</h3>
                        <a href="/customer/checklists/<?= $tripId ?>/photos" class="btn btn-primary btn-sm">+ Tambah Foto</a>
                    </div>
                    <div class="card-body">
                        <?php if ($photos): ?>
                        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px">
                            <?php foreach ($photos as $p): ?>
                            <div style="border:1px solid var(--border);border-radius:6px;padding:8px;text-align:center">
                                <div style="width:100%;height:80px;background:var(--surface-2);border-radius:4px;margin-bottom:4px;display:flex;align-items:center;justify-content:center;color:var(--text-muted)"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></div>
                                <small style="font-size:10px;color:var(--text-2)"><?= e($p['position']) ?><br><?= e($p['photo_type']) ?></small>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state" style="padding:20px"><p>Belum ada foto</p></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div style="margin-top:16px">
                    <a href="/customer/trips" class="btn btn-outline">← Kembali</a>
                </div>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../CustomerPanel/Views/layout.php';
        renderCustomerLayout('Checklist Trip', $content, ['active' => 'trips']);
    }

    public function createChecklist(int $tripId, string $type): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');

        if (!in_array($type, ['pre_trip', 'post_trip'], true)) {
            $_SESSION['_flash']['error'] = 'Tipe checklist tidak valid';
            redirect('/customer/trips');
        }

        $db = Database::getConnection();
        $trip = $db->prepare("SELECT * FROM mova_trips WHERE id = ?");
        $trip->execute([$tripId]);
        $trip = $trip->fetch();
        if (!$trip) { $_SESSION['_flash']['error'] = 'Trip tidak ditemukan'; redirect('/customer/trips'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthMiddleware::validateCsrf();
            $repo = new DriverSelfServiceRepository();

            $items = [];
            foreach ($_POST['item_name'] as $i => $name) {
                $items[] = [
                    'name' => $name,
                    'status' => $_POST['item_status'][$i] ?? 'ok',
                    'note' => $_POST['item_note'][$i] ?? '',
                ];
            }

            $repo->createChecklist([
                'trip_id' => $tripId,
                'check_type' => $type,
                'submitted_by' => $_SESSION['user_id'],
                'items' => $items,
                'overall_condition' => $_POST['overall_condition'],
                'notes' => $_POST['notes'] ?? null,
            ]);
            $_SESSION['_flash']['success'] = 'Checklist ' . str_replace('_', ' ', $type) . ' berhasil disimpan';
            redirect('/customer/checklists/' . $tripId);
        }

        $defaultItems = [
            'Ban & Roda', 'Lampu (depan, belakang, sein)', 'Kaca & Spion',
            'Kebersihan interior', 'Klason', 'Air radiator & wiper',
            'Sabuk pengaman', 'Toolkit & dongkrak',
        ];
        if ($type === 'post_trip') {
            $defaultItems = [
                'Ban & Roda', 'Lampu', 'Kaca & Spion',
                'Kebersihan interior', 'Bahan bakar (posisi)',
                'Kelengkapan dokumen STNK/KIR',
            ];
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Checklist <?= str_replace('_', ' ', ucfirst($type)) ?></h3></div>
            <div class="card-body">
                <p style="margin-bottom:16px;font-size:14px">
                    Trip: <strong><?= e($trip['trip_number']) ?></strong> —
                    <?= e($trip['origin']) ?> → <?= e($trip['destination']) ?>
                </p>
                <form method="post">
                    <?= csrf_field() ?>
                    <div class="table-wrap"><table>
                        <thead><tr><th style="width:40%">Item</th><th style="width:80px">Status</th><th>Catatan</th></tr></thead>
                        <tbody id="checklist-items">
                            <?php foreach ($defaultItems as $i => $item): ?>
                            <tr>
                                <td>
                                    <input type="hidden" name="item_name[]" value="<?= e($item) ?>">
                                    <?= e($item) ?>
                                </td>
                                <td>
                                    <select name="item_status[]" class="form-control form-control-sm">
                                        <option value="ok">✅ OK</option>
                                        <option value="not_ok">❌ Tidak OK</option>
                                        <option value="na">N/A</option>
                                    </select>
                                </td>
                                <td><input type="text" name="item_note[]" class="form-control form-control-sm" placeholder="Catatan"></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table></div>
                    <button type="button" class="btn btn-outline btn-sm" style="margin:10px 0" onclick="addItem()">+ Tambah Item</button>
                    <div class="form-group">
                        <label>Kondisi Keseluruhan</label>
                        <select name="overall_condition" class="form-control" style="max-width:200px" required>
                            <option value="good">Good</option>
                            <option value="minor_issue">Minor Issue</option>
                            <option value="major_issue">Major Issue</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Catatan Tambahan</label>
                        <textarea name="notes" class="form-control" rows="2"></textarea>
                    </div>
                    <div style="margin-top:20px">
                        <button type="submit" class="btn btn-success">Simpan Checklist</button>
                        <a href="/customer/checklists/<?= $tripId ?>" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <script>
        function addItem() {
            const tbody = document.getElementById('checklist-items');
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="text" name="item_name[]" class="form-control form-control-sm" required></td>
                <td><select name="item_status[]" class="form-control form-control-sm">
                    <option value="ok">✅ OK</option>
                    <option value="not_ok">❌ Tidak OK</option>
                    <option value="na">N/A</option>
                </select></td>
                <td><input type="text" name="item_note[]" class="form-control form-control-sm" placeholder="Catatan"></td>
            `;
            tbody.appendChild(tr);
        }
        </script>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../CustomerPanel/Views/layout.php';
        renderCustomerLayout('Checklist ' . ucfirst($type), $content, ['active' => 'trips']);
    }

    public function photos(int $tripId): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');

        $db = Database::getConnection();
        $trip = $db->prepare("SELECT * FROM mova_trips WHERE id = ?");
        $trip->execute([$tripId]);
        $trip = $trip->fetch();
        if (!$trip) { $_SESSION['_flash']['error'] = 'Trip tidak ditemukan'; redirect('/customer/trips'); }

        $repo = new DriverSelfServiceRepository();
        $photos = $repo->findPhotosByTrip($tripId);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthMiddleware::validateCsrf();
            $uploadDir = __DIR__ . '/../../public/uploads/trip_photos/';
            if (!is_dir($uploadDir)) { mkdir($uploadDir, 0755, true); }

            $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];
            $files = $_FILES['photos'] ?? null;
            $uploaded = 0;

            if ($files && is_array($files['name'])) {
                foreach ($files['name'] as $i => $name) {
                    if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mime = finfo_file($finfo, $files['tmp_name'][$i]);
                    finfo_close($finfo);
                    if (!in_array($mime, $allowedMime, true)) continue;

                    $ext = pathinfo($name, PATHINFO_EXTENSION);
                    $filename = 'trip_' . $tripId . '_' . time() . '_' . $i . '.' . $ext;
                    $dest = $uploadDir . $filename;
                    if (move_uploaded_file($files['tmp_name'][$i], $dest)) {
                        $repo->addPhoto([
                            'trip_id' => $tripId,
                            'photo_type' => $_POST['photo_type'] ?? 'pre_trip',
                            'position' => $_POST['position'] ?? 'other',
                            'file_path' => 'uploads/trip_photos/' . $filename,
                            'uploaded_by' => $_SESSION['user_id'],
                        ]);
                        $uploaded++;
                    }
                }
            }

            $_SESSION['_flash']['success'] = "$uploaded foto berhasil diupload";
            redirect('/customer/checklists/' . $tripId);
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Upload Foto — <?= e($trip['trip_number']) ?></h3></div>
            <div class="card-body">
                <?php if ($photos): ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:8px;margin-bottom:20px">
                    <?php foreach ($photos as $p): ?>
                    <div style="border:1px solid var(--border);border-radius:6px;padding:8px;text-align:center">
                        <div style="width:100%;height:80px;background:var(--surface-2);border-radius:4px;margin-bottom:4px;display:flex;align-items:center;justify-content:center;color:var(--text-muted)"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg></div>
                        <small style="font-size:10px;color:var(--text-2)"><?= e($p['position']) ?><br><?= e($p['photo_type']) ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
                <form method="post" enctype="multipart/form-data" style="max-width:500px">
                    <?= csrf_field() ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Tipe Foto</label>
                            <select name="photo_type" class="form-control">
                                <option value="pre_trip">Pre-Trip</option>
                                <option value="post_trip">Post-Trip</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Posisi</label>
                            <select name="position" class="form-control">
                                <option value="front">Depan</option>
                                <option value="rear">Belakang</option>
                                <option value="left">Kiri</option>
                                <option value="right">Kanan</option>
                                <option value="interior">Interior</option>
                                <option value="other">Lainnya</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Pilih Foto (bisa multiple)</label>
                        <input type="file" name="photos[]" class="form-control" accept="image/jpeg,image/png,image/webp" multiple required>
                    </div>
                    <div style="margin-top:20px">
                        <button type="submit" class="btn btn-success">Upload</button>
                        <a href="/customer/checklists/<?= $tripId ?>" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../CustomerPanel/Views/layout.php';
        renderCustomerLayout('Upload Foto', $content, ['active' => 'trips']);
    }
}
