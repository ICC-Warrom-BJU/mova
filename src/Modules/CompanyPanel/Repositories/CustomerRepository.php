<?php

class CustomerRepository extends BaseRepository
{
    protected string $table = 'mova_customers';

    public function findActive(): array
    {
        return $this->scopedSelect('is_active = 1 ORDER BY name ASC');
    }

    public function findWithBranch(): array
    {
        $sql = "SELECT c.*, b.name AS branch_name, sp.name AS plan_name
                FROM mova_customers c
                JOIN mova_branches b ON b.id = c.branch_id
                JOIN mova_subscription_plans sp ON sp.id = c.subscription_plan_id
                ORDER BY c.name";

        if ($this->tenant->isSuperAdmin()) {
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
        } else {
            $ids = $this->tenant->getAccessibleCustomerIds();
            if (empty($ids)) {
                return [];
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql .= " WHERE c.id IN ($placeholders)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($ids);
        }

        return $stmt->fetchAll();
    }

    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT c.*, b.name AS branch_name, sp.name AS plan_name
                FROM mova_customers c
                JOIN mova_branches b ON b.id = c.branch_id
                JOIN mova_subscription_plans sp ON sp.id = c.subscription_plan_id
                WHERE c.id IN ($ph) ORDER BY c.name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_values($ids));
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $id = $this->scopedInsert([
            'branch_id' => $data['branch_id'],
            'subscription_plan_id' => $data['subscription_plan_id'],
            'name' => $data['name'],
            'code' => $data['code'],
            'pic_name' => $data['pic_name'] ?? null,
            'pic_phone' => $data['pic_phone'] ?? null,
            'pic_email' => $data['pic_email'] ?? null,
            'contract_start' => $data['contract_start'] ?? null,
            'contract_end' => $data['contract_end'] ?? null,
            'total_units' => $data['total_units'] ?? 0,
            'is_active' => $data['is_active'] ?? 1,
        ]);

        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO mova_customer_configs (customer_id, enable_supervisor_approval) VALUES (?, 0)");
        $stmt->execute([$id]);

        return $id;
    }

    public function update(int $id, array $data): int
    {
        return $this->scopedUpdate(
            [
                'branch_id' => $data['branch_id'],
                'subscription_plan_id' => $data['subscription_plan_id'],
                'name' => $data['name'],
                'code' => $data['code'],
                'pic_name' => $data['pic_name'] ?? null,
                'pic_phone' => $data['pic_phone'] ?? null,
                'pic_email' => $data['pic_email'] ?? null,
                'contract_start' => $data['contract_start'] ?? null,
                'contract_end' => $data['contract_end'] ?? null,
                'total_units' => $data['total_units'] ?? 0,
                'is_active' => $data['is_active'] ?? 1,
            ],
            'id = ?',
            [$id]
        );
    }
}
