<?php

class RegionRepository extends BaseRepository
{
    protected string $table = 'mova_regions';
    protected bool $hasCustomerId = false;

    public function findByCode(string $code): ?array
    {
        $rows = $this->scopedSelect('code = ?', [$code]);
        return $rows[0] ?? null;
    }

    public function findActive(): array
    {
        return $this->scopedSelect('is_active = 1 ORDER BY name ASC');
    }

    public function create(array $data): int
    {
        return $this->scopedInsert([
            'name' => $data['name'],
            'code' => $data['code'],
            'is_active' => $data['is_active'] ?? 1,
        ]);
    }

    public function update(int $id, array $data): int
    {
        return $this->scopedUpdate(
            [
                'name' => $data['name'],
                'code' => $data['code'],
                'is_active' => $data['is_active'] ?? 1,
            ],
            'id = ?',
            [$id]
        );
    }

    public function delete(int $id): int
    {
        return $this->scopedDelete('id = ?', [$id]);
    }
}
