<?php

require_once __DIR__ . '/../BaseTestCase.php';

class FuelExpenseRepositoryTest extends BaseTestCase
{
    private FuelExpenseRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new FuelExpenseRepository($this->db, $this->customerA_KoordinatorContext);
        $this->ensureTripExists();
    }

    public function testCreateFuelReport(): void
    {
        $id = $this->repo->createFuelReport([
            'customer_id' => 1,
            'trip_id' => 1,
            'vehicle_id' => 1,
            'reported_by' => 8,
            'fuel_date' => '2026-07-08',
            'fuel_type' => 'pertalite',
            'liters' => 10.5,
            'price_per_liter' => 10000,
            'km_at_refuel' => 50100,
            'station_name' => 'SPBU Pengayang',
        ]);

        $this->assertGreaterThan(0, $id);

        $report = $this->repo->findFuelReport($id);
        $this->assertNotNull($report);
        $this->assertEquals(10.5, (float)$report['liters']);
        $this->assertEquals(10000, (float)$report['price_per_liter']);
        $this->assertEquals(105000, (float)$report['total_cost']);
        $this->assertEquals('pending', $report['status']);
    }

    public function testFuelReportAutoCalculatesTotalCost(): void
    {
        $liters = 20.0;
        $pricePerLiter = 12000;
        $expectedTotal = $liters * $pricePerLiter;

        $id = $this->repo->createFuelReport([
            'customer_id' => 1,
            'trip_id' => 1,
            'vehicle_id' => 1,
            'reported_by' => 8,
            'fuel_date' => '2026-07-08',
            'fuel_type' => 'pertamax',
            'liters' => $liters,
            'price_per_liter' => $pricePerLiter,
        ]);

        $report = $this->repo->findFuelReport($id);
        $this->assertEquals($expectedTotal, (float)$report['total_cost']);
    }

    public function testApproveFuelReport(): void
    {
        $id = $this->createPendingFuelReport();

        $affected = $this->repo->approveFuelReport($id, 7);
        $this->assertEquals(1, $affected);

        $report = $this->repo->findFuelReport($id);
        $this->assertEquals('approved', $report['status']);
        $this->assertEquals(7, $report['approved_by']);
    }

    public function testRejectFuelReport(): void
    {
        $id = $this->createPendingFuelReport();

        $affected = $this->repo->rejectFuelReport($id, 7, 'Harga tidak sesuai');
        $this->assertEquals(1, $affected);

        $report = $this->repo->findFuelReport($id);
        $this->assertEquals('rejected', $report['status']);
        $this->assertEquals('Harga tidak sesuai', $report['rejection_reason']);
    }

    public function testApproveOnlyPendingFuelReport(): void
    {
        $id = $this->createPendingFuelReport();
        $this->repo->approveFuelReport($id, 7);

        $affected = $this->repo->approveFuelReport($id, 7);
        $this->assertEquals(0, $affected);
    }

    public function testCreateExpenseReport(): void
    {
        $id = $this->repo->createExpenseReport([
            'customer_id' => 1,
            'trip_id' => 1,
            'vehicle_id' => 1,
            'reported_by' => 8,
            'expense_date' => '2026-07-08',
            'category' => 'tol',
            'description' => 'Tol Jorr',
            'amount' => 15000,
        ]);

        $this->assertGreaterThan(0, $id);

        $report = $this->repo->findExpenseReport($id);
        $this->assertNotNull($report);
        $this->assertEquals('tol', $report['category']);
        $this->assertEquals(15000, (float)$report['amount']);
        $this->assertEquals('pending', $report['status']);
    }

    public function testApproveExpenseReport(): void
    {
        $id = $this->createPendingExpenseReport();

        $affected = $this->repo->approveExpenseReport($id, 7);
        $this->assertEquals(1, $affected);

        $report = $this->repo->findExpenseReport($id);
        $this->assertEquals('approved', $report['status']);
    }

    public function testRejectExpenseReport(): void
    {
        $id = $this->createPendingExpenseReport();

        $affected = $this->repo->rejectExpenseReport($id, 7, 'Bukti tidak lengkap');
        $this->assertEquals(1, $affected);

        $report = $this->repo->findExpenseReport($id);
        $this->assertEquals('rejected', $report['status']);
        $this->assertEquals('Bukti tidak lengkap', $report['rejection_reason']);
    }

    public function testFindFuelReportsWithFilters(): void
    {
        $this->createPendingFuelReport();

        $results = $this->repo->findFuelReports([
            'date_start' => '2026-07-01',
            'date_end' => '2026-07-31',
        ]);

        $this->assertNotEmpty($results);
        $this->assertArrayHasKey('plate_number', $results[0]);
    }

    public function testFindFuelReportsByStatus(): void
    {
        $id = $this->createPendingFuelReport();
        $this->repo->approveFuelReport($id, 7);

        $pending = $this->repo->findFuelReports(['status' => 'pending']);
        $approved = $this->repo->findFuelReports(['status' => 'approved']);

        $this->assertEmpty($pending);
        $this->assertNotEmpty($approved);
    }

    public function testFuelReportTenantIsolation(): void
    {
        $this->repo->createFuelReport([
            'customer_id' => 1,
            'trip_id' => 1,
            'vehicle_id' => 1,
            'reported_by' => 8,
            'fuel_date' => '2026-07-08',
            'fuel_type' => 'pertalite',
            'liters' => 5,
            'price_per_liter' => 10000,
        ]);

        $repoB = new FuelExpenseRepository($this->db, $this->customerB_KoordinatorContext);
        $fuelB = $repoB->findFuelReports();

        $this->assertEmpty($fuelB);
    }

    private function ensureTripExists(): void
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM mova_trips WHERE id = 1");
        $stmt->execute();
        if ((int)$stmt->fetchColumn() === 0) {
            $tripRepo = new TripRepository($this->db, $this->customerA_KoordinatorContext);
            $tripRepo->create([
                'customer_id' => 1,
                'vehicle_id' => 1,
                'driver_id' => 8,
                'trip_number' => 'TRP-FUEL-BASE-001',
                'origin' => 'Makassar',
                'destination' => 'Maros',
                'trip_date' => '2026-07-08',
                'purpose_type' => 'dinas',
                'input_by' => 7,
            ]);
        }
    }

    private function createPendingFuelReport(): int
    {
        $unique = 'FR-' . mt_rand(10000, 99999);
        return $this->repo->createFuelReport([
            'customer_id' => 1,
            'trip_id' => 1,
            'vehicle_id' => 1,
            'reported_by' => 8,
            'fuel_date' => '2026-07-08',
            'fuel_type' => 'pertalite',
            'liters' => 10,
            'price_per_liter' => 10000,
            'station_name' => $unique,
        ]);
    }

    private function createPendingExpenseReport(): int
    {
        $unique = 'ER-' . mt_rand(10000, 99999);
        return $this->repo->createExpenseReport([
            'customer_id' => 1,
            'trip_id' => 1,
            'vehicle_id' => 1,
            'reported_by' => 8,
            'expense_date' => '2026-07-08',
            'category' => 'parkir',
            'description' => $unique,
            'amount' => 5000,
        ]);
    }
}
