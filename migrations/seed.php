<?php
/**
 * Seed data awal untuk testing MOVA
 * Jalankan SETELAH migration: php migrations/seed.php
 */

require_once __DIR__ . '/../config/database.php';

$db = Database::getConnection();

echo "=== MOVA Seed Data ===\n\n";

// ---- Region ----
$regionId = insertOrGetId($db, 'mova_regions', 'code', 'SULSEL', [
    'name' => 'Sulawesi Selatan',
    'code' => 'SULSEL',
    'is_active' => 1,
]);
echo "[OK] Region: Sulawesi Selatan (ID: $regionId)\n";

// ---- Branch ----
$branchId = insertOrGetId($db, 'mova_branches', 'code', 'MKS', [
    'region_id' => $regionId,
    'name' => 'Makassar',
    'code' => 'MKS',
    'address' => 'Jl. Sultan Hasanuddin No. 10, Makassar',
    'phone' => '0411-123456',
    'is_active' => 1,
]);
echo "[OK] Branch: Makassar (ID: $branchId)\n";

// ---- Customer ----
$customerId = insertOrGetId($db, 'mova_customers', 'code', 'DEMO01', [
    'branch_id' => $branchId,
    'subscription_plan_id' => 1, // free plan
    'name' => 'PT. Transportasi Demo',
    'code' => 'DEMO01',
    'pic_name' => 'Budi Santoso',
    'pic_phone' => '081234567890',
    'pic_email' => 'budi@demo.com',
    'contract_start' => '2026-01-01',
    'contract_end' => '2026-12-31',
    'total_units' => 5,
    'is_active' => 1,
]);
echo "[OK] Customer: PT. Transportasi Demo (ID: $customerId)\n";

// ---- Customer Config ----
$stmt = $db->prepare("INSERT IGNORE INTO mova_customer_configs (customer_id, enable_supervisor_approval) VALUES (?, 0)");
$stmt->execute([$customerId]);
echo "[OK] Customer Config created\n";

// ---- Users ----
$users = [
    'Super Admin' => [
        'role_id' => getRoleId($db, 'super_admin'),
        'customer_id' => null,
        'name' => 'Administrator',
        'email' => 'admin@mova.com',
        'password' => 'admin123',
        'phone' => '081111111111',
        'is_active' => 1,
    ],
    'Manager' => [
        'role_id' => getRoleId($db, 'manager'),
        'customer_id' => $customerId,
        'name' => 'Andi Manager',
        'email' => 'manager@demo.com',
        'password' => 'demo123',
        'phone' => '082222222222',
        'is_active' => 1,
    ],
    'Koordinator' => [
        'role_id' => getRoleId($db, 'koordinator'),
        'customer_id' => $customerId,
        'name' => 'Cici Koordinator',
        'email' => 'koord@demo.com',
        'password' => 'demo123',
        'phone' => '083333333333',
        'is_active' => 1,
    ],
    'Driver' => [
        'role_id' => getRoleId($db, 'driver'),
        'customer_id' => $customerId,
        'name' => 'Dodi Driver',
        'email' => 'driver@demo.com',
        'password' => 'demo123',
        'phone' => '084444444444',
        'is_active' => 1,
    ],
    'Operation' => [
        'role_id' => getRoleId($db, 'operation'),
        'customer_id' => null,
        'name' => 'Opi Operation',
        'email' => 'operation@mova.com',
        'password' => 'demo123',
        'phone' => '085555555555',
        'is_active' => 1,
    ],
    'Marketing' => [
        'role_id' => getRoleId($db, 'marketing'),
        'customer_id' => null,
        'name' => 'Mark Marketing',
        'email' => 'marketing@mova.com',
        'password' => 'demo123',
        'phone' => '086666666666',
        'is_active' => 1,
    ],
];

foreach ($users as $label => $data) {
    $existing = $db->prepare("SELECT id FROM mova_users WHERE email = ?");
    $existing->execute([$data['email']]);
    if ($existing->fetch()) {
        echo "[SKIP] User $label already exists\n";
        continue;
    }

    $stmt = $db->prepare(
        "INSERT INTO mova_users (role_id, customer_id, name, email, password, phone, is_active) VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $data['role_id'],
        $data['customer_id'],
        $data['name'],
        $data['email'],
        password_hash($data['password'], PASSWORD_ARGON2ID),
        $data['phone'],
        $data['is_active'],
    ]);
    $userId = $db->lastInsertId();
    echo "[OK] User: $label (ID: $userId) — {$data['email']} / {$data['password']}\n";
}

// ---- User Branch Access (company users only) ----
$companyUserEmails = ['admin@mova.com', 'operation@mova.com', 'marketing@mova.com'];
foreach ($companyUserEmails as $email) {
    $stmt = $db->prepare("SELECT id FROM mova_users WHERE email = ?");
    $stmt->execute([$email]);
    $userId = $stmt->fetchColumn();
    if (!$userId) continue;

    $existing = $db->prepare("SELECT id FROM mova_user_branch_access WHERE user_id = ? AND branch_id = ?");
    $existing->execute([$userId, $branchId]);
    if (!$existing->fetch()) {
        $stmt2 = $db->prepare("INSERT INTO mova_user_branch_access (user_id, branch_id) VALUES (?, ?)");
        $stmt2->execute([$userId, $branchId]);
        echo "[OK] Branch access: $email → branch ID $branchId\n";
    }
}

// ---- Vehicle ----
$vehiclePlate = 'B 1234 XX';
$existing = $db->prepare("SELECT id FROM mova_vehicles WHERE plate_number = ?");
$existing->execute([$vehiclePlate]);
if (!$existing->fetch()) {
    $stmt = $db->prepare(
        "INSERT INTO mova_vehicles (customer_id, plate_number, brand, model, year, color, vehicle_type, current_km, status, is_active)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'ready', 1)"
    );
    $stmt->execute([
        $customerId,
        $vehiclePlate,
        'Mitsubishi',
        'L300',
        2022,
        'Putih',
        'pickup',
        45000,
    ]);
    $vehicleId = $db->lastInsertId();
    echo "[OK] Vehicle: $vehiclePlate (ID: $vehicleId)\n";
} else {
    echo "[SKIP] Vehicle $vehiclePlate already exists\n";
}

echo "\n=== Selesai! ===\n";
echo "\nAkun login:\n";
echo "  Super Admin : admin@mova.com / admin123\n";
echo "  Manager     : manager@demo.com / demo123 (Customer Panel)\n";
echo "  Koordinator : koord@demo.com / demo123 (Customer Panel)\n";
echo "  Driver      : driver@demo.com / demo123 (Customer Panel)\n";
echo "  Operation   : operation@mova.com / demo123\n";
echo "  Marketing   : marketing@mova.com / demo123\n";

// ---- Helper Functions ----

function insertOrGetId(PDO $db, string $table, string $uniqueCol, string $uniqueVal, array $data): int
{
    $stmt = $db->prepare("SELECT id FROM `$table` WHERE `$uniqueCol` = ?");
    $stmt->execute([$uniqueVal]);
    $row = $stmt->fetch();
    if ($row) {
        return (int) $row['id'];
    }

    $columns = implode('`, `', array_keys($data));
    $placeholders = implode(', ', array_fill(0, count($data), '?'));
    $stmt = $db->prepare("INSERT INTO `$table` (`$columns`) VALUES ($placeholders)");
    $stmt->execute(array_values($data));
    return (int) $db->lastInsertId();
}

function getRoleId(PDO $db, string $roleName): int
{
    static $cache = [];
    if (!isset($cache[$roleName])) {
        $stmt = $db->prepare("SELECT id FROM mova_roles WHERE name = ?");
        $stmt->execute([$roleName]);
        $cache[$roleName] = (int) $stmt->fetchColumn();
    }
    return $cache[$roleName];
}
