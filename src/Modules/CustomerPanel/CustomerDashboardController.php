<?php

class CustomerDashboardController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');

        $tenant = SessionMiddleware::getTenantContext();
        $customerId = $tenant->getCustomerId();
        $db = Database::getConnection();

        $stats = [];
        $recentTrips = [];
        $recentNotifications = [];
        if ($customerId) {
            $stats['vehicles'] = $db->prepare("SELECT COUNT(*) FROM mova_vehicles WHERE customer_id = ? AND is_active = 1");
            $stats['vehicles']->execute([$customerId]); $stats['vehicles'] = $stats['vehicles']->fetchColumn();

            $stats['pending_requests'] = $db->prepare("SELECT COUNT(*) FROM mova_vehicle_requests WHERE customer_id = ? AND status = 'pending'");
            $stats['pending_requests']->execute([$customerId]); $stats['pending_requests'] = $stats['pending_requests']->fetchColumn();

            $stats['active_trips'] = $db->prepare("SELECT COUNT(*) FROM mova_trips WHERE customer_id = ? AND status = 'in_progress'");
            $stats['active_trips']->execute([$customerId]); $stats['active_trips'] = $stats['active_trips']->fetchColumn();

            $stats['open_issues'] = $db->prepare("SELECT COUNT(*) FROM mova_issue_reports WHERE customer_id = ? AND status IN ('open','in_review','in_progress')");
            $stats['open_issues']->execute([$customerId]); $stats['open_issues'] = $stats['open_issues']->fetchColumn();

            $recentTrips = $db->prepare("SELECT t.id, t.trip_number, t.trip_date, t.origin, t.destination, t.status, v.plate_number FROM mova_trips t JOIN mova_vehicles v ON v.id = t.vehicle_id WHERE t.customer_id = ? ORDER BY t.created_at DESC LIMIT 5");
            $recentTrips->execute([$customerId]); $recentTrips = $recentTrips->fetchAll();

            $recentNotifications = $db->prepare("SELECT * FROM mova_notifications WHERE customer_id = ? ORDER BY created_at DESC LIMIT 5");
            $recentNotifications->execute([$customerId]); $recentNotifications = $recentNotifications->fetchAll();

            // Chart: trip per 6 months
            $tripMonthLabels = [];
            $tripMonthCounts = [];
            for ($i = 5; $i >= 0; $i--) {
                $tripMonthLabels[] = date('M Y', strtotime("-$i months"));
                $ym = date('Y-m', strtotime("-$i months"));
                $tmStmt = $db->prepare("SELECT COUNT(*) FROM mova_trips WHERE customer_id = ? AND DATE_FORMAT(created_at,'%Y-%m') = ?");
                $tmStmt->execute([$customerId, $ym]);
                $tripMonthCounts[] = (int)$tmStmt->fetchColumn();
            }

            // Chart: BBM liters per week (last 4 weeks)
            $fuelWeekLabels = [];
            $fuelWeekLiters = [];
            for ($i = 3; $i >= 0; $i--) {
                $start = date('Y-m-d', strtotime("-$i weeks monday"));
                $end   = date('Y-m-d', strtotime("-$i weeks sunday"));
                $fuelWeekLabels[] = date('d M', strtotime($start)) . ' – ' . date('d M', strtotime($end));
                $fStmt = $db->prepare("SELECT COALESCE(SUM(liters),0) FROM mova_fuel_reports WHERE customer_id = ? AND fuel_date BETWEEN ? AND ?");
                $fStmt->execute([$customerId, $start, $end]);
                $fuelWeekLiters[] = (float)$fStmt->fetchColumn();
            }
        } else {
            $tripMonthLabels = $tripMonthCounts = $fuelWeekLabels = $fuelWeekLiters = [];
        }

        ob_start();
        ?>
        <!-- Stat Cards -->
        <div class="stats">
            <div class="stat-card">
                <span class="stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h3l3 3v4h-6V8z"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></span>
                <div class="stat-label">Kendaraan</div>
                <div class="stat-value"><?= (int)($stats['vehicles'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <span class="stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></span>
                <div class="stat-label">Request Pending</div>
                <div class="stat-value"><?= (int)($stats['pending_requests'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <span class="stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg></span>
                <div class="stat-label">Trip Aktif</div>
                <div class="stat-value"><?= (int)($stats['active_trips'] ?? 0) ?></div>
            </div>
            <div class="stat-card">
                <span class="stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span>
                <div class="stat-label">Issue Open</div>
                <div class="stat-value"><?= (int)($stats['open_issues'] ?? 0) ?></div>
            </div>
        </div>

        <!-- Quick Access -->
        <div class="card">
            <div class="card-header"><h3>Akses Cepat</h3></div>
            <div class="card-body">
                <div class="quick-access">
                    <a href="/customer/requests/create" class="quick-access__btn">
                        <span class="quick-access__icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                        </span>
                        <span>+ Request Vehicle</span>
                    </a>
                    <a href="/customer/trips/create" class="quick-access__btn">
                        <span class="quick-access__icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        </span>
                        <span>+ Input Trip</span>
                    </a>
                    <a href="/customer/issues/create" class="quick-access__btn">
                        <span class="quick-access__icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        </span>
                        <span>+ Lapor Issue</span>
                    </a>
                    <a href="/customer/fuel/create" class="quick-access__btn">
                        <span class="quick-access__icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 22v-8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8"/><path d="M5 22h14"/><path d="M7 10l5-6 5 6"/><path d="M12 4v14"/></svg>
                        </span>
                        <span>+ Lapor BBM</span>
                    </a>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="dashboard-charts mb-2">
            <div class="card chart-card">
                <div class="card-header"><h3>Trip 6 Bulan Terakhir</h3></div>
                <div class="card-body chart-body">
                    <canvas id="tripMonthChart"></canvas>
                </div>
            </div>
            <div class="card chart-card">
                <div class="card-header"><h3>BBM 4 Minggu Terakhir (Liter)</h3></div>
                <div class="card-body chart-body">
                    <canvas id="fuelWeekChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Recent tables -->
        <div class="grid-2">
            <div class="card">
                <div class="card-header"><h3>Trip Terbaru</h3></div>
                <div class="card-body">
                    <?php if (empty($recentTrips)): ?>
                        <div class="empty-state"><p>Belum ada trip.</p></div>
                    <?php else: ?>
                    <table class="table-sm">
                        <thead><tr><th>No</th><th>Rute</th><th>Status</th></tr></thead>
                        <tbody>
                            <?php foreach ($recentTrips as $t): ?>
                            <tr>
                                <td><strong><a href="/customer/trips/<?= $t['id'] ?>"><?= e($t['trip_number']) ?></a></strong></td>
                                <td><?= e($t['origin']) ?> → <?= e($t['destination']) ?></td>
                                <td><span class="badge badge-<?= e($t['status']) ?>"><?= e($t['status']) ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3>Notifikasi Terbaru</h3></div>
                <div class="card-body">
                    <?php if (empty($recentNotifications)): ?>
                        <div class="empty-state"><p>Belum ada notifikasi.</p></div>
                    <?php else: ?>
                    <table class="table-sm">
                        <thead><tr><th>Pesan</th><th>Waktu</th></tr></thead>
                        <tbody>
                            <?php foreach ($recentNotifications as $n): ?>
                            <tr class="<?= !$n['is_read'] ? 'row-bold' : '' ?>">
                                <td><?= e($n['message']) ?></td>
                                <td class="text-nowrap text-muted"><?= date('d/m H:i', strtotime($n['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
        (function() {
            Chart.defaults.color = '#64748B';
            Chart.defaults.font = { family: "'Inter', sans-serif", size: 12 };

            // Line — Trip per Month
            new Chart(document.getElementById('tripMonthChart'), {
                type: 'line',
                data: {
                    labels: <?= json_encode($tripMonthLabels) ?>,
                    datasets: [{
                        label: 'Jumlah Trip',
                        data: <?= json_encode($tripMonthCounts) ?>,
                        borderColor: '#0F6E56',
                        backgroundColor: 'rgba(15,110,86,0.10)',
                        borderWidth: 2,
                        pointBackgroundColor: '#0F6E56',
                        pointRadius: 3,
                        tension: 0.35,
                        fill: true,
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

            // Bar — BBM per Week
            new Chart(document.getElementById('fuelWeekChart'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($fuelWeekLabels) ?>,
                    datasets: [{
                        label: 'Liter',
                        data: <?= json_encode($fuelWeekLiters) ?>,
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
                        y: { beginAtZero: true, grid: { color: '#E2E8F0' }, border: { display: false }, ticks: { color: '#64748B' } }
                    }
                }
            });
        })();
        </script>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/Views/layout.php';
        renderCustomerLayout('Dashboard', $content, ['active' => 'dashboard']);
    }
}
