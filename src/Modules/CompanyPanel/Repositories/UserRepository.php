<?php

class UserRepository extends BaseRepository
{
    protected string $table = 'mova_users';

    public function findWithRole(): array
    {
        $sql = "SELECT u.*, r.name AS role_name, r.layer AS role_layer
                FROM mova_users u
                JOIN mova_roles r ON r.id = u.role_id";

        if ($this->tenant->isSuperAdmin()) {
            $stmt = $this->db->prepare($sql . " ORDER BY u.name");
            $stmt->execute();
        } else {
            $ids = $this->tenant->getAccessibleCustomerIds();
            if (empty($ids)) {
                return [];
            }
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $sql .= " WHERE u.customer_id IN ($placeholders) ORDER BY u.name";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($ids);
        }

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
