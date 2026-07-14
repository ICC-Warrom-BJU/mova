<?php

require_once __DIR__ . '/../BaseTestCase.php';

class VehicleRequestRepositoryTest extends BaseTestCase
{
    private VehicleRequestRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new VehicleRequestRepository($this->db, $this->customerA_KoordinatorContext);
    }

    public function testCreateRequest(): void
    {
        $id = $this->repo->create([
            'customer_id' => 1,
            'request_number' => 'REQ-2026-0001',
            'requested_by' => 8,
            'origin' => 'Makassar',
            'destination' => 'Maros',
            'purpose' => 'Antar dokumen',
            'departure_date' => '2026-07-08',
            'return_date' => '2026-07-08',
            'passenger_count' => 1,
        ]);

        $this->assertGreaterThan(0, $id);

        $request = $this->repo->find($id);
        $this->assertNotNull($request);
        $this->assertEquals('Makassar', $request['origin']);
        $this->assertEquals('Maros', $request['destination']);
        $this->assertEquals('pending', $request['status']);
        $this->assertEquals(1, $request['customer_id']);
    }

    public function testApproveL1(): void
    {
        $id = $this->createPendingRequest();
        $affected = $this->repo->approveL1($id, 7);
        $this->assertEquals(1, $affected);

        $request = $this->repo->find($id);
        $this->assertEquals('approved_l1', $request['status']);
        $this->assertEquals(7, $request['approved_by_l1']);
    }

    public function testApproveL2(): void
    {
        $id = $this->createPendingRequest();
        $this->repo->approveL1($id, 7);
        $affected = $this->repo->approveL2($id, 6);
        $this->assertEquals(1, $affected);

        $request = $this->repo->find($id);
        $this->assertEquals('approved', $request['status']);
        $this->assertEquals(6, $request['approved_by_l2']);
    }

    public function testReject(): void
    {
        $id = $this->createPendingRequest();
        $affected = $this->repo->reject($id, 7, 'Kendaraan tidak tersedia');
        $this->assertEquals(1, $affected);

        $request = $this->repo->find($id);
        $this->assertEquals('rejected', $request['status']);
        $this->assertEquals(7, $request['rejected_by']);
        $this->assertEquals('Kendaraan tidak tersedia', $request['rejection_reason']);
    }

    public function testAssign(): void
    {
        $id = $this->createPendingRequest();
        $this->repo->approveL1($id, 7);
        $affected = $this->repo->assign($id, 1, 8);
        $this->assertEquals(1, $affected);

        $request = $this->repo->find($id);
        $this->assertEquals('approved', $request['status']);
        $this->assertEquals(1, $request['assigned_vehicle_id']);
        $this->assertEquals(8, $request['assigned_driver_id']);
    }

    public function testAssignWithoutDriver(): void
    {
        $id = $this->createPendingRequest();
        $this->repo->approveL1($id, 7);
        $affected = $this->repo->assign($id, 1, null);
        $this->assertEquals(1, $affected);

        $request = $this->repo->find($id);
        $this->assertEquals('approved', $request['status']);
        $this->assertEquals(1, $request['assigned_vehicle_id']);
        $this->assertNull($request['assigned_driver_id']);
    }

    public function testApproveOnlyPending(): void
    {
        $id = $this->createPendingRequest();
        $this->repo->approveL1($id, 7);

        $affected = $this->repo->approveL1($id, 7);
        $this->assertEquals(0, $affected);
    }

    public function testRejectOnlyPending(): void
    {
        $id = $this->createPendingRequest();
        $this->repo->approveL1($id, 7);

        $affected = $this->repo->reject($id, 7, 'Alasan');
        $this->assertEquals(0, $affected);
    }

    public function testFindWithRelations(): void
    {
        $this->createPendingRequest();
        $results = $this->repo->findWithRelations([
            'date_start' => '2026-07-01',
            'date_end' => '2026-07-31',
        ]);

        $this->assertNotEmpty($results);
        $this->assertArrayHasKey('requested_by_name', $results[0]);
    }

    public function testFindPending(): void
    {
        $this->createPendingRequest();
        $pending = $this->repo->findPending();

        $this->assertNotEmpty($pending);
        foreach ($pending as $req) {
            $this->assertEquals('pending', $req['status']);
        }
    }

    public function testTenantIsolationCustomerA(): void
    {
        $this->createRequestForCustomer(1, 'REQ-2026-CA-001');
        $this->createRequestForCustomer(2, 'REQ-2026-CB-001');

        $repoA = new VehicleRequestRepository($this->db, $this->customerA_KoordinatorContext);
        $requestsA = $repoA->findAll();

        foreach ($requestsA as $req) {
            $this->assertEquals(1, $req['customer_id']);
        }
    }

    public function testTenantIsolationCustomerB(): void
    {
        $this->createRequestForCustomer(1, 'REQ-2026-CA-002');
        $this->createRequestForCustomer(2, 'REQ-2026-CB-002');

        $repoB = new VehicleRequestRepository($this->db, $this->customerB_KoordinatorContext);
        $requestsB = $repoB->findAll();

        foreach ($requestsB as $req) {
            $this->assertEquals(2, $req['customer_id']);
        }
    }

    public function testSuperAdminSeesAll(): void
    {
        $this->createRequestForCustomer(1, 'REQ-2026-SA-001');
        $this->createRequestForCustomer(2, 'REQ-2026-SA-002');

        $repoSA = new VehicleRequestRepository($this->db, $this->superAdminContext);
        $requestsSA = $repoSA->findAll();

        $this->assertGreaterThanOrEqual(2, count($requestsSA));
    }

    public function testCreateRequestWithDriverOption(): void
    {
        $id = $this->repo->create([
            'customer_id' => 1,
            'request_number' => 'REQ-2026-DRV-001',
            'requested_by' => 8,
            'origin' => 'Makassar',
            'destination' => 'Maros',
            'purpose' => 'Test driver option',
            'driver_option' => 'without_driver',
            'duration_type' => 'half_day',
            'departure_date' => '2026-07-08',
            'return_date' => '2026-07-08',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
            'passenger_count' => 1,
        ]);

        $request = $this->repo->find($id);
        $this->assertEquals('without_driver', $request['driver_option']);
        $this->assertEquals('half_day', $request['duration_type']);
    }

    private function createPendingRequest(): int
    {
        $unique = 'REQ-2026-' . str_pad(mt_rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        return $this->repo->create([
            'customer_id' => 1,
            'request_number' => $unique,
            'requested_by' => 8,
            'origin' => 'Makassar',
            'destination' => 'Maros',
            'purpose' => 'Test request',
            'departure_date' => '2026-07-08',
            'return_date' => '2026-07-08',
            'passenger_count' => 1,
        ]);
    }

    private function createRequestForCustomer(int $customerId, string $requestNumber): int
    {
        $repo = new VehicleRequestRepository($this->db, $this->superAdminContext);
        return $repo->create([
            'customer_id' => $customerId,
            'request_number' => $requestNumber,
            'requested_by' => $customerId === 1 ? 8 : 12,
            'origin' => 'Kota A',
            'destination' => 'Kota B',
            'purpose' => 'Test tenant isolation',
            'departure_date' => '2026-07-08',
            'return_date' => '2026-07-08',
            'passenger_count' => 1,
        ]);
    }
}
