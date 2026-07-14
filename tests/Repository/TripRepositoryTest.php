<?php

require_once __DIR__ . '/../BaseTestCase.php';

class TripRepositoryTest extends BaseTestCase
{
    private TripRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new TripRepository($this->db, $this->customerA_KoordinatorContext);
    }

    public function testCreateTrip(): void
    {
        $id = $this->repo->create([
            'customer_id' => 1,
            'vehicle_id' => 1,
            'driver_id' => 8,
            'trip_number' => 'TRP-2026-0001',
            'origin' => 'Makassar',
            'destination' => 'Maros',
            'trip_date' => '2026-07-08',
            'purpose_type' => 'dinas',
            'input_by' => 7,
        ]);

        $this->assertGreaterThan(0, $id);

        $trip = $this->repo->find($id);
        $this->assertNotNull($trip);
        $this->assertEquals('draft', $trip['status']);
        $this->assertEquals('Makassar', $trip['origin']);
        $this->assertEquals(1, $trip['customer_id']);
    }

    public function testStartTrip(): void
    {
        $id = $this->createDraftTrip('TRP-2026-START-001');

        $affected = $this->repo->startTrip($id, 50000, '08:00:00');
        $this->assertEquals(1, $affected);

        $trip = $this->repo->find($id);
        $this->assertEquals('in_progress', $trip['status']);
        $this->assertEquals(50000, $trip['km_start']);
        $this->assertEquals('08:00:00', $trip['departure_time']);
    }

    public function testCompleteTrip(): void
    {
        $id = $this->createDraftTrip('TRP-2026-COMP-001');
        $this->repo->startTrip($id, 50000, '08:00:00');

        $affected = $this->repo->completeTrip($id, 50120, 120, '17:00:00');
        $this->assertEquals(1, $affected);

        $trip = $this->repo->find($id);
        $this->assertEquals('completed', $trip['status']);
        $this->assertEquals(50120, $trip['km_end']);
        $this->assertEquals(120, $trip['distance_km']);
        $this->assertEquals('17:00:00', $trip['return_time']);
    }

    public function testCompleteTripCalculatesDistance(): void
    {
        $id = $this->createDraftTrip('TRP-2026-DIST-001');
        $this->repo->startTrip($id, 30000, '09:00:00');
        $this->repo->completeTrip($id, 30150, 150, '18:00:00');

        $trip = $this->repo->find($id);
        $this->assertEquals(150, $trip['distance_km']);
        $this->assertEquals(30150 - 30000, $trip['distance_km']);
    }

    public function testCannotStartNonDraftTrip(): void
    {
        $id = $this->createDraftTrip('TRP-2026-ND-001');
        $this->repo->startTrip($id, 50000, '08:00:00');

        $affected = $this->repo->startTrip($id, 50100, '09:00:00');
        $this->assertEquals(0, $affected);
    }

    public function testCannotCompleteNonInProgressTrip(): void
    {
        $id = $this->createDraftTrip('TRP-2026-NC-001');

        $affected = $this->repo->completeTrip($id, 50100, 100, '17:00:00');
        $this->assertEquals(0, $affected);
    }

    public function testFindWithRelations(): void
    {
        $this->createDraftTrip('TRP-2026-FWR-001');
        $results = $this->repo->findWithRelations([
            'date_start' => '2026-07-01',
            'date_end' => '2026-07-31',
        ]);

        $this->assertNotEmpty($results);
        $this->assertArrayHasKey('plate_number', $results[0]);
        $this->assertArrayHasKey('driver_name', $results[0]);
    }

    public function testFindActiveTrips(): void
    {
        $id = $this->createDraftTrip('TRP-2026-ACT-001');
        $this->repo->startTrip($id, 50000, '08:00:00');

        $active = $this->repo->findActiveTrips();
        $this->assertNotEmpty($active);
        foreach ($active as $trip) {
            $this->assertEquals('in_progress', $trip['status']);
        }
    }

    public function testTenantIsolation(): void
    {
        $repoA = new TripRepository($this->db, $this->customerA_KoordinatorContext);
        $repoB = new TripRepository($this->db, $this->customerB_KoordinatorContext);

        $repoA->create([
            'customer_id' => 1,
            'vehicle_id' => 1,
            'driver_id' => 8,
            'trip_number' => 'TRP-2026-ISO-A-001',
            'origin' => 'Makassar',
            'destination' => 'Maros',
            'trip_date' => '2026-07-08',
            'purpose_type' => 'dinas',
            'input_by' => 7,
        ]);

        $repoB->create([
            'customer_id' => 2,
            'vehicle_id' => 4,
            'driver_id' => 12,
            'trip_number' => 'TRP-2026-ISO-B-001',
            'origin' => 'Parepare',
            'destination' => 'Pinrang',
            'trip_date' => '2026-07-08',
            'purpose_type' => 'material',
            'input_by' => 11,
        ]);

        $tripsA = $repoA->findAll();
        foreach ($tripsA as $t) {
            $this->assertEquals(1, $t['customer_id']);
        }

        $tripsB = $repoB->findAll();
        foreach ($tripsB as $t) {
            $this->assertEquals(2, $t['customer_id']);
        }
    }

    public function testSuperAdminSeesAll(): void
    {
        $repoA = new TripRepository($this->db, $this->customerA_KoordinatorContext);
        $repoA->create([
            'customer_id' => 1,
            'vehicle_id' => 1,
            'driver_id' => 8,
            'trip_number' => 'TRP-2026-SA-A-001',
            'origin' => 'A',
            'destination' => 'B',
            'trip_date' => '2026-07-08',
            'purpose_type' => 'dinas',
            'input_by' => 7,
        ]);
        $repoA->create([
            'customer_id' => 2,
            'vehicle_id' => 4,
            'driver_id' => 12,
            'trip_number' => 'TRP-2026-SA-B-001',
            'origin' => 'C',
            'destination' => 'D',
            'trip_date' => '2026-07-08',
            'purpose_type' => 'dinas',
            'input_by' => 11,
        ]);

        $repoSA = new TripRepository($this->db, $this->superAdminContext);
        $allTrips = $repoSA->findAll();
        $this->assertGreaterThanOrEqual(2, count($allTrips));
    }

    private function createDraftTrip(string $tripNumber): int
    {
        return $this->repo->create([
            'customer_id' => 1,
            'vehicle_id' => 1,
            'driver_id' => 8,
            'trip_number' => $tripNumber,
            'origin' => 'Makassar',
            'destination' => 'Maros',
            'trip_date' => '2026-07-08',
            'purpose_type' => 'dinas',
            'input_by' => 7,
        ]);
    }
}
