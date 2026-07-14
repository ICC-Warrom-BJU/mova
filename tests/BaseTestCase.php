<?php

abstract class BaseTestCase extends PHPUnit\Framework\TestCase
{
    protected ?PDO $db;
    protected ?TenantContext $superAdminContext;
    protected ?TenantContext $customerA_KoordinatorContext;
    protected ?TenantContext $customerA_DriverContext;
    protected ?TenantContext $customerB_KoordinatorContext;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = TestDatabase::getConnection();
        TestDatabase::seed();
        $this->createTestContexts();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    private function createTestContexts(): void
    {
        $this->superAdminContext = new TenantContext([
            'user_id' => 1,
            'layer' => 'company',
            'role' => 'super_admin',
            'customer_id' => null,
            'branch_id' => null,
            'branch_ids' => [],
        ]);

        $this->customerA_KoordinatorContext = new TenantContext([
            'user_id' => 7,
            'layer' => 'customer',
            'role' => 'koordinator',
            'customer_id' => 1,
            'branch_id' => 1,
            'branch_ids' => [],
        ]);

        $this->customerA_DriverContext = new TenantContext([
            'user_id' => 8,
            'layer' => 'customer',
            'role' => 'driver',
            'customer_id' => 1,
            'branch_id' => 1,
            'branch_ids' => [],
        ]);

        $this->customerB_KoordinatorContext = new TenantContext([
            'user_id' => 11,
            'layer' => 'customer',
            'role' => 'koordinator',
            'customer_id' => 2,
            'branch_id' => 2,
            'branch_ids' => [],
        ]);
    }

    protected function createRepository(string $class, TenantContext $tenant)
    {
        return new $class($this->db, $tenant);
    }
}
