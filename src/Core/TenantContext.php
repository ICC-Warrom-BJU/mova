<?php

class TenantContext
{
    private ?int $customerId;
    private ?int $branchId;
    private array $branchIds = [];
    private string $layer;
    private string $role;
    private ?int $userId;
    private array $accessibleCustomerIds = [];

    public function __construct(array $sessionData)
    {
        $this->userId = $sessionData['user_id'] ?? null;
        $this->layer = $sessionData['layer'] ?? 'customer';
        $this->role = $sessionData['role'] ?? 'driver';
        $this->customerId = $sessionData['customer_id'] ?? null;
        $this->branchId = $sessionData['branch_id'] ?? null;
        $this->branchIds = $sessionData['branch_ids'] ?? ($this->branchId !== null ? [$this->branchId] : []);

        if ($this->layer === 'company' && !$this->isSuperAdmin()) {
            $this->accessibleCustomerIds = $this->resolveCustomersByBranches();
        }
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function getCustomerId(): ?int
    {
        return $this->customerId;
    }

    public function getBranchId(): ?int
    {
        return $this->branchId;
    }

    public function getBranchIds(): array
    {
        return $this->branchIds;
    }

    public function getLayer(): string
    {
        return $this->layer;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getAccessibleCustomerIds(): array
    {
        if ($this->layer === 'customer') {
            return $this->customerId !== null ? [$this->customerId] : [];
        }
        if ($this->isSuperAdmin()) {
            return [];
        }
        return $this->accessibleCustomerIds;
    }

    public function isSuperAdmin(): bool
    {
        return $this->layer === 'company' && $this->role === 'super_admin';
    }

    /**
     * Apakah modul ($key) diizinkan oleh paket langganan customer ini?
     * Company layer (BJU internal) selalu true. Customer: cek allowed_modules
     * dari plan (atau override di customer_configs).
     */
    public function hasModule(string $key): bool
    {
        if ($this->layer === 'company') return true;
        if ($this->customerId === null) return false;
        return in_array($key, planAllowedModules($this->customerId), true);
    }

    /** Nama paket customer (free/premium/enterprise), null utk company. */
    public function planName(): ?string
    {
        return $this->customerId !== null ? customerPlanName($this->customerId) : null;
    }

    public function isManagement(): bool
    {
        return $this->layer === 'company' && $this->role === 'management';
    }

    private function resolveCustomersByBranches(): array
    {
        if (empty($this->branchIds)) {
            return [];
        }

        try {
            $db = Database::getConnection();
            $ph = implode(',', array_fill(0, count($this->branchIds), '?'));
            $stmt = $db->prepare("SELECT id FROM mova_customers WHERE branch_id IN ($ph) AND is_active = 1");
            $stmt->execute(array_values($this->branchIds));
            return array_column($stmt->fetchAll(), 'id');
        } catch (\PDOException $e) {
            return [];
        }
    }
}
