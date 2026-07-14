<?php

class UserRepository extends BaseRepository
{
    protected string $table = 'mova_users';

    public function findWithRole(?string $search = null): array
    {
        $sql = "SELECT u.*, r.name AS role_name, r.layer AS role_layer
                FROM mova_users u
                JOIN mova_roles r ON r.id = u.role_id";

        $params = [];
        $conditions = [];

        if (!$this->tenant->isSuperAdmin()) {
            $ids = $this->tenant->getAccessibleCustomerIds();
            if (empty($ids)) {
                return [];
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $conditions[] = "u.customer_id IN ($placeholders)";
            $params = $ids;
        }

        if ($search !== null && $search !== '') {
            $keyword = '%' . $search . '%';
            $conditions[] = "(u.name LIKE ? OR u.email LIKE ? OR r.name LIKE ?)";
            $params = array_merge($params, [$keyword, $keyword, $keyword]);
        }

        if (!empty($conditions)) {
            $sql .= " WHERE " . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY u.name";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function findByCustomer(int $customerId): array
    {
        $stmt = $this->db->prepare(
            "SELECT u.*, r.name AS role_name
             FROM mova_users u
             JOIN mova_roles r ON r.id = u.role_id
             WHERE u.customer_id = ? AND u.is_active = 1
             ORDER BY u.name"
        );
        $stmt->execute([$customerId]);
        return $stmt->fetchAll();
    }

    public function findByEmail(string $email): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM mova_users WHERE email = ?");
        $stmt->execute([$email]);
        return $stmt->fetch() ?: null;
    }

    public function create(array $data): int
    {
        $password = $data['password'] ?? bin2hex(random_bytes(8));
        $hashed = password_hash($password, PASSWORD_ARGON2ID);

        return $this->scopedInsert([
            'role_id' => $data['role_id'],
            'customer_id' => $data['customer_id'] ?? null,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $hashed,
            'phone' => $data['phone'] ?? null,
            'telegram_chat_id' => $data['telegram_chat_id'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
        ]);
    }

    public function update(int $id, array $data): int
    {
        $updateData = [
            'role_id' => $data['role_id'],
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'is_active' => $data['is_active'] ?? 1,
        ];

        if (!empty($data['password'])) {
            $updateData['password'] = password_hash($data['password'], PASSWORD_ARGON2ID);
        }

        return $this->scopedUpdate($updateData, 'id = ?', [$id]);
    }
}
