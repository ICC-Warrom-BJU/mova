<?php

require_once __DIR__ . '/../BaseTestCase.php';

class MaintenanceRepositoryTest extends BaseTestCase
{
    private MaintenanceRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new MaintenanceRepository($this->db, $this->customerA_KoordinatorContext);
    }

    public function testCreateScheduleKmBased(): void
    {
        $id = $this->repo->createSchedule([
            'customer_id' => 1,
            'vehicle_id' => 1,
            'service_type' => 'Ganti Oli',
            'trigger_type' => 'km_based',
            'km_threshold' => 60000,
            'reminder_days_before' => 7,
            'created_by' => 7,
        ]);

        $this->assertGreaterThan(0, $id);

        $schedule = $this->repo->find($id);
        $this->assertNotNull($schedule);
        $this->assertEquals('Ganti Oli', $schedule['service_type']);
        $this->assertEquals('km_based', $schedule['trigger_type']);
        $this->assertEquals(60000, $schedule['km_threshold']);
        $this->assertEquals('scheduled', $schedule['status']);
        $this->assertEquals(1, $schedule['customer_id']);
    }

    public function testCreateScheduleDateBased(): void
    {
        $id = $this->repo->createSchedule([
            'customer_id' => 1,
            'vehicle_id' => 2,
            'service_type' => 'Servis 6 Bulan',
            'trigger_type' => 'date_based',
            'scheduled_date' => '2026-09-01',
            'reminder_days_before' => 14,
            'created_by' => 7,
        ]);

        $schedule = $this->repo->find($id);
        $this->assertEquals('date_based', $schedule['trigger_type']);
        $this->assertEquals('2026-09-01', $schedule['scheduled_date']);
    }

    public function testUpdateScheduleStatus(): void
    {
        $id = $this->repo->createSchedule([
            'customer_id' => 1,
            'vehicle_id' => 1,
            'service_type' => 'Ganti Oli',
            'trigger_type' => 'km_based',
            'km_threshold' => 60000,
            'created_by' => 7,
        ]);

        $affected = $this->repo->updateScheduleStatus($id, 'completed');
        $this->assertEquals(1, $affected);

        $schedule = $this->repo->find($id);
        $this->assertEquals('completed', $schedule['status']);
    }

    public function testCreateLog(): void
    {
        $scheduleId = $this->repo->createSchedule([
            'customer_id' => 1,
            'vehicle_id' => 1,
            'service_type' => 'Ganti Oli',
            'trigger_type' => 'km_based',
            'km_threshold' => 60000,
            'created_by' => 7,
        ]);

        $logId = $this->repo->createLog([
            'schedule_id' => $scheduleId,
            'customer_id' => 1,
            'vehicle_id' => 1,
            'service_type' => 'Ganti Oli',
            'service_date' => '2026-07-08',
            'km_at_service' => 60000,
            'workshop_name' => 'Bengkel Maju Jaya',
            'cost' => 350000,
            'notes' => 'Oli Castrol 5W-30',
            'next_service_km' => 66000,
            'next_service_date' => '2026-10-08',
            'logged_by' => 7,
        ]);

        $this->assertGreaterThan(0, $logId);

        $logs = $this->repo->findLogsByVehicle(1);
        $this->assertNotEmpty($logs);
        $this->assertEquals('Bengkel Maju Jaya', $logs[0]['workshop_name']);
        $this->assertEquals(350000, (float)$logs[0]['cost']);
    }

    public function testCreateLogWithoutSchedule(): void
    {
        $logId = $this->repo->createLog([
            'schedule_id' => null,
            'customer_id' => 1,
            'vehicle_id' => 2,
            'service_type' => 'Ganti Ban',
            'service_date' => '2026-07-08',
            'km_at_service' => 80000,
            'workshop_name' => 'Tambal Ban 24 Jam',
            'logged_by' => 7,
        ]);

        $this->assertGreaterThan(0, $logId);

        $logs = $this->repo->findLogsByVehicle(2);
        $this->assertNotEmpty($logs);
    }

    public function testFindSchedulesWithRelations(): void
    {
        $this->repo->createSchedule([
            'customer_id' => 1,
            'vehicle_id' => 1,
            'service_type' => 'Service Rutin',
            'trigger_type' => 'km_based',
            'km_threshold' => 50000,
            'created_by' => 7,
        ]);

        $schedules = $this->repo->findSchedulesWithRelations();
        $this->assertNotEmpty($schedules);
        $this->assertArrayHasKey('plate_number', $schedules[0]);
    }

    public function testTenantIsolation(): void
    {
        $this->repo->createSchedule([
            'customer_id' => 1,
            'vehicle_id' => 1,
            'service_type' => 'Isolasi Test A',
            'trigger_type' => 'km_based',
            'km_threshold' => 50000,
            'created_by' => 7,
        ]);

        $repoB = new MaintenanceRepository($this->db, $this->customerB_KoordinatorContext);
        $schedulesB = $repoB->findSchedulesWithRelations();
        $this->assertEmpty($schedulesB);
    }

    public function testSuperAdminSeesAllSchedules(): void
    {
        $repoB = new MaintenanceRepository($this->db, $this->customerB_KoordinatorContext);
        $repoB->createSchedule([
            'customer_id' => 2,
            'vehicle_id' => 4,
            'service_type' => 'Servis B',
            'trigger_type' => 'date_based',
            'scheduled_date' => '2026-08-01',
            'created_by' => 11,
        ]);

        $repoSA = new MaintenanceRepository($this->db, $this->superAdminContext);
        $allSchedules = $repoSA->findSchedulesWithRelations();
        $this->assertGreaterThanOrEqual(1, count($allSchedules));
    }

    public function testFindLogsByVehicle(): void
    {
        $this->repo->createLog([
            'customer_id' => 1,
            'vehicle_id' => 1,
            'service_type' => 'Test Log',
            'service_date' => '2026-07-08',
            'logged_by' => 7,
        ]);

        $logs = $this->repo->findLogsByVehicle(1);
        $this->assertNotEmpty($logs);
        $this->assertArrayHasKey('logged_by_name', $logs[0]);
    }
}
