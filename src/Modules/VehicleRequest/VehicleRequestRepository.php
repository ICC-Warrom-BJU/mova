<?php

class VehicleRequestRepository extends BaseRepository
{
    protected string $table = 'mova_vehicle_requests';

    public function findWithRelations(): array
    {
        $sql = "SELECT vr.*, 
                requester.name AS requested_by_name,
                veh.plate_number AS assigned_vehicle_plate,
                driver.name AS assigned_driver_name
                FROM mova_vehicle_requests vr
                JOIN mova_users requester ON requester.id = vr.requested_by
                LEFT JOIN mova_vehicles veh ON veh.id = vr.assigned_vehicle_id
                LEFT JOIN mova_users driver ON driver.id = vr.assigned_driver_id";

        if ($this->tenant->isSuperAdmin()) {
            $stmt = $this->db->prepare($sql . " ORDER BY vr.created_at DESC");
            $stmt->execute();
        } else {
            $ids = $this->tenant->getAccessibleCustomerIds();
            if (empty($ids)) return [];
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $sql .= " WHERE vr.customer_id IN ($ph) ORDER BY vr.created_at DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($ids);
        }
        return $stmt->fetchAll();
    }

    public function findPending(): array
    {
        $sql = "SELECT vr.*, requester.name AS requested_by_name
                FROM mova_vehicle_requests vr
                JOIN mova_users requester ON requester.id = vr.requested_by
                WHERE vr.status = 'pending'";

        if (!$this->tenant->isSuperAdmin()) {
            $ids = $this->tenant->getAccessibleCustomerIds();
            if (empty($ids)) return [];
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $sql .= " AND vr.customer_id IN ($ph)";
            $stmt = $this->db->prepare($sql . " ORDER BY vr.created_at ASC");
            $stmt->execute($ids);
        } else {
            $stmt = $this->db->prepare($sql . " ORDER BY vr.created_at ASC");
            $stmt->execute();
        }
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        return $this->scopedInsert([
            'customer_id' => $data['customer_id'],
            'request_number' => $data['request_number'],
            'requested_by' => $data['requested_by'],
            'department' => $data['department'] ?? null,
            'origin' => $data['origin'],
            'destination' => $data['destination'],
            'purpose' => $data['purpose'],
            'driver_option' => $data['driver_option'] ?? 'with_driver',
            'duration_type' => $data['duration_type'] ?? 'full_day',
            'departure_date' => $data['departure_date'],
            'return_date' => $data['return_date'],
            'start_time' => $data['start_time'] ?? null,
            'end_time' => $data['end_time'] ?? null,
            'passenger_count' => $data['passenger_count'] ?? 1,
            'vehicle_preference' => $data['vehicle_preference'] ?? null,
            'status' => 'pending',
        ]);
    }

    public function approveL1(int $id, int $approverId): int
    {
        return $this->scopedUpdate(
            ['status' => 'approved_l1', 'approved_by_l1' => $approverId, 'approved_at_l1' => date('Y-m-d H:i:s')],
            'id = ? AND status = ?', [$id, 'pending']
        );
    }

    public function approveL2(int $id, int $approverId): int
    {
        return $this->scopedUpdate(
            ['status' => 'approved', 'approved_by_l2' => $approverId, 'approved_at_l2' => date('Y-m-d H:i:s')],
            'id = ? AND status = ?', [$id, 'approved_l1']
        );
    }

    public function assign(int $id, int $vehicleId, ?int $driverId): int
    {
        // driverId NULL untuk request "without_driver" (assign kendaraan saja).
        return $this->scopedUpdate(
            ['assigned_vehicle_id' => $vehicleId, 'assigned_driver_id' => $driverId, 'status' => 'approved'],
            'id = ? AND status IN (?, ?)', [$id, 'approved', 'approved_l1']
        );
    }

    public function reject(int $id, int $rejectedBy, string $reason): int
    {
        return $this->scopedUpdate(
            ['status' => 'rejected', 'rejected_by' => $rejectedBy, 'rejection_reason' => $reason],
            'id = ? AND status = ?', [$id, 'pending']
        );
    }
}
