<?php

class TripRepository extends BaseRepository
{
    protected string $table = 'mova_trips';

    public function findWithRelations(): array
    {
        $sql = "SELECT t.*,
                v.plate_number,
                v.brand AS vehicle_brand,
                v.model AS vehicle_model,
                driver.name AS driver_name,
                inputter.name AS input_by_name
                FROM mova_trips t
                JOIN mova_vehicles v ON v.id = t.vehicle_id
                JOIN mova_users driver ON driver.id = t.driver_id
                JOIN mova_users inputter ON inputter.id = t.input_by";

        if ($this->tenant->isSuperAdmin()) {
            $stmt = $this->db->prepare($sql . " ORDER BY t.created_at DESC");
            $stmt->execute();
        } else {
            $ids = $this->tenant->getAccessibleCustomerIds();
            if (empty($ids)) return [];
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $sql .= " WHERE t.customer_id IN ($ph) ORDER BY t.created_at DESC";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($ids);
        }
        return $stmt->fetchAll();
    }

    public function findActiveTrips(): array
    {
        $sql = "SELECT t.*, v.plate_number, driver.name AS driver_name
                FROM mova_trips t
                JOIN mova_vehicles v ON v.id = t.vehicle_id
                JOIN mova_users driver ON driver.id = t.driver_id
                WHERE t.status = 'in_progress'";

        if (!$this->tenant->isSuperAdmin()) {
            $ids = $this->tenant->getAccessibleCustomerIds();
            if (empty($ids)) return [];
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $sql .= " AND t.customer_id IN ($ph)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($ids);
        } else {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
        }
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        return $this->scopedInsert([
            'customer_id' => $data['customer_id'],
            'vehicle_request_id' => $data['vehicle_request_id'] ?? null,
            'vehicle_id' => $data['vehicle_id'],
            'driver_id' => $data['driver_id'],
            'trip_number' => $data['trip_number'],
            'origin' => $data['origin'],
            'destination' => $data['destination'],
            'trip_date' => $data['trip_date'],
            'departure_time' => $data['departure_time'] ?? null,
            'km_start' => $data['km_start'] ?? null,
            'purpose_type' => $data['purpose_type'],
            'notes' => $data['notes'] ?? null,
            'status' => $data['status'] ?? 'draft',
            'input_by' => $data['input_by'],
        ]);
    }

    public function startTrip(int $id, int $kmStart, string $departureTime): int
    {
        return $this->scopedUpdate(
            ['status' => 'in_progress', 'km_start' => $kmStart, 'departure_time' => $departureTime],
            'id = ? AND status = ?', [$id, 'draft']
        );
    }

    public function completeTrip(int $id, int $kmEnd, int $distance, ?string $returnTime): int
    {
        return $this->scopedUpdate(
            ['status' => 'completed', 'km_end' => $kmEnd, 'distance_km' => $distance,
             'return_time' => $returnTime],
            'id = ? AND status = ?', [$id, 'in_progress']
        );
    }
}
