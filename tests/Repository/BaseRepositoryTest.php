<?php

require_once __DIR__ . '/../BaseTestCase.php';

class PaginationTestRepo extends BaseRepository
{
    protected string $table = 'mova_vehicle_requests';
}

class NoCustomerIdTestRepo extends BaseRepository
{
    protected string $table = 'mova_vehicle_requests';
    protected bool $hasCustomerId = false;
}

class BaseRepositoryTest extends BaseTestCase
{
    private int $reqCounter = 0;

    private function insertVehicleRequests(PDO $db, int $customerId, int $count): void
    {
        $requestedBy = $customerId === 1 ? 7 : 11;
        $sql = "INSERT INTO mova_vehicle_requests
                (customer_id, request_number, requested_by, destination, purpose,
                 departure_date, return_date, passenger_count, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);

        for ($i = 0; $i < $count; $i++) {
            $this->reqCounter++;
            $stmt->execute([
                $customerId,
                "REQ-TEST-{$this->reqCounter}",
                $requestedBy,
                "Destination {$this->reqCounter}",
                "Purpose {$this->reqCounter}",
                '2026-01-15',
                '2026-01-16',
                1,
                'pending',
            ]);
        }
    }

    // --- Pagination ---

    public function testPaginatedFirstPageReturnsCorrectSlice(): void
    {
        $this->insertVehicleRequests($this->db, 1, 25);
        $repo = new PaginationTestRepo($this->db, $this->customerA_KoordinatorContext);

        $result = $repo->findAllPaginated(1, 10);

        $this->assertCount(10, $result['data']);
        $this->assertEquals(25, $result['total']);
        $this->assertEquals(1, $result['page']);
        $this->assertEquals(10, $result['per_page']);
        $this->assertEquals(3, $result['last_page']);
        $this->assertEquals('REQ-TEST-1', $result['data'][0]['request_number']);
    }

    public function testPaginatedSecondPageReturnsCorrectSlice(): void
    {
        $this->insertVehicleRequests($this->db, 1, 25);
        $repo = new PaginationTestRepo($this->db, $this->customerA_KoordinatorContext);

        $result = $repo->findAllPaginated(2, 10);

        $this->assertCount(10, $result['data']);
        $this->assertEquals(25, $result['total']);
        $this->assertEquals(2, $result['page']);
        $this->assertEquals(3, $result['last_page']);
        $this->assertEquals('REQ-TEST-11', $result['data'][0]['request_number']);
    }

    public function testPaginatedLastPageReturnsRemaining(): void
    {
        $this->insertVehicleRequests($this->db, 1, 25);
        $repo = new PaginationTestRepo($this->db, $this->customerA_KoordinatorContext);

        $result = $repo->findAllPaginated(3, 10);

        $this->assertCount(5, $result['data']);
        $this->assertEquals(25, $result['total']);
        $this->assertEquals(3, $result['page']);
        $this->assertEquals('REQ-TEST-21', $result['data'][0]['request_number']);
    }

    public function testPaginatedBeyondLastPageReturnsEmpty(): void
    {
        $this->insertVehicleRequests($this->db, 1, 25);
        $repo = new PaginationTestRepo($this->db, $this->customerA_KoordinatorContext);

        $result = $repo->findAllPaginated(10, 10);

        $this->assertCount(0, $result['data']);
        $this->assertEquals(25, $result['total']);
        $this->assertEquals(10, $result['page']);
        $this->assertEquals(3, $result['last_page']);
    }

    // --- Tenant Scoping ---

    public function testTenantScopingCustomerASeesOwnDataOnly(): void
    {
        $this->insertVehicleRequests($this->db, 1, 10);
        $this->insertVehicleRequests($this->db, 2, 5);
        $repo = new PaginationTestRepo($this->db, $this->customerA_KoordinatorContext);

        $result = $repo->findAllPaginated(1, 50);

        $this->assertCount(10, $result['data']);
        $this->assertEquals(10, $result['total']);
        foreach ($result['data'] as $row) {
            $this->assertEquals(1, $row['customer_id']);
        }
    }

    public function testTenantScopingCustomerBSeesOwnDataOnly(): void
    {
        $this->insertVehicleRequests($this->db, 1, 10);
        $this->insertVehicleRequests($this->db, 2, 5);
        $repo = new PaginationTestRepo($this->db, $this->customerB_KoordinatorContext);

        $result = $repo->findAllPaginated(1, 50);

        $this->assertCount(5, $result['data']);
        $this->assertEquals(5, $result['total']);
        foreach ($result['data'] as $row) {
            $this->assertEquals(2, $row['customer_id']);
        }
    }

    public function testSuperAdminSeesAllData(): void
    {
        $this->insertVehicleRequests($this->db, 1, 10);
        $this->insertVehicleRequests($this->db, 2, 5);
        $repo = new PaginationTestRepo($this->db, $this->superAdminContext);

        $result = $repo->findAllPaginated(1, 50);

        $this->assertCount(15, $result['data']);
        $this->assertEquals(15, $result['total']);
    }

    // --- Custom WHERE clause ---

    public function testCustomWhereCombinedWithTenantScoping(): void
    {
        $this->insertVehicleRequests($this->db, 1, 5);

        $stmt = $this->db->prepare(
            "UPDATE mova_vehicle_requests SET status = 'approved' WHERE id = ?"
        );
        $stmt->execute([$this->db->lastInsertId()]);

        $repo = new PaginationTestRepo($this->db, $this->customerA_KoordinatorContext);
        $result = $repo->findAllPaginated(1, 50, "status = ?", ['approved']);

        $this->assertCount(1, $result['data']);
        $this->assertEquals('approved', $result['data'][0]['status']);
    }

    public function testSuperAdminCustomWhereNoScoping(): void
    {
        $this->insertVehicleRequests($this->db, 1, 5);
        $this->insertVehicleRequests($this->db, 2, 3);

        $stmt = $this->db->prepare(
            "UPDATE mova_vehicle_requests SET status = 'approved' WHERE customer_id = 2"
        );
        $stmt->execute();

        $repo = new PaginationTestRepo($this->db, $this->superAdminContext);
        $result = $repo->findAllPaginated(1, 50, "status = ?", ['approved']);

        $this->assertCount(3, $result['data']);
    }

    // --- hasCustomerId = false ---

    public function testHasCustomerIdFalseSkipsTenantScoping(): void
    {
        $this->insertVehicleRequests($this->db, 1, 10);
        $this->insertVehicleRequests($this->db, 2, 5);
        $repo = new NoCustomerIdTestRepo($this->db, $this->customerA_KoordinatorContext);

        $result = $repo->findAllPaginated(1, 50);

        $this->assertCount(15, $result['data']);
        $this->assertEquals(15, $result['total']);
    }

    // --- Edge cases ---

    public function testZeroResultsReturnsEmpty(): void
    {
        $repo = new PaginationTestRepo($this->db, $this->customerA_KoordinatorContext);

        $result = $repo->findAllPaginated(1, 10);

        $this->assertCount(0, $result['data']);
        $this->assertEquals(0, $result['total']);
        $this->assertEquals(1, $result['page']);
        $this->assertEquals(0, $result['last_page']);
    }

    public function testCustomPerPage(): void
    {
        $this->insertVehicleRequests($this->db, 1, 7);
        $repo = new PaginationTestRepo($this->db, $this->customerA_KoordinatorContext);

        $result = $repo->findAllPaginated(1, 3);

        $this->assertCount(3, $result['data']);
        $this->assertEquals(7, $result['total']);
        $this->assertEquals(3, $result['last_page']);
    }
}
