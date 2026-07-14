<?php

abstract class BaseRepository
{
    protected PDO $db;
    protected TenantContext $tenant;
    protected string $table;
    protected bool $hasCustomerId = true;

    public function __construct(?PDO $db = null, ?TenantContext $tenant = null)
    {
        $this->db = $db ?? Database::getConnection();
        $this->tenant = $tenant ?? SessionMiddleware::getTenantContext();
    }

    public function find(int $id): ?array
    {
        $rows = $this->scopedSelect('id = ?', [$id]);
        return $rows[0] ?? null;
    }

    public function findAll(): array
    {
        return $this->scopedSelect();
    }

    public function findAllPaginated(int $page = 1, int $perPage = 20, string $whereClause = '', array $params = []): array
    {
        $offset = ($page - 1) * $perPage;
        $whereClause .= ($whereClause ? ' AND ' : '') . "1 = 1";

        if (!$this->tenant->isSuperAdmin() && $this->hasCustomerId) {
            $ids = $this->tenant->getAccessibleCustomerIds();
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $whereClause = "customer_id IN ($placeholders)" . ($whereClause ? " AND $whereClause" : '');
                $params = array_merge($ids, $params);
            }
        }

        $countSql = "SELECT COUNT(*) FROM {$this->table}" . ($whereClause ? " WHERE $whereClause" : '');
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM {$this->table}" . ($whereClause ? " WHERE $whereClause" : '') . " LIMIT $perPage OFFSET $offset";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    protected function scopedSelect(string $whereClause = '', array $params = []): array
    {
        if ($this->tenant->isSuperAdmin() || !$this->hasCustomerId) {
            $sql = "SELECT * FROM {$this->table}" . ($whereClause ? " WHERE $whereClause" : '');
        } else {
            $ids = $this->tenant->getAccessibleCustomerIds();
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql = "SELECT * FROM {$this->table} WHERE customer_id IN ($placeholders)"
                 . ($whereClause ? " AND $whereClause" : '');
            $params = array_merge($ids, $params);
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    protected function scopedInsert(array $data): int
    {
        if ($this->hasCustomerId && !$this->tenant->isSuperAdmin()) {
            $data['customer_id'] = $this->tenant->getCustomerId();
        }
        $columns = implode(',', array_keys($data));
        $placeholders = implode(',', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($data));
        return (int) $this->db->lastInsertId();
    }

    protected function scopedUpdate(array $data, string $whereClause = '', array $whereParams = []): int
    {
        if ($this->hasCustomerId && !$this->tenant->isSuperAdmin()) {
            $ids = $this->tenant->getAccessibleCustomerIds();
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $whereClause = "customer_id IN ($placeholders)" . ($whereClause ? " AND $whereClause" : '');
            $whereParams = array_merge($ids, $whereParams);
        }

        $sets = implode(', ', array_map(fn($col) => "$col = ?", array_keys($data)));
        $sql = "UPDATE {$this->table} SET $sets" . ($whereClause ? " WHERE $whereClause" : '');
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge(array_values($data), $whereParams));
        return $stmt->rowCount();
    }

    public function delete(int $id): int
    {
        return $this->scopedDelete('id = ?', [$id]);
    }

    /**
     * Soft delete: set is_active = 0 (tetap hormati scope tenant).
     * Dipakai untuk master data agar tidak melanggar FK RESTRICT & menjaga jejak.
     */
    public function softDelete(int $id): int
    {
        return $this->scopedUpdate(['is_active' => 0], 'id = ?', [$id]);
    }

    protected function scopedDelete(string $whereClause = '', array $whereParams = []): int
    {
        if ($this->hasCustomerId && !$this->tenant->isSuperAdmin()) {
            $ids = $this->tenant->getAccessibleCustomerIds();
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $whereClause = "customer_id IN ($placeholders)" . ($whereClause ? " AND $whereClause" : '');
            $whereParams = array_merge($ids, $whereParams);
        }

        $sql = "DELETE FROM {$this->table}" . ($whereClause ? " WHERE $whereClause" : '');
        $stmt = $this->db->prepare($sql);
        $stmt->execute($whereParams);
        return $stmt->rowCount();
    }

    public function beginTransaction(): void
    {
        $this->db->beginTransaction();
    }

    public function commit(): void
    {
        $this->db->commit();
    }

    public function rollback(): void
    {
        $this->db->rollBack();
    }
}
