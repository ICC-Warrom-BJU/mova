<?php

class VehicleRepository extends BaseRepository
{
    protected string $table = 'mova_vehicles';

    public function findActive(): array
    {
        return $this->scopedSelect('is_active = 1 ORDER BY plate_number ASC');
    }

    public function findByCustomer(int $customerId): array
    {
        $this->requireOwnershipOrScope($customerId);

        $stmt = $this->db->prepare(
            "SELECT * FROM mova_vehicles WHERE customer_id = ? AND is_active = 1 ORDER BY plate_number"
        );
        $stmt->execute([$customerId]);
        return $stmt->fetchAll();
    }

    public function findWithCustomer(?string $search = null): array
    {
        $sql = "SELECT v.*, c.name AS customer_name
                FROM mova_vehicles v
                JOIN mova_customers c ON c.id = v.customer_id";

        $params = [];
        $conditions = [];

        if (!$this->tenant->isSuperAdmin()) {
            $ids = $this->tenant->getAccessibleCustomerIds();
            if (empty($ids)) {
                return [];
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $conditions[] = "v.customer_id IN ($placeholders)";
            $params = $ids;
        }

        if ($search !== null && $search !== '') {
            $keyword = '%' . $search . '%';
            $conditions[] = "(v.plate_number LIKE ? OR v.brand LIKE ? OR v.model LIKE ? OR c.name LIKE ?)";
            $params = array_merge($params, [$keyword, $keyword, $keyword, $keyword]);
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY c.name, v.plate_number";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        return $this->scopedInsert([
            'customer_id'  => $data['customer_id'],
            'plate_number' => $data['plate_number'],
            'brand'        => $data['brand'],
            'model'        => $data['model'],
            'year'         => $data['year'] ?? null,
            'color'        => $data['color'] ?? null,
            'vehicle_type' => $data['vehicle_type'] ?? null,
            'current_km'   => $data['current_km'] ?? 0,
            'status'       => $data['status'] ?? 'active',
            'stnk_expiry'  => $data['stnk_expiry'] ?? null,
            'stnk_photo'   => $data['stnk_photo'] ?? null,
            'kir_expiry'   => $data['kir_expiry'] ?? null,
            'kir_photo'    => $data['kir_photo'] ?? null,
            'is_active'    => $data['is_active'] ?? 1,
        ]);
    }

    public function update(int $id, array $data): int
    {
        return $this->scopedUpdate(
            [
                'plate_number' => $data['plate_number'],
                'brand'        => $data['brand'],
                'model'        => $data['model'],
                'year'         => $data['year'] ?? null,
                'color'        => $data['color'] ?? null,
                'vehicle_type' => $data['vehicle_type'] ?? null,
                'current_km'   => $data['current_km'] ?? 0,
                'status'       => $data['status'] ?? 'active',
                'stnk_expiry'  => $data['stnk_expiry'] ?? null,
                'stnk_photo'   => $data['stnk_photo'] ?? null,
                'kir_expiry'   => $data['kir_expiry'] ?? null,
                'kir_photo'    => $data['kir_photo'] ?? null,
                'is_active'    => $data['is_active'] ?? 1,
            ],
            'id = ?',
            [$id]
        );
    }

    private function requireOwnershipOrScope(int $customerId): void
    {
        $accessible = $this->tenant->getAccessibleCustomerIds();
        if (!$this->tenant->isSuperAdmin() && !in_array($customerId, $accessible, true)) {
            throw new RuntimeException('Forbidden: di luar scope akses Anda');
        }
    }
}
