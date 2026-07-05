<?php
require_once __DIR__ . '/../config/database.php';
$db = Database::getConnection();
echo "=== Seed Operational Data ===\n\n";

$customerId = $db->query("SELECT id FROM mova_customers WHERE code = 'DEMO01' LIMIT 1")->fetchColumn();
if (!$customerId) { die("Customer DEMO01 not found. Run seed.php first.\n"); }

$vehicleId = $db->query("SELECT id FROM mova_vehicles WHERE plate_number = 'B 1234 XX' LIMIT 1")->fetchColumn();
$koordId = $db->query("SELECT id FROM mova_users WHERE email = 'koord@demo.com' LIMIT 1")->fetchColumn();
$driverId = $db->query("SELECT id FROM mova_users WHERE email = 'driver@demo.com' LIMIT 1")->fetchColumn();

if (!$vehicleId || !$koordId || !$driverId) {
    die("Required seed data not found. Run seed.php first.\n");
}

// 1. Vehicle Request
$reqCount = $db->query("SELECT COUNT(*) FROM mova_vehicle_requests")->fetchColumn();
if ($reqCount < 2) {
    $db->prepare("INSERT INTO mova_vehicle_requests (customer_id, request_number, requested_by, destination, purpose, departure_date, return_date, passenger_count, status, created_at) VALUES (?, 'REQ-2026-0001', ?, 'Makassar - Parepare', 'Meeting Klien', '2026-07-05', '2026-07-06', 3, 'approved', NOW())")->execute([$customerId, $koordId]);
    $db->prepare("INSERT INTO mova_vehicle_requests (customer_id, request_number, requested_by, destination, purpose, departure_date, return_date, passenger_count, status, created_at) VALUES (?, 'REQ-2026-0002', ?, 'Makassar - Maros', 'Pengantaran Material', '2026-07-03', '2026-07-03', 2, 'pending', NOW())")->execute([$customerId, $driverId]);
    echo "[OK] Vehicle requests created\n";
} else { echo "[SKIP] Vehicle requests already exist\n"; }

// 2. Trip
$tripCount = $db->query("SELECT COUNT(*) FROM mova_trips")->fetchColumn();
if ($tripCount < 2) {
    $db->prepare("INSERT INTO mova_trips (customer_id, vehicle_id, driver_id, trip_number, origin, destination, trip_date, purpose_type, km_start, km_end, distance_km, status, input_by, created_at) VALUES (?, ?, ?, 'TRP-2026-0001', 'Makassar', 'Parepare', '2026-07-05', 'dinas', 45000, 45320, 320, 'completed', ?, NOW())")->execute([$customerId, $vehicleId, $driverId, $koordId]);
    $db->prepare("INSERT INTO mova_trips (customer_id, vehicle_id, driver_id, trip_number, origin, destination, trip_date, purpose_type, status, input_by, created_at) VALUES (?, ?, ?, 'TRP-2026-0002', 'Makassar', 'Maros', '2026-07-03', 'material', 'draft', ?, NOW())")->execute([$customerId, $vehicleId, $driverId, $koordId]);
    echo "[OK] Trips created\n";
} else { echo "[SKIP] Trips already exist\n"; }

// Get trip IDs
$completedTripId = $db->query("SELECT id FROM mova_trips WHERE trip_number = 'TRP-2026-0001' LIMIT 1")->fetchColumn();
$draftTripId = $db->query("SELECT id FROM mova_trips WHERE trip_number = 'TRP-2026-0002' LIMIT 1")->fetchColumn();

// 3. Pre-trip checklist
if ($completedTripId) {
    $clCount = $db->prepare("SELECT COUNT(*) FROM mova_trip_checklists WHERE trip_id = ?");
    $clCount->execute([$completedTripId]);
    if ($clCount->fetchColumn() < 2) {
        $items = json_encode([
            ['name' => 'Ban & Tekanan Angin', 'status' => 'ok', 'note' => 'Tekanan normal 32 psi'],
            ['name' => 'Oli Mesin', 'status' => 'ok', 'note' => 'Level normal'],
            ['name' => 'Air Radiator', 'status' => 'ok', 'note' => 'Full'],
            ['name' => 'Lampu Depan/Belakang', 'status' => 'ok', 'note' => 'Semua berfungsi'],
            ['name' => 'Rem', 'status' => 'ok', 'note' => 'Baik'],
        ]);
        $db->prepare("INSERT INTO mova_trip_checklists (trip_id, check_type, submitted_by, items, overall_condition, notes) VALUES (?, 'pre_trip', ?, ?, 'good', 'Kendaraan siap jalan')")->execute([$completedTripId, $driverId, $items]);

        $items2 = json_encode([
            ['name' => 'Kondisi Kendaraan', 'status' => 'ok', 'note' => 'Baik, tidak ada kerusakan'],
            ['name' => 'Kebersihan Interior', 'status' => 'ok', 'note' => 'Bersih'],
        ]);
        $db->prepare("INSERT INTO mova_trip_checklists (trip_id, check_type, submitted_by, items, overall_condition, notes) VALUES (?, 'post_trip', ?, ?, 'good', 'Perjalanan lancar')")->execute([$completedTripId, $driverId, $items2]);
        echo "[OK] Checklists created\n";
    } else { echo "[SKIP] Checklists already exist\n"; }

    // 4. Fuel report
    $fuelCount = $db->prepare("SELECT COUNT(*) FROM mova_fuel_reports WHERE trip_id = ?");
    $fuelCount->execute([$completedTripId]);
    if ($fuelCount->fetchColumn() < 1) {
        $db->prepare("INSERT INTO mova_fuel_reports (customer_id, trip_id, vehicle_id, reported_by, fuel_date, fuel_type, liters, price_per_liter, total_cost, km_at_refuel, station_name, status) VALUES (?, ?, ?, ?, '2026-07-05', 'Solar', 20, 6800, 136000, 45100, 'SPBU Pertamina Pettarani', 'approved')")->execute([$customerId, $completedTripId, $vehicleId, $driverId]);
        $db->prepare("INSERT INTO mova_fuel_reports (customer_id, trip_id, vehicle_id, reported_by, fuel_date, fuel_type, liters, price_per_liter, total_cost, km_at_refuel, station_name, status) VALUES (?, ?, ?, ?, '2026-07-05', 'Solar', 25, 6800, 170000, 45250, 'SPBU Pertamina Parepare', 'pending')")->execute([$customerId, $completedTripId, $vehicleId, $driverId]);
        echo "[OK] Fuel reports created\n";
    } else { echo "[SKIP] Fuel reports already exist\n"; }

    // 5. Expense report
    $expCount = $db->prepare("SELECT COUNT(*) FROM mova_expense_reports WHERE trip_id = ?");
    $expCount->execute([$completedTripId]);
    if ($expCount->fetchColumn() < 1) {
        $db->prepare("INSERT INTO mova_expense_reports (customer_id, trip_id, vehicle_id, reported_by, expense_date, category, description, amount, status) VALUES (?, ?, ?, ?, '2026-07-05', 'tol', 'Tol Cambaya - Parepare', 48000, 'approved')")->execute([$customerId, $completedTripId, $vehicleId, $driverId]);
        $db->prepare("INSERT INTO mova_expense_reports (customer_id, trip_id, vehicle_id, reported_by, expense_date, category, description, amount, status) VALUES (?, ?, ?, ?, '2026-07-05', 'makan', 'Makan siang tim', 120000, 'pending')")->execute([$customerId, $completedTripId, $vehicleId, $driverId]);
        echo "[OK] Expense reports created\n";
    } else { echo "[SKIP] Expense reports already exist\n"; }

    // 6. Issue report
    $issCount = $db->prepare("SELECT COUNT(*) FROM mova_issue_reports WHERE vehicle_id = ?");
    $issCount->execute([$vehicleId]);
    if ($issCount->fetchColumn() < 1) {
        $db->prepare("INSERT INTO mova_issue_reports (customer_id, vehicle_id, report_number, reported_by, category, description, severity, status, created_at) VALUES (?, ?, 'ISS-2026-0001', ?, 'mesin', 'Suara mesin kasar saat idle, perlu diservis', 'medium', 'open', NOW())")->execute([$customerId, $vehicleId, $driverId]);
        echo "[OK] Issue reports created\n";
    } else { echo "[SKIP] Issue reports already exist\n"; }
}

// 7. Maintenance schedule
$maintCount = $db->query("SELECT COUNT(*) FROM mova_maintenance_schedules")->fetchColumn();
if ($maintCount < 1) {
    $db->prepare("INSERT INTO mova_maintenance_schedules (customer_id, vehicle_id, service_type, trigger_type, km_threshold, reminder_days_before, status, created_by, created_at) VALUES (?, ?, 'Ganti Oli Mesin', 'km_based', 50000, 7, 'active', ?, NOW())")->execute([$customerId, $vehicleId, $koordId]);
    $db->prepare("INSERT INTO mova_maintenance_schedules (customer_id, vehicle_id, service_type, trigger_type, scheduled_date, reminder_days_before, status, created_by, created_at) VALUES (?, ?, 'Servis AC', 'date_based', '2026-08-01', 14, 'active', ?, NOW())")->execute([$customerId, $vehicleId, $koordId]);
    $db->prepare("INSERT INTO mova_maintenance_schedules (customer_id, vehicle_id, service_type, trigger_type, km_threshold, reminder_days_before, status, created_by, created_at) VALUES (?, ?, 'Servis 20.000 KM', 'km_based', 70000, 7, 'active', ?, NOW())")->execute([$customerId, $vehicleId, $koordId]);
    echo "[OK] Maintenance schedules created\n";
} else { echo "[SKIP] Maintenance schedules already exist\n"; }

echo "\n=== Selesai! ===\n";
