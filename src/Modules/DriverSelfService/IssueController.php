<?php

class IssueController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');
        $repo = new DriverSelfServiceRepository();
        $issues = $repo->findIssues();

        ob_start();
        ?>
        <div class="card">
            <div class="card-header">
                <h3>Issue Report</h3>
                <a href="/customer/issues/create" class="btn btn-primary btn-sm">+ Lapor Issue</a>
            </div>
            <div class="card-body">
                <?php if (empty($issues)): ?>
                    <div class="empty-state"><p>Belum ada laporan issue.</p></div>
                <?php else: ?>
                <div class="table-wrap"><table>
                    <thead><tr>
                        <th>No. Laporan</th><th>Kendaraan</th><th>Kategori</th><th>Severity</th><th>Status</th><th>Pelapor</th><th>Tanggal</th><th>Aksi</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($issues as $iss): ?>
                        <tr>
                            <td><strong><?= e($iss['report_number']) ?></strong></td>
                            <td><?= e($iss['plate_number']) ?></td>
                            <td><?= e($iss['category']) ?></td>
                            <td><span class="badge badge-<?= $iss['severity'] === 'critical' ? 'danger' : 'warning' ?>"><?= e($iss['severity']) ?></span></td>
                            <td><span class="badge badge-<?= e($iss['status']) ?>"><?= e($iss['status']) ?></span></td>
                            <td><?= e($iss['reported_by_name']) ?></td>
                            <td><?= date('d/m/Y', strtotime($iss['created_at'])) ?></td>
                            <td>
                                <a href="/customer/issues/<?= $iss['id'] ?>/edit" class="btn btn-outline btn-sm">Edit</a>
                                <?php if (in_array($iss['status'], ['open','in_review'])): ?>
                                <form method="post" action="/customer/issues/<?= $iss['id'] ?>/resolve" style="display:inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-success btn-sm">Selesai</button>
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
        renderCustomerLayout('Issue Report', $content, ['active' => 'issues']);
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
            $repo = new DriverSelfServiceRepository();
            $repo->createIssue([
                'customer_id' => $customerId,
                'vehicle_id' => $_POST['vehicle_id'],
                'report_number' => generateNumber('ISS'),
                'reported_by' => $_SESSION['user_id'],
                'category' => $_POST['category'],
                'description' => $_POST['description'],
                'severity' => $_POST['severity'],
            ]);
            $_SESSION['_flash']['success'] = 'Issue berhasil dilaporkan';
            redirect('/customer/issues');
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Laporkan Issue</h3></div>
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
                        <label>Kategori *</label>
                        <select name="category" class="form-control" required>
                            <option value="">-- Pilih --</option>
                            <?php foreach (configOptions('issue_category') as $opt): ?>
                            <option value="<?= e($opt['value']) ?>"><?= e($opt['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tingkat Keparahan *</label>
                        <select name="severity" class="form-control" required>
                            <option value="low">Low</option>
                            <option value="medium">Medium</option>
                            <option value="high">High</option>
                            <option value="critical">Critical</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Deskripsi *</label>
                        <textarea name="description" class="form-control" rows="4" required></textarea>
                    </div>
                    <div style="margin-top:20px">
                        <button type="submit" class="btn btn-success">Laporkan</button>
                        <a href="/customer/issues" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../CustomerPanel/Views/layout.php';
        renderCustomerLayout('Lapor Issue', $content, ['active' => 'issues']);
    }

    public function edit(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');
        $tenant = SessionMiddleware::getTenantContext();
        $customerId = $tenant->getCustomerId();
        $db = Database::getConnection();

        if ($tenant->isSuperAdmin()) {
            $issue = $db->prepare("SELECT * FROM mova_issue_reports WHERE id = ?");
            $issue->execute([$id]); $issue = $issue->fetch();
            if ($issue) $customerId = $issue['customer_id'];
            $vehicles = $db->query("SELECT v.*, c.name as customer_name FROM mova_vehicles v LEFT JOIN mova_customers c ON c.id = v.customer_id WHERE v.is_active = 1")->fetchAll();
        } else {
            $issue = $db->prepare("SELECT * FROM mova_issue_reports WHERE id = ? AND customer_id = ?");
            $issue->execute([$id, $customerId]); $issue = $issue->fetch();
            $vehicles = $db->prepare("SELECT * FROM mova_vehicles WHERE customer_id = ? AND is_active = 1");
            $vehicles->execute([$customerId]); $vehicles = $vehicles->fetchAll();
        }
        if (!$issue) { $_SESSION['_flash']['error'] = 'Data tidak ditemukan'; redirect('/customer/issues'); }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            AuthMiddleware::validateCsrf();
            $stmt = $db->prepare("UPDATE mova_issue_reports SET vehicle_id=?, category=?, severity=?, description=? WHERE id=? AND customer_id=?");
            $stmt->execute([$_POST['vehicle_id'], $_POST['category'], $_POST['severity'], $_POST['description'], $id, $customerId]);
            $_SESSION['_flash']['success'] = 'Issue diupdate';
            redirect('/customer/issues');
        }

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Edit Issue</h3></div>
            <div class="card-body">
                <form method="post" style="max-width:600px">
                    <?= csrf_field() ?>
                    <div class="form-group">
                        <label>Kendaraan *</label>
                        <select name="vehicle_id" class="form-control" required>
                            <?php foreach ($vehicles as $v): ?>
                            <option value="<?= $v['id'] ?>" <?= $v['id'] == $issue['vehicle_id'] ? 'selected' : '' ?>><?= isset($v['customer_name']) ? '[' . e($v['customer_name']) . '] ' : '' ?><?= e($v['plate_number']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Kategori *</label>
                            <?php $curC = $issue['category'] ?? ''; $optsC = configOptions('issue_category'); ?>
                            <select name="category" class="form-control" required>
                                <?php if ($curC !== '' && !in_array($curC, array_column($optsC, 'value'), true)): ?>
                                <option value="<?= e($curC) ?>" selected><?= e(ucfirst(str_replace('_', ' ', $curC))) ?></option>
                                <?php endif; ?>
                                <?php foreach ($optsC as $opt): ?>
                                <option value="<?= e($opt['value']) ?>" <?= $curC === $opt['value'] ? 'selected' : '' ?>><?= e($opt['label']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Severity *</label>
                            <select name="severity" class="form-control" required>
                                <?php foreach (['low','medium','high','critical'] as $sev): ?>
                                <option value="<?= $sev ?>" <?= ($issue['severity'] ?? '') === $sev ? 'selected' : '' ?>><?= ucfirst($sev) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Deskripsi *</label>
                        <textarea name="description" class="form-control" rows="4" required><?= e($issue['description']) ?></textarea>
                    </div>
                    <div style="margin-top:20px">
                        <button type="submit" class="btn btn-success">Simpan</button>
                        <a href="/customer/issues" class="btn btn-outline">Batal</a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/../CustomerPanel/Views/layout.php';
        renderCustomerLayout('Edit Issue', $content, ['active' => 'issues']);
    }

    public function resolve(int $id): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');
        AuthMiddleware::validateCsrf();

        $db = Database::getConnection();
        $issue = $db->prepare("SELECT * FROM mova_issue_reports WHERE id = ?");
        $issue->execute([$id]); $issue = $issue->fetch();

        $repo = new DriverSelfServiceRepository();
        $repo->updateIssueStatus($id, 'resolved', 'Diselesaikan oleh ' . ($_SESSION['user_name'] ?? ''));

        if ($issue) {
            createNotification([
                'user_id' => $issue['reported_by'],
                'customer_id' => $issue['customer_id'],
                'type' => 'issue',
                'title' => 'Issue Diselesaikan',
                'message' => 'Issue ' . $issue['report_number'] . ' telah ditandai selesai.',
                'reference_type' => 'issue_reports',
                'reference_id' => $id,
            ]);
        }
        $_SESSION['_flash']['success'] = 'Issue ditandai selesai';
        redirect('/customer/issues');
    }
}
