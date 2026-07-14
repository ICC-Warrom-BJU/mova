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

        // -- Master Data Stats (existing) --
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

        // -- Operational Stats (tenant-aware helper) --
        $isSa = $tenant->isSuperAdmin();
        $cIds = $tenant->getAccessibleCustomerIds();
        $hasCust = !empty($cIds);

        $tenantCount = function(string $baseSql, array $extraParams = []) use ($db, $isSa, $cIds, $hasCust) {
            if ($isSa) {
                $sql = str_replace('__TENANT__', '', $baseSql);
                $stmt = $db->prepare($sql);
                $stmt->execute($extraParams);
            } elseif ($hasCust) {
                $ph = implode(',', array_fill(0, count($cIds), '?'));
                $sql = str_replace('__TENANT__', " AND customer_id IN ($ph)", $baseSql);
                $stmt = $db->prepare($sql);
                $stmt->execute(array_merge($cIds, $extraParams));
            } else {
                return 0;
            }
            return (int) $stmt->fetchColumn();
        };

        $tenantCountAll = function(string $baseSql) use ($db, $isSa, $cIds, $hasCust) {
            if ($isSa) {
                $sql = str_replace('__TENANT__', '', $baseSql);
                $stmt = $db->query($sql);
                return $stmt->fetchAll();
            } elseif ($hasCust) {
                $ph = implode(',', array_fill(0, count($cIds), '?'));
                $sql = str_replace('__TENANT__', " WHERE customer_id IN ($ph)", $baseSql);
                $stmt = $db->prepare($sql);
                $stmt->execute($cIds);
                return $stmt->fetchAll();
            }
            return [];
        };

        $opPendingReqs = $tenantCount("SELECT COUNT(*) FROM mova_vehicle_requests WHERE status = 'pending' __TENANT__");
        $opActiveTrips = $tenantCount("SELECT COUNT(*) FROM mova_trips WHERE status = 'in_progress' __TENANT__");
        $opOpenIssues = $tenantCount("SELECT COUNT(*) FROM mova_issue_reports WHERE status IN ('open','in_review','in_progress') __TENANT__");
        $opPendingFuel = $tenantCount("SELECT COUNT(*) FROM mova_fuel_reports WHERE status = 'pending' __TENANT__");
        $opPendingExp = $tenantCount("SELECT COUNT(*) FROM mova_expense_reports WHERE status = 'pending' __TENANT__");

        // -- Fleet Utilization --
        $fleetTotal = $tenantCount("SELECT COUNT(*) FROM mova_vehicles WHERE is_active = 1 __TENANT__");
        $fleetMaintenance = $tenantCount("SELECT COUNT(*) FROM mova_vehicles WHERE is_active = 1 AND status = 'maintenance' __TENANT__");
        $fleetOnTrip = $tenantCount("SELECT COUNT(DISTINCT vehicle_id) FROM mova_trips WHERE status = 'in_progress' __TENANT__");
        $fleetAvailable = max(0, $fleetTotal - $fleetMaintenance - $fleetOnTrip);

        // -- Driver Activity --
        $driverTotal = $tenantCount("SELECT COUNT(*) FROM mova_users u JOIN mova_roles r ON r.id = u.role_id WHERE r.name = 'driver' AND u.is_active = 1 __TENANT__");
        $driverOnTrip = $tenantCount("SELECT COUNT(DISTINCT driver_id) FROM mova_trips WHERE status = 'in_progress' __TENANT__");
        $driverAvailable = max(0, $driverTotal - $driverOnTrip);

        // -- Vehicle Status Breakdown --
        if ($isSa) {
            $vsStmt = $db->query("SELECT status, COUNT(*) as cnt FROM mova_vehicles WHERE is_active = 1 GROUP BY status");
        } elseif ($hasCust) {
            $ph = implode(',', array_fill(0, count($cIds), '?'));
            $vsStmt = $db->prepare("SELECT status, COUNT(*) as cnt FROM mova_vehicles WHERE customer_id IN ($ph) AND is_active = 1 GROUP BY status");
            $vsStmt->execute($cIds);
        } else {
            $vsStmt = null;
        }
        $vehicleStatusData = ['active' => 0, 'maintenance' => 0, 'inactive' => 0];
        if ($vsStmt) {
            foreach ($vsStmt->fetchAll() as $row) {
                $vehicleStatusData[$row['status']] = (int)$row['cnt'];
            }
        }

        // -- Monthly Trends: last 6 months --
        $monthLabels = [];
        $tripCounts = [];
        $distances = [];
        $fuelCosts = [];
        $expenses = [];
        for ($i = 5; $i >= 0; $i--) {
            $monthLabels[] = date('M Y', strtotime("-$i months"));
            $ym = date('Y-m', strtotime("-$i months"));

            if ($isSa) {
                $tStmt = $db->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(distance_km),0) AS total_km FROM mova_trips WHERE DATE_FORMAT(created_at,'%Y-%m') = ?");
                $tStmt->execute([$ym]);
                $fStmt = $db->prepare("SELECT COALESCE(SUM(total_cost),0) AS val FROM mova_fuel_reports WHERE DATE_FORMAT(fuel_date,'%Y-%m') = ? AND status = 'approved'");
                $fStmt->execute([$ym]);
                $eStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) AS val FROM mova_expense_reports WHERE DATE_FORMAT(expense_date,'%Y-%m') = ? AND status = 'approved'");
                $eStmt->execute([$ym]);
            } elseif ($hasCust) {
                $ph = implode(',', array_fill(0, count($cIds), '?'));
                $tStmt = $db->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(distance_km),0) AS total_km FROM mova_trips WHERE customer_id IN ($ph) AND DATE_FORMAT(created_at,'%Y-%m') = ?");
                $tStmt->execute(array_merge($cIds, [$ym]));
                $fStmt = $db->prepare("SELECT COALESCE(SUM(total_cost),0) AS val FROM mova_fuel_reports WHERE customer_id IN ($ph) AND DATE_FORMAT(fuel_date,'%Y-%m') = ? AND status = 'approved'");
                $fStmt->execute(array_merge($cIds, [$ym]));
                $eStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) AS val FROM mova_expense_reports WHERE customer_id IN ($ph) AND DATE_FORMAT(expense_date,'%Y-%m') = ? AND status = 'approved'");
                $eStmt->execute(array_merge($cIds, [$ym]));
            } else {
                $tStmt = null;
            }

            if ($tStmt) {
                $tRow = $tStmt->fetch();
                $tripCounts[] = (int) ($tRow['cnt'] ?? 0);
                $distances[] = (int) ($tRow['total_km'] ?? 0);
                $fuelCosts[] = (float) $fStmt->fetchColumn();
                $expenses[] = (float) $eStmt->fetchColumn();
            } else {
                $tripCounts[] = 0;
                $distances[] = 0;
                $fuelCosts[] = 0;
                $expenses[] = 0;
            }
        }

        // -- Approval Queue (latest 10 pending items) --
        $pendingItems = [];
        if ($isSa) {
            $vrPending = $db->query(
                "SELECT 'request' as source, vr.id, vr.request_number as code, vr.destination as info,
                        vr.created_at, u.name as requester, c.name as customer
                 FROM mova_vehicle_requests vr
                 JOIN mova_users u ON u.id = vr.requested_by
                 JOIN mova_customers c ON c.id = vr.customer_id
                 WHERE vr.status = 'pending'
                 ORDER BY vr.created_at ASC LIMIT 10"
            )->fetchAll();
            $flPending = $db->query(
                "SELECT 'fuel' as source, fr.id, fr.fuel_type as code, v.plate_number as info,
                        fr.created_at, u.name as requester, c.name as customer
                 FROM mova_fuel_reports fr
                 JOIN mova_vehicles v ON v.id = fr.vehicle_id
                 JOIN mova_users u ON u.id = fr.reported_by
                 JOIN mova_customers c ON c.id = fr.customer_id
                 WHERE fr.status = 'pending'
                 ORDER BY fr.created_at ASC LIMIT 10"
            )->fetchAll();
            $exPending = $db->query(
                "SELECT 'expense' as source, er.id, er.category as code, v.plate_number as info,
                        er.created_at, u.name as requester, c.name as customer
                 FROM mova_expense_reports er
                 JOIN mova_vehicles v ON v.id = er.vehicle_id
                 JOIN mova_users u ON u.id = er.reported_by
                 JOIN mova_customers c ON c.id = er.customer_id
                 WHERE er.status = 'pending'
                 ORDER BY er.created_at ASC LIMIT 10"
            )->fetchAll();
        } elseif ($hasCust) {
            $ph = implode(',', array_fill(0, count($cIds), '?'));
            $vrStmt = $db->prepare(
                "SELECT 'request' as source, vr.id, vr.request_number as code, vr.destination as info,
                        vr.created_at, u.name as requester, NULL as customer
                 FROM mova_vehicle_requests vr
                 JOIN mova_users u ON u.id = vr.requested_by
                 WHERE vr.customer_id IN ($ph) AND vr.status = 'pending'
                 ORDER BY vr.created_at ASC LIMIT 10"
            );
            $vrStmt->execute($cIds); $vrPending = $vrStmt->fetchAll();

            $flStmt = $db->prepare(
                "SELECT 'fuel' as source, fr.id, fr.fuel_type as code, v.plate_number as info,
                        fr.created_at, u.name as requester, NULL as customer
                 FROM mova_fuel_reports fr
                 JOIN mova_vehicles v ON v.id = fr.vehicle_id
                 JOIN mova_users u ON u.id = fr.reported_by
                 WHERE fr.customer_id IN ($ph) AND fr.status = 'pending'
                 ORDER BY fr.created_at ASC LIMIT 10"
            );
            $flStmt->execute($cIds); $flPending = $flStmt->fetchAll();

            $exStmt = $db->prepare(
                "SELECT 'expense' as source, er.id, er.category as code, v.plate_number as info,
                        er.created_at, u.name as requester, NULL as customer
                 FROM mova_expense_reports er
                 JOIN mova_vehicles v ON v.id = er.vehicle_id
                 JOIN mova_users u ON u.id = er.reported_by
                 WHERE er.customer_id IN ($ph) AND er.status = 'pending'
                 ORDER BY er.created_at ASC LIMIT 10"
            );
            $exStmt->execute($cIds); $exPending = $exStmt->fetchAll();
        } else {
            $vrPending = $flPending = $exPending = [];
        }

        // Merge & sort by created_at ASC
        $allPending = array_merge($vrPending, $flPending, $exPending);
        usort($allPending, fn($a, $b) => strtotime($a['created_at']) - strtotime($b['created_at']));
        $pendingItems = array_slice($allPending, 0, 10);

        ob_start();
        ?>
        <!-- Master Data Stats -->
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

        <!-- Operational Stats -->
        <div class="stats">
            <div class="stat-card">
                <span class="stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg></span>
                <div class="stat-label">Request Pending</div>
                <div class="stat-value"><?= $opPendingReqs ?></div>
                <a href="/customer/requests" class="stat-link">Lihat →</a>
            </div>
            <div class="stat-card">
                <span class="stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></span>
                <div class="stat-label">Trip Aktif</div>
                <div class="stat-value"><?= $opActiveTrips ?></div>
                <a href="/customer/trips" class="stat-link">Lihat →</a>
            </div>
            <div class="stat-card">
                <span class="stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg></span>
                <div class="stat-label">Issue Terbuka</div>
                <div class="stat-value"><?= $opOpenIssues ?></div>
                <a href="/customer/issues" class="stat-link">Lihat →</a>
            </div>
            <div class="stat-card">
                <span class="stat-icon"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg></span>
                <div class="stat-label">Pending Approval</div>
                <div class="stat-value"><?= $opPendingFuel + $opPendingExp ?></div>
                <a href="/customer/fuel" class="stat-link">Lihat →</a>
            </div>
        </div>

        <!-- Fleet Utilization + Driver Activity -->
        <div class="grid-2">
            <div class="card">
                <div class="card-header"><h3>Utilisasi Armada</h3></div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;text-align:center">
                        <div style="padding:12px;background:var(--surface-2);border-radius:var(--radius-card)">
                            <div style="font-size:1.6rem;font-weight:700;color:var(--success)"><?= $fleetAvailable ?></div>
                            <div style="font-size:0.72rem;color:var(--text-2);font-weight:500;margin-top:2px">Tersedia</div>
                        </div>
                        <div style="padding:12px;background:var(--surface-2);border-radius:var(--radius-card)">
                            <div style="font-size:1.6rem;font-weight:700;color:var(--info)"><?= $fleetOnTrip ?></div>
                            <div style="font-size:0.72rem;color:var(--text-2);font-weight:500;margin-top:2px">Di Trip</div>
                        </div>
                        <div style="padding:12px;background:var(--surface-2);border-radius:var(--radius-card)">
                            <div style="font-size:1.6rem;font-weight:700;color:var(--warning)"><?= $fleetMaintenance ?></div>
                            <div style="font-size:0.72rem;color:var(--text-2);font-weight:500;margin-top:2px">Maintenance</div>
                        </div>
                    </div>
                    <div style="margin-top:12px;padding:10px 14px;background:var(--surface-2);border-radius:var(--radius-card)">
                        <div style="display:flex;justify-content:space-between;font-size:0.82rem">
                            <span style="color:var(--text-2)">Total Armada</span>
                            <span style="font-weight:600"><?= $fleetTotal ?></span>
                        </div>
                        <?php if ($fleetTotal > 0): ?>
                        <div style="margin-top:8px;height:6px;background:var(--border);border-radius:999px;overflow:hidden;display:flex">
                            <?php $aPct = round($fleetAvailable / $fleetTotal * 100); $tPct = round($fleetOnTrip / $fleetTotal * 100); $mPct = 100 - $aPct - $tPct; ?>
                            <div style="height:100%;width:<?= $aPct ?>%;background:var(--success);transition:width 0.3s"></div>
                            <div style="height:100%;width:<?= $tPct ?>%;background:var(--info);transition:width 0.3s"></div>
                            <div style="height:100%;width:<?= $mPct ?>%;background:var(--warning);transition:width 0.3s"></div>
                        </div>
                        <div style="display:flex;gap:12px;margin-top:6px;font-size:0.68rem;color:var(--text-muted)">
                            <span>■ Tersedia <?= $aPct ?>%</span>
                            <span>■ Di Trip <?= $tPct ?>%</span>
                            <span>■ Maint. <?= $mPct ?>%</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h3>Aktivitas Driver</h3></div>
                <div class="card-body">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;text-align:center">
                        <div style="padding:16px;background:var(--surface-2);border-radius:var(--radius-card)">
                            <div style="font-size:1.6rem;font-weight:700;color:var(--success)"><?= $driverAvailable ?></div>
                            <div style="font-size:0.72rem;color:var(--text-2);font-weight:500;margin-top:2px">Tersedia</div>
                        </div>
                        <div style="padding:16px;background:var(--surface-2);border-radius:var(--radius-card)">
                            <div style="font-size:1.6rem;font-weight:700;color:var(--info)"><?= $driverOnTrip ?></div>
                            <div style="font-size:0.72rem;color:var(--text-2);font-weight:500;margin-top:2px">Sedang Bertugas</div>
                        </div>
                    </div>
                    <div style="margin-top:12px;padding:10px 14px;background:var(--surface-2);border-radius:var(--radius-card)">
                        <div style="display:flex;justify-content:space-between;font-size:0.82rem">
                            <span style="color:var(--text-2)">Total Driver</span>
                            <span style="font-weight:600"><?= $driverTotal ?></span>
                        </div>
                        <?php if ($driverTotal > 0): ?>
                        <div style="margin-top:8px;height:6px;background:var(--border);border-radius:999px;overflow:hidden;display:flex">
                            <?php $daPct = round($driverAvailable / $driverTotal * 100); $doPct = 100 - $daPct; ?>
                            <div style="height:100%;width:<?= $daPct ?>%;background:var(--success);transition:width 0.3s"></div>
                            <div style="height:100%;width:<?= $doPct ?>%;background:var(--info);transition:width 0.3s"></div>
                        </div>
                        <div style="display:flex;gap:12px;margin-top:6px;font-size:0.68rem;color:var(--text-muted)">
                            <span>■ Tersedia <?= $daPct ?>%</span>
                            <span>■ Bertugas <?= $doPct ?>%</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
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
                <div class="card-header"><h3>Tren Bulanan</h3></div>
                <div class="card-body chart-body">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Approval Queue -->
        <div class="card">
            <div class="card-header">
                <h3>Antrian Approval</h3>
                <span style="font-size:0.78rem;color:var(--text-2)">Total <?= $opPendingFuel + $opPendingExp + $opPendingReqs ?> pending</span>
            </div>
            <div class="card-body">
                <?php if (empty($pendingItems)): ?>
                    <div class="empty-state"><p>Tidak ada item yang perlu disetujui.</p></div>
                <?php else: ?>
                <div class="table-wrap"><table>
                    <thead><tr>
                        <th>Tipe</th><th>Kode</th><th>Detail</th><th>Pengaju</th><th>Customer</th><th>Tanggal</th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($pendingItems as $pi): ?>
                        <?php
                            $srcLabel = match($pi['source']) {
                                'request' => 'Request',
                                'fuel' => 'BBM',
                                'expense' => 'Biaya',
                                default => $pi['source'],
                            };
                            $badgeClass = match($pi['source']) {
                                'request' => 'badge-warning',
                                'fuel' => 'badge-info',
                                'expense' => 'badge-active',
                                default => 'badge',
                            };
                        ?>
                        <tr>
                            <td><span class="badge <?= $badgeClass ?>"><?= $srcLabel ?></span></td>
                            <td><strong><?= e($pi['code']) ?></strong></td>
                            <td><?= e($pi['info']) ?></td>
                            <td><?= e($pi['requester']) ?></td>
                            <td><?= e($pi['customer'] ?? '-') ?></td>
                            <td><?= date('d/m/Y H:i', strtotime($pi['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Access -->
        <div class="card">
            <div class="card-header"><h3>Akses Cepat</h3></div>
            <div class="card-body">
                <div class="quick-access">
                    <?php if ($tenant->isSuperAdmin()): ?>
                    <a href="/regions/create" class="quick-access__btn">
                        <span class="quick-access__icon">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                        </span>
                        <span>+ Region Baru</span>
                    </a>
                    <a href="/branches/create" class="quick-access__btn">
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

            // Combo bar — Monthly Trends: Trip, Distance, BBM, Biaya
            <?php
            $tripMax = max($tripCounts) ?: 1;
            $distScale = $tripMax > 0 ? round(max($distances) / $tripMax, 1) : 1;
            $distScale = max($distScale, 1);
            $fuelScale = max($fuelCosts) > 0 ? round(max($fuelCosts) / $tripMax, -2) : 1;
            $fuelScale = max($fuelScale, 1);
            $expScale = max($expenses) > 0 ? round(max($expenses) / $tripMax, -2) : 1;
            $expScale = max($expScale, 1);
            $scaledDist = array_map(fn($v) => round($v / $distScale), $distances);
            $scaledFuel = array_map(fn($v) => round($v / $fuelScale), $fuelCosts);
            $scaledExp = array_map(fn($v) => round($v / $expScale), $expenses);
            ?>
            new Chart(document.getElementById('trendChart'), {
                type: 'bar',
                data: {
                    labels: <?= json_encode($monthLabels) ?>,
                    datasets: [
                        {
                            label: 'Trip',
                            data: <?= json_encode($tripCounts) ?>,
                            backgroundColor: 'rgba(15,110,86,0.20)',
                            borderColor: '#0F6E56',
                            borderWidth: 1.5,
                            borderRadius: 4,
                            borderSkipped: false,
                            order: 3,
                        },
                        {
                            label: 'Jarak (km)',
                            data: <?= json_encode($scaledDist) ?>,
                            backgroundColor: 'rgba(37,99,235,0.18)',
                            borderColor: '#2563EB',
                            borderWidth: 1.5,
                            borderRadius: 4,
                            borderSkipped: false,
                            order: 2,
                        },
                        {
                            label: 'BBM (Rp)',
                            data: <?= json_encode($scaledFuel) ?>,
                            backgroundColor: 'rgba(217,119,6,0.18)',
                            borderColor: '#D97706',
                            borderWidth: 1.5,
                            borderRadius: 4,
                            borderSkipped: false,
                            order: 1,
                        },
                        {
                            label: 'Biaya (Rp)',
                            data: <?= json_encode($scaledExp) ?>,
                            backgroundColor: 'rgba(124,58,237,0.18)',
                            borderColor: '#7C3AED',
                            borderWidth: 1.5,
                            borderRadius: 4,
                            borderSkipped: false,
                            order: 0,
                        },
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: { position: 'bottom', labels: { padding: 12, usePointStyle: true, boxWidth: 12, font: { size: 10 } } },
                        tooltip: {
                            backgroundColor: '#0F172A', padding: 12, cornerRadius: 8,
                            callbacks: {
                                label: function(ctx) {
                                    const datasets = ctx.chart.data.datasets;
                                    const origValues = [<?= implode(',', $tripCounts) ?>];
                                    const origDist = [<?= implode(',', $distances) ?>];
                                    const origFuel = [<?= implode(',', $fuelCosts) ?>];
                                    const origExp = [<?= implode(',', $expenses) ?>];
                                    const allOrig = [origValues, origDist, origFuel, origExp];
                                    const idx = ctx.dataIndex;
                                    const dsIdx = ctx.datasetIndex;
                                    const val = allOrig[dsIdx]?.[idx] ?? ctx.parsed.y;
                                    if (dsIdx === 0) return 'Trip: ' + val;
                                    if (dsIdx === 1) return 'Jarak: ' + Number(val).toLocaleString('id-ID') + ' km';
                                    return ctx.dataset.label + ': Rp ' + Number(val).toLocaleString('id-ID');
                                }
                            }
                        }
                    },
                    scales: {
                        x: { grid: { display: false }, ticks: { font: { size: 10 } } },
                        y: { beginAtZero: true, grid: { color: '#E2E8F0' }, border: { display: false }, ticks: { display: false } }
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
