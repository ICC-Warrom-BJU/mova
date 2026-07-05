<?php

class BranchRepository extends BaseRepository
{
    protected string $table = 'mova_branches';
    protected bool $hasCustomerId = false;

    public function findByRegion(int $regionId): array
    {
        $rows = $this->scopedSelect('region_id = ? AND is_active = 1 ORDER BY name ASC', [$regionId]);
        return $rows;
    }

    public function findActive(): array
    {
        return $this->scopedSelect('is_active = 1 ORDER BY name ASC');
    }

    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT b.*, r.name AS region_name
                FROM mova_branches b
                JOIN mova_regions r ON r.id = b.region_id
                WHERE b.id IN ($ph) ORDER BY r.name, b.name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($ids));
        return $stmt->fetchAll();
    }

    public function findWithRegion(): array
    {
        $sql = "SELECT b.*, r.name AS region_name
                FROM mova_branches b
                JOIN mova_regions r ON r.id = b.region_id
                ORDER BY r.name, b.name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        return $this->scopedInsert([
            'region_id' => $data['region_id'],
            'name' => $data['name'],
            'code' => $data['code'],
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
        ]);
    }

    public function update(int $id, array $data): int
    {
        return $this->scopedUpdate(
            [
                'region_id' => $data['region_id'],
                'name' => $data['name'],
                'code' => $data['code'],
                'address' => $data['address'] ?? null,
                'phone' => $data['phone'] ?? null,
                'is_active' => $data['is_active'] ?? 1,
            ],
            'id = ?',
            [$id]
        );
    }
}
