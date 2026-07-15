<?php

class MaintenanceRepository extends BaseRepository
{
    protected string $table = 'mova_maintenance_schedules';

    public function findSchedulesWithRelations(): array
    {
        $sql = "SELECT ms.*, v.plate_number, v.brand, v.model,
                u.name AS created_by_name
                FROM mova_maintenance_schedules ms
                JOIN mova_vehicles v ON v.id = ms.vehicle_id
                JOIN mova_users u ON u.id = ms.created_by";

        if ($this->tenant->isSuperAdmin()) {
            $stmt = $this->db->prepare($sql . " ORDER BY ms.created_at DESC");
            $stmt->execute();
        } else {
            $ids = $this->tenant->getAccessibleCustomerIds();
            if (empty($ids)) return [];
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $sql .= " WHERE ms.customer_id IN ($ph) ORDER BY ms.created_at DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($ids);
        }
        return $stmt->fetchAll();
    }

    public function findOverdue(): array
    {
        $sql = "SELECT ms.*, v.plate_number
                FROM mova_maintenance_schedules ms
                JOIN mova_vehicles v ON v.id = ms.vehicle_id
                WHERE ms.status IN ('scheduled', 'overdue')
                AND ((ms.trigger_type = 'date_based' AND ms.scheduled_date <= CURDATE())
                     OR (ms.trigger_type = 'km_based' AND v.current_km >= ms.km_threshold))";

        if (!$this->tenant->isSuperAdmin()) {
            $ids = $this->tenant->getAccessibleCustomerIds();
            if (empty($ids)) return [];
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $sql .= " AND ms.customer_id IN ($ph)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($ids);
        } else {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
        }
        return $stmt->fetchAll();
    }

    public function createSchedule(array $data): int
    {
        // Insert langsung (bukan scopedInsert) — customer_id sudah ditentukan
        // controller (dari kendaraan). scopedInsert akan menimpanya jadi null
        // untuk user company non-super (mis. Operation).
        $stmt = $this->db->prepare(
            "INSERT INTO mova_maintenance_schedules
             (customer_id, vehicle_id, service_type, trigger_type, km_threshold,
              scheduled_date, reminder_days_before, status, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, 'scheduled', ?, ?)"
        );
        $stmt->execute([
            $data['customer_id'],
            $data['vehicle_id'],
            $data['service_type'],
            $data['trigger_type'],
            $data['km_threshold'] ?? null,
            $data['scheduled_date'] ?? null,
            $data['reminder_days_before'] ?? 7,
            $data['notes'] ?? null,
            $data['created_by'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateScheduleStatus(int $id, string $status): int
    {
        return $this->scopedUpdate(
            ['status' => $status],
            'id = ?', [$id]
        );
    }

    public function findLogsByVehicle(int $vehicleId): array
    {
        $stmt = $this->db->prepare(
            "SELECT ml.*, u.name AS logged_by_name
             FROM mova_maintenance_logs ml
             JOIN mova_users u ON u.id = ml.logged_by
             WHERE ml.vehicle_id = ?
             ORDER BY ml.service_date DESC"
        );
        $stmt->execute([$vehicleId]);
        return $stmt->fetchAll();
    }

    public function createLog(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO mova_maintenance_logs (schedule_id, customer_id, vehicle_id,
             service_type, service_date, km_at_service, workshop_name, cost, notes,
             next_service_km, next_service_date, logged_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['schedule_id'] ?? null,
            $data['customer_id'],
            $data['vehicle_id'],
            $data['service_type'],
            $data['service_date'],
            $data['km_at_service'] ?? null,
            $data['workshop_name'] ?? null,
            $data['cost'] ?? null,
            $data['notes'] ?? null,
            $data['next_service_km'] ?? null,
            $data['next_service_date'] ?? null,
            $data['logged_by'],
        ]);
        return (int) $this->db->lastInsertId();
    }
}
