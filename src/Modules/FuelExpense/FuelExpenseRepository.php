<?php

class FuelExpenseRepository extends BaseRepository
{
    protected string $table = 'mova_fuel_reports';

    public function findFuelReports(array $filters = []): array
    {
        $sql = "SELECT fr.*, v.plate_number, u.name AS reported_by_name
                FROM mova_fuel_reports fr
                JOIN mova_vehicles v ON v.id = fr.vehicle_id
                JOIN mova_users u ON u.id = fr.reported_by
                WHERE 1=1";
        $params = [];

        if (!empty($filters['customer_id'])) {
            $sql .= " AND fr.customer_id = ?";
            $params[] = $filters['customer_id'];
        } elseif (!$this->tenant->isSuperAdmin()) {
            $ids = $this->tenant->getAccessibleCustomerIds();
            if (empty($ids)) return [];
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $sql .= " AND fr.customer_id IN ($ph)";
            $params = array_merge($params, $ids);
        }

        if (!empty($filters['status'])) {
            $sql .= " AND fr.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['trip_id'])) {
            $sql .= " AND fr.trip_id = ?";
            $params[] = $filters['trip_id'];
        }

        if (!empty($filters['date_start'])) {
            $sql .= " AND fr.fuel_date >= ?";
            $params[] = $filters['date_start'];
        }
        if (!empty($filters['date_end'])) {
            $sql .= " AND fr.fuel_date <= ?";
            $params[] = $filters['date_end'];
        }

        $sql .= " ORDER BY fr.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findFuelReport(int $id): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT fr.*, v.plate_number, u.name AS reported_by_name
             FROM mova_fuel_reports fr
             JOIN mova_vehicles v ON v.id = fr.vehicle_id
             JOIN mova_users u ON u.id = fr.reported_by
             WHERE fr.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function createFuelReport(array $data): int
    {
        $totalCost = $data['liters'] * $data['price_per_liter'];
        $stmt = $this->db->prepare(
            "INSERT INTO mova_fuel_reports (customer_id, trip_id, vehicle_id, reported_by,
             fuel_date, fuel_type, liters, price_per_liter, total_cost, km_at_refuel,
             station_name, receipt_photo, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['customer_id'],
            $data['trip_id'],
            $data['vehicle_id'],
            $data['reported_by'],
            $data['fuel_date'],
            $data['fuel_type'],
            $data['liters'],
            $data['price_per_liter'],
            $totalCost,
            $data['km_at_refuel'] ?? null,
            $data['station_name'] ?? null,
            $data['receipt_photo'] ?? null,
            $data['notes'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function approveFuelReport(int $id, int $approverId): int
    {
        return $this->scopedUpdate(
            ['status' => 'approved', 'approved_by' => $approverId, 'approved_at' => date('Y-m-d H:i:s')],
            'id = ? AND status = ?', [$id, 'pending']
        );
    }

    public function rejectFuelReport(int $id, int $approverId, string $reason): int
    {
        return $this->scopedUpdate(
            ['status' => 'rejected', 'approved_by' => $approverId,
             'approved_at' => date('Y-m-d H:i:s'), 'rejection_reason' => $reason],
            'id = ? AND status = ?', [$id, 'pending']
        );
    }

    public function findExpenseReports(array $filters = []): array
    {
        $sql = "SELECT er.*, v.plate_number, u.name AS reported_by_name
                FROM mova_expense_reports er
                JOIN mova_vehicles v ON v.id = er.vehicle_id
                JOIN mova_users u ON u.id = er.reported_by
                WHERE 1=1";
        $params = [];

        if (!empty($filters['customer_id'])) {
            $sql .= " AND er.customer_id = ?";
            $params[] = $filters['customer_id'];
        } elseif (!$this->tenant->isSuperAdmin()) {
            $ids = $this->tenant->getAccessibleCustomerIds();
            if (empty($ids)) return [];
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $sql .= " AND er.customer_id IN ($ph)";
            $params = array_merge($params, $ids);
        }

        if (!empty($filters['status'])) {
            $sql .= " AND er.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['date_start'])) {
            $sql .= " AND er.expense_date >= ?";
            $params[] = $filters['date_start'];
        }
        if (!empty($filters['date_end'])) {
            $sql .= " AND er.expense_date <= ?";
            $params[] = $filters['date_end'];
        }

        $sql .= " ORDER BY er.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findExpenseReport(int $id): array|false
    {
        $stmt = $this->db->prepare(
            "SELECT er.*, v.plate_number, u.name AS reported_by_name
             FROM mova_expense_reports er
             JOIN mova_vehicles v ON v.id = er.vehicle_id
             JOIN mova_users u ON u.id = er.reported_by
             WHERE er.id = ?"
        );
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public function createExpenseReport(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO mova_expense_reports (customer_id, trip_id, vehicle_id, reported_by,
             expense_date, category, description, amount, receipt_photo)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['customer_id'],
            $data['trip_id'],
            $data['vehicle_id'],
            $data['reported_by'],
            $data['expense_date'],
            $data['category'],
            $data['description'],
            $data['amount'],
            $data['receipt_photo'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function approveExpenseReport(int $id, int $approverId): int
    {
        $sql = "UPDATE mova_expense_reports SET status = 'approved', approved_by = ?, approved_at = NOW()
                WHERE id = ? AND status = 'pending'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$approverId, $id]);
        return $stmt->rowCount();
    }

    public function rejectExpenseReport(int $id, int $approverId, string $reason): int
    {
        $sql = "UPDATE mova_expense_reports SET status = 'rejected', approved_by = ?,
                approved_at = NOW(), rejection_reason = ?
                WHERE id = ? AND status = 'pending'";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$approverId, $reason, $id]);
        return $stmt->rowCount();
    }
}
