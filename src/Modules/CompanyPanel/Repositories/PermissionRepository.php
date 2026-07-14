<?php

class PermissionRepository extends BaseRepository
{
    protected string $table = 'mova_role_modules';
    protected bool $hasCustomerId = false;

    public const MODULES = [
        // Company layer
        ['key' => 'dashboard', 'name' => 'Dashboard', 'layer' => 'company', 'group' => 'Overview'],
        ['key' => 'region', 'name' => 'Region', 'layer' => 'company', 'group' => 'Master Data'],
        ['key' => 'branch', 'name' => 'Branch', 'layer' => 'company', 'group' => 'Master Data'],
        ['key' => 'customer', 'name' => 'Customer', 'layer' => 'company', 'group' => 'Master Data'],
        ['key' => 'vehicle', 'name' => 'Vehicle', 'layer' => 'company', 'group' => 'Master Data'],
        ['key' => 'user', 'name' => 'User', 'layer' => 'company', 'group' => 'Master Data'],
        ['key' => 'config', 'name' => 'Konfigurasi', 'layer' => 'company', 'group' => 'Master Data'],
        ['key' => 'permission', 'name' => 'Permission', 'layer' => 'company', 'group' => 'Master Data'],
        ['key' => 'vehicle_request', 'name' => 'Vehicle Request', 'layer' => 'company', 'group' => 'Operational'],
        ['key' => 'trip_log', 'name' => 'Trip Log', 'layer' => 'company', 'group' => 'Operational'],
        ['key' => 'issue_report', 'name' => 'Issue Report', 'layer' => 'company', 'group' => 'Operational'],
        ['key' => 'fuel_report', 'name' => 'Fuel Report', 'layer' => 'company', 'group' => 'Operational'],
        ['key' => 'expense_report', 'name' => 'Expense Report', 'layer' => 'company', 'group' => 'Operational'],
        ['key' => 'maintenance', 'name' => 'Maintenance', 'layer' => 'company', 'group' => 'Fleet'],
        ['key' => 'notifications', 'name' => 'Notifications', 'layer' => 'company', 'group' => 'Other'],
        // Customer layer
        ['key' => 'customer_dashboard', 'name' => 'Dashboard', 'layer' => 'customer', 'group' => 'Overview'],
        ['key' => 'customer_vehicle_request', 'name' => 'Vehicle Request', 'layer' => 'customer', 'group' => 'Operational'],
        ['key' => 'customer_trip_log', 'name' => 'Trip Log', 'layer' => 'customer', 'group' => 'Operational'],
        ['key' => 'customer_issue_report', 'name' => 'Issue Report', 'layer' => 'customer', 'group' => 'Operational'],
        ['key' => 'customer_fuel_report', 'name' => 'Fuel Report', 'layer' => 'customer', 'group' => 'Operational'],
        ['key' => 'customer_expense_report', 'name' => 'Expense Report', 'layer' => 'customer', 'group' => 'Operational'],
        ['key' => 'customer_maintenance', 'name' => 'Maintenance', 'layer' => 'customer', 'group' => 'Fleet'],
        ['key' => 'customer_my_vehicles', 'name' => 'My Vehicles', 'layer' => 'customer', 'group' => 'Fleet'],
    ];

    public function getAllRoles(): array
    {
        $stmt = $this->db->query("SELECT * FROM mova_roles ORDER BY FIELD(layer, 'company','customer'), id");
        return $stmt->fetchAll();
    }

    public function getModuleCatalog(): array
    {
        return self::MODULES;
    }

    public function getModulesByLayer(string $layer): array
    {
        return array_values(array_filter(self::MODULES, fn($m) => $m['layer'] === $layer));
    }

    public function getModuleGroups(string $layer): array
    {
        $modules = $this->getModulesByLayer($layer);
        $groups = [];
        foreach ($modules as $m) {
            $groups[$m['group']][] = $m;
        }
        return $groups;
    }

    public function getPermissionsMatrix(): array
    {
        $stmt = $this->db->query("
            SELECT rm.role_id, rm.module_key, rm.can_access
            FROM mova_role_modules rm
        ");
        $rows = $stmt->fetchAll();
        $matrix = [];
        foreach ($rows as $r) {
            $matrix[$r['role_id']][$r['module_key']] = (bool) $r['can_access'];
        }
        return $matrix;
    }

    public function setPermission(int $roleId, string $moduleKey, bool $canAccess): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO mova_role_modules (role_id, module_key, can_access)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE can_access = VALUES(can_access)
        ");
        $stmt->execute([$roleId, $moduleKey, $canAccess ? 1 : 0]);
    }

    public function getRolePermissions(int $roleId): array
    {
        $stmt = $this->db->prepare("SELECT module_key, can_access FROM mova_role_modules WHERE role_id = ?");
        $stmt->execute([$roleId]);
        $result = [];
        foreach ($stmt->fetchAll() as $row) {
            $result[$row['module_key']] = (bool) $row['can_access'];
        }
        return $result;
    }
}
