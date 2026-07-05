<?php

class DashboardController
{
    public function home(): void
    {
        if (SessionMiddleware::isLoggedIn()) {
            redirect('/dashboard');
        }
        redirect('/login');
    }

    public function index(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('company');
        $tenant = SessionMiddleware::getTenantContext();
        $db = Database::getConnection();

        if ($tenant->isSuperAdmin()) {
            $stats = [
                'regions' => (int) $db->query("SELECT COUNT(*) FROM mova_regions WHERE is_active = 1")->fetchColumn(),
                'branches' => (int) $db->query("SELECT COUNT(*) FROM mova_branches WHERE is_active = 1")->fetchColumn(),
                'customers' => (int) $db->query("SELECT COUNT(*) FROM mova_customers WHERE is_active = 1")->fetchColumn(),
                'vehicles' => (int) $db->query("SELECT COUNT(*) FROM mova_vehicles WHERE is_active = 1")->fetchColumn(),
                'users' => (int) $db->query("SELECT COUNT(*) FROM mova_users WHERE is_active = 1")->fetchColumn(),
            ];
        } else {
            $customerIds = $tenant->getAccessibleCustomerIds();
            $branchIds = $tenant->getBranchIds();

            $branchCount = 0;
            if (!empty($branchIds)) {
                $ph = implode(',', array_fill(0, count($branchIds), '?'));
                $stmt = $db->prepare("SELECT COUNT(*) FROM mova_branches WHERE id IN ($ph) AND is_active = 1");
                $stmt->execute($branchIds);
                $branchCount = (int) $stmt->fetchColumn();
            }

            if (empty($customerIds)) {
                $stats = [
                    'regions' => 0, 'branches' => $branchCount,
                    'customers' => 0, 'vehicles' => 0, 'users' => 0,
                ];
            } else {
                $ph = implode(',', array_fill(0, count($customerIds), '?'));
                $stmt = $db->prepare("SELECT COUNT(*) FROM mova_customers WHERE id IN ($ph) AND is_active = 1");
                $stmt->execute($customerIds);
                $customerCount = (int) $stmt->fetchColumn();

                $stmt = $db->prepare("SELECT COUNT(*) FROM mova_vehicles WHERE customer_id IN ($ph) AND is_active = 1");
                $stmt->execute($customerIds);
                $vehicleCount = (int) $stmt->fetchColumn();

                $stmt = $db->prepare("SELECT COUNT(*) FROM mova_users WHERE customer_id IN ($ph) AND is_active = 1");
                $stmt->execute($customerIds);
                $userCount = (int) $stmt->fetchColumn();

                $stats = [
                    'regions' => 0, 'branches' => $branchCount,
                    'customers' => $customerCount, 'vehicles' => $vehicleCount, 'users' => $userCount,
                ];
            }
        }

        // -- Chart queries --
        // Vehicle status breakdown (tenant-aware)
        if ($tenant->isSuperAdmin()) {
            $vsStmt = $db->query("SELECT status, COUNT(*) as cnt FROM mova_vehicles WHERE is_active = 1 GROUP BY status");
        } else {
            $customerIds = $tenant->getAccessibleCustomerIds();
            if (!empty($customerIds)) {
                $ph = implode(',', array_fill(0, count($customerIds), '?'));
                $vsStmt = $db->prepare("SELECT status, COUNT(*) as cnt FROM mova_vehicles WHERE customer_id IN ($ph) AND is_active = 1 GROUP BY status");
                $vsStmt->execute($customerIds);
            } else {
                $vsStmt = null;
            }
        }
        $vehicleStatusData = ['active' => 0, 'maintenance' => 0, 'inactive' => 0];
        if ($vsStmt) {
            foreach ($vsStmt->fetchAll() as $row) {
                $vehicleStatusData[$row['status']] = (int)$row['cnt'];
            }
        }

        // Trip count per month — last 6 months
        $tripMonthLabels = [];
        $tripMonthCounts = [];
        for ($i = 5; $i >= 0; $i--) {
            $tripMonthLabels[] = date('M Y', strtotime("-$i months"));
            $ym = date('Y-m', strtotime("-$i months"));
            if ($tenant->isSuperAdmin()) {
                $tmStmt = $db->prepare("SELECT COUNT(*) FROM mova_trips WHERE DATE_FORMAT(created_at,'%Y-%m') = ?");
                $tmStmt->execute([$ym]);
            } else {
                $customerIds = $tenant->getAccessibleCustomerIds();
                if (!empty($customerIds)) {
                    $ph = implode(',', array_fill(0, count($customerIds), '?'));
                    $tmStmt = $db->prepare("SELECT COUNT(*) FROM mova_trips WHERE customer_id IN ($ph) AND DATE_FORMAT(created_at,'%Y-%m') = ?");
                    $tmStmt->execute(array_merge($customerIds, [$ym]));
                } else {
                    $tmStmt = null;
                }
            }
            $tripMonthCounts[] = $tmStmt ? (int)$tmStmt->fetchColumn() : 0;
        }

        ob_start();
        ?>
        <!-- Stat Cards -->
        <div class="stats">
            <div class="stat-card">
                <span class="stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg></span>
                <div class="stat-label">Region</div>
                <div class="stat-value"><?= $stats['regions'] ?></div>
                <a href="/regions" class="stat-link">Kelola →</a>
            </div>
            <div class="stat-card">
                <span class="stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></span>
                <div class="stat-label">Branch</div>
                <div class="stat-value"><?= $stats['branches'] ?></div>
                <a href="/branches" class="stat-link">Kelola →</a>
            </div>
            <div class="stat-card">
                <span class="stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg></span>
                <div class="stat-label">Customer</div>
                <div class="stat-value"><?= $stats['customers'] ?></div>
                <a href="/customers" class="stat-link">Kelola →</a>
            </div>
            <div class="stat-card">
                <span class="stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h3l3 3v4h-6V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></span>
                <div class="stat-label">Vehicle</div>
                <div class="stat-value"><?= $stats['vehicles'] ?></div>
                <a href="/vehicles" class="stat-link">Kelola →</a>
            </div>
            <div class="stat-card">
                <span class="stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20v-1a8 8 0 0 1 16 0v1"/></svg></span>
                <div class="stat-label">User</div>
                <div class="stat-value"><?= $stats['users'] ?></div>
                <a href="/users" class="stat-link">Kelola →</a>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="dashboard-charts">
            <div class="card chart-card">
                <div class="card-header"><h3>Status Armada</h3></div>
                <div class="card-body chart-body">
                    <canvas id="vehicleStatusChart"></canvas>
                </div>
            </div>
            <div class="card chart-card">
                <div class="card-header"><h3>Trip 6 Bulan Terakhir</h3></div>
                <div class="card-body chart-body">
                    <canvas id="tripMonthChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Quick Access -->
        <div class="card">
            <div class="card-header"><h3>Akses Cepat</h3></div>
            <div class="card-body">
                <div class="quick-access">
                    <?php if ($tenant->isSuperAdmin()): ?>
                    <a href="/regions/create" class="quick-access__btn quick-access__btn--primary">
                        <span class="quick-access__icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        </span>
                        <span>+ Region Baru</span>
                    </a>
                    <a href="/branches/create" class="quick-access__btn quick-access__btn--primary">
                        <span class="quick-access__icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                        </span>
                        <span>+ Branch Baru</span>
                    </a>
                    <?php endif; ?>
                    <a href="/customers/create" class="quick-access__btn">
                        <span class="quick-access__icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                        </span>
                        <span>+ Customer Baru</span>
                    </a>
                    <a href="/vehicles/create" class="quick-access__btn">
                        <span class="quick-access__icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h3l3 3v4h-6V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
                        </span>
                        <span>+ Vehicle Baru</span>
                    </a>
                    <a href="/users/create" class="quick-access__btn">
                        <span class="quick-access__icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="8" r="4"/><path d="M4 20v-1a8 8 0 0 1 16 0v1"/></svg>
                        </span>
                        <span>+ User Baru</span>
                    </a>
                </div>
            </div>
        </div>

        <script>
        (function() {
            Chart.defaults.color = '#64748B';
            Chart.defaults.font = { family: "'Inter', sans-serif", size: 12 };

            // Doughnut — Vehicle Status
            new Chart(document.getElementById('vehicleStatusChart'), {
                type: 'doughnut',
                data: {
                    labels: ['Aktif', 'Maintenance', 'Tidak Aktif'],
                    datasets: [{
                        data: [<?= $vehicleStatusData['active'] ?>, <?= $vehicleStatusData['maintenance'] ?>, <?= $vehicleStatusData['inactive'] ?>],
                        backgroundColor: ['#0F6E56', '#D97706', '#94A3B8'],
                        borderColor: '#FFFFFF',
                        borderWidth: 2,
                        hoverOffset: 6,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    cutout: '65%',
                    plugins: {
                        legend: { position: 'bottom', labels: { padding: 16, usePointStyle: true, color: '#64748B' } },
                        tooltip: { backgroundColor: '#0F172A', padding: 12, cornerRadius: 8 }
                    }
                }
            });

            // Bar — Trip per Month
            new Chart(document.getElementById('tripMonthChart'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($tripMonthLabels) ?>,
                    datasets: [{
                        label: 'Jumlah Trip',
                        data: <?= json_encode($tripMonthCounts) ?>,
                        backgroundColor: 'rgba(15,110,86,0.15)',
                        borderColor: '#0F6E56',
                        borderWidth: 1.5,
                        borderRadius: 6,
                        borderSkipped: false,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { display: false },
                        tooltip: { backgroundColor: '#0F172A', padding: 12, cornerRadius: 8 }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: '#64748B' } },
                        y: { beginAtZero: true, grid: { color: '#E2E8F0' }, border: { display: false }, ticks: { color: '#64748B', stepSize: 1 } }
                    }
                }
            });
        })();
        </script>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/Views/layout.php';
        renderLayout('Dashboard', $content, ['active' => 'dashboard']);
    }
}
