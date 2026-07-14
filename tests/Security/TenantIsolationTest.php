<?php

require_once __DIR__ . '/../BaseTestCase.php';

class TenantIsolationTest extends BaseTestCase
{
    public function testVehicleRequestIsolation(): void
    {
        $repoA = new VehicleRequestRepository($this->db, $this->customerA_KoordinatorContext);
        $repoB = new VehicleRequestRepository($this->db, $this->customerB_KoordinatorContext);

        $repoA->create([
            'customer_id' => 1,
            'request_number' => 'REQ-SEC-A-001',
            'requested_by' => 8,
            'destination' => 'A',
            'purpose' => 'Security test',
            'departure_date' => '2026-07-10',
            'return_date' => '2026-07-10',
        ]);
        $repoB->create([
            'customer_id' => 2,
            'request_number' => 'REQ-SEC-B-001',
            'requested_by' => 12,
            'destination' => 'B',
            'purpose' => 'Security test',
            'departure_date' => '2026-07-10',
            'return_date' => '2026-07-10',
        ]);

        $this->assertCount(1, $repoA->findAll());
        $this->assertCount(1, $repoB->findAll());
    }

    public function testTripIsolation(): void
    {
        $repoA = new TripRepository($this->db, $this->customerA_KoordinatorContext);
        $repoB = new TripRepository($this->db, $this->customerB_KoordinatorContext);

        $repoA->create([
            'customer_id' => 1,
            'vehicle_id' => 1,
            'driver_id' => 8,
            'trip_number' => 'TRP-SEC-A-001',
            'origin' => 'A',
            'destination' => 'B',
            'trip_date' => '2026-07-10',
            'purpose_type' => 'dinas',
            'input_by' => 7,
        ]);
        $repoB->create([
            'customer_id' => 2,
            'vehicle_id' => 4,
            'driver_id' => 12,
            'trip_number' => 'TRP-SEC-B-001',
            'origin' => 'C',
            'destination' => 'D',
            'trip_date' => '2026-07-10',
            'purpose_type' => 'dinas',
            'input_by' => 11,
        ]);

        $this->assertCount(1, $repoA->findAll());
        $this->assertCount(1, $repoB->findAll());
    }

    public function testFuelReportIsolation(): void
    {
        $this->ensureTripId1Exists();

        $repo = new FuelExpenseRepository($this->db, $this->customerA_KoordinatorContext);
        $repo->createFuelReport([
            'customer_id' => 1,
            'trip_id' => 1,
            'vehicle_id' => 1,
            'reported_by' => 8,
            'fuel_date' => '2026-07-10',
            'fuel_type' => 'pertalite',
            'liters' => 10,
            'price_per_liter' => 10000,
        ]);

        $repoB = new FuelExpenseRepository($this->db, $this->customerB_KoordinatorContext);
        $this->assertEmpty($repoB->findFuelReports());
    }

    public function testIssueReportIsolation(): void
    {
        $repoA = new DriverSelfServiceRepository($this->db, $this->customerA_KoordinatorContext);
        $repoB = new DriverSelfServiceRepository($this->db, $this->customerB_KoordinatorContext);

        $repoA->createIssue([
            'customer_id' => 1,
            'vehicle_id' => 1,
            'report_number' => 'ISS-SEC-A-001',
            'reported_by' => 8,
            'category' => 'mesin',
            'description' => 'Test',
            'severity' => 'low',
        ]);
        $repoB->createIssue([
            'customer_id' => 2,
            'vehicle_id' => 4,
            'report_number' => 'ISS-SEC-B-001',
            'reported_by' => 12,
            'category' => 'ban',
            'description' => 'Test',
            'severity' => 'low',
        ]);

        $this->assertCount(1, $repoA->findIssues());
        $this->assertCount(1, $repoB->findIssues());
    }

    public function testMaintenanceScheduleIsolation(): void
    {
        $repoA = new MaintenanceRepository($this->db, $this->customerA_KoordinatorContext);
        $repoB = new MaintenanceRepository($this->db, $this->customerB_KoordinatorContext);

        $repoA->createSchedule([
            'customer_id' => 1,
            'vehicle_id' => 1,
            'service_type' => 'Test A',
            'trigger_type' => 'km_based',
            'km_threshold' => 50000,
            'created_by' => 7,
        ]);
        $repoB->createSchedule([
            'customer_id' => 2,
            'vehicle_id' => 4,
            'service_type' => 'Test B',
            'trigger_type' => 'km_based',
            'km_threshold' => 30000,
            'created_by' => 11,
        ]);

        $this->assertCount(1, $repoA->findSchedulesWithRelations());
        $this->assertCount(1, $repoB->findSchedulesWithRelations());
    }

    public function testScopedUpdateRespectsTenant(): void
    {
        $repoA = new VehicleRequestRepository($this->db, $this->customerA_KoordinatorContext);
        $repoB = new VehicleRequestRepository($this->db, $this->customerB_KoordinatorContext);

        $idA = $repoA->create([
            'customer_id' => 1,
            'request_number' => 'REQ-SUP-A-001',
            'requested_by' => 8,
            'destination' => 'A',
            'purpose' => 'Scoped update test',
            'departure_date' => '2026-07-10',
            'return_date' => '2026-07-10',
        ]);

        $affected = $repoB->approveL1($idA, 11);
        $this->assertEquals(0, $affected, 'Customer B should not be able to approve Customer A\'s request');

        $request = $repoA->find($idA);
        $this->assertEquals('pending', $request['status']);
    }

    public function testScopedDeleteRespectsTenant(): void
    {
        $repoA = new VehicleRequestRepository($this->db, $this->customerA_KoordinatorContext);
        $repoB = new VehicleRequestRepository($this->db, $this->customerB_KoordinatorContext);

        $idA = $repoA->create([
            'customer_id' => 1,
            'request_number' => 'REQ-DEL-A-001',
            'requested_by' => 8,
            'destination' => 'A',
            'purpose' => 'Scoped delete test',
            'departure_date' => '2026-07-10',
            'return_date' => '2026-07-10',
        ]);

        $affected = $repoB->delete($idA);
        $this->assertEquals(0, $affected, 'Customer B should not be able to delete Customer A\'s request');

        $this->assertNotNull($repoA->find($idA));
    }

    private function ensureTripId1Exists(): void
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM mova_trips WHERE id = 1");
        $stmt->execute();
        if ((int)$stmt->fetchColumn() === 0) {
            $tripRepo = new TripRepository($this->db, $this->customerA_KoordinatorContext);
            $tripRepo->create([
                'customer_id' => 1,
                'vehicle_id' => 1,
                'driver_id' => 8,
                'trip_number' => 'TRP-SEC-BASE-001',
                'origin' => 'Makassar',
                'destination' => 'Maros',
                'trip_date' => '2026-07-10',
                'purpose_type' => 'dinas',
                'input_by' => 7,
            ]);
        }
    }
}
