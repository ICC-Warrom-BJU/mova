<?php

require_once __DIR__ . '/../BaseTestCase.php';

class VehicleRequestFullWorkflowTest extends BaseTestCase
{
    public function testDriverCreateKoordinatorApproveApprovalFlow(): void
    {
        $driverRepo = new VehicleRequestRepository($this->db, $this->customerA_DriverContext);
        $koordinatorRepo = new VehicleRequestRepository($this->db, $this->customerA_KoordinatorContext);

        $id = $driverRepo->create([
            'customer_id' => 1,
            'request_number' => 'REQ-WF-2026-001',
            'requested_by' => 8,
            'origin' => 'Makassar',
            'destination' => 'Maros',
            'purpose' => 'Dinas pengantaran',
            'departure_date' => '2026-07-10',
            'return_date' => '2026-07-10',
        ]);
        $this->assertGreaterThan(0, $id);

        $request = $koordinatorRepo->find($id);
        $this->assertEquals('pending', $request['status']);

        $koordinatorRepo->approveL1($id, 7);
        $request = $koordinatorRepo->find($id);
        $this->assertEquals('approved_l1', $request['status']);

        $koordinatorRepo->assign($id, 1, 8);
        $request = $koordinatorRepo->find($id);
        $this->assertEquals('approved', $request['status']);
        $this->assertEquals(1, $request['assigned_vehicle_id']);
        $this->assertEquals(8, $request['assigned_driver_id']);
    }

    public function testFullRejectionFlow(): void
    {
        $repo = new VehicleRequestRepository($this->db, $this->customerA_KoordinatorContext);

        $id = $repo->create([
            'customer_id' => 1,
            'request_number' => 'REQ-REJ-2026-001',
            'requested_by' => 8,
            'origin' => 'Makassar',
            'destination' => 'Gowa',
            'purpose' => 'Perjalanan dinas',
            'departure_date' => '2026-07-10',
            'return_date' => '2026-07-11',
        ]);

        $repo->reject($id, 7, 'Kendaraan tidak tersedia');
        $request = $repo->find($id);
        $this->assertEquals('rejected', $request['status']);
        $this->assertEquals('Kendaraan tidak tersedia', $request['rejection_reason']);
        $this->assertEquals(7, $request['rejected_by']);
    }

    public function testTripLifecycleFromDraftToCompleted(): void
    {
        $tripRepo = new TripRepository($this->db, $this->customerA_KoordinatorContext);

        $tripId = $tripRepo->create([
            'customer_id' => 1,
            'vehicle_id' => 1,
            'driver_id' => 8,
            'trip_number' => 'TRP-LC-2026-001',
            'origin' => 'Makassar',
            'destination' => 'Maros',
            'trip_date' => '2026-07-10',
            'purpose_type' => 'dinas',
            'input_by' => 7,
        ]);
        $this->assertEquals('draft', $tripRepo->find($tripId)['status']);

        $tripRepo->startTrip($tripId, 50000, '08:00:00');
        $this->assertEquals('in_progress', $tripRepo->find($tripId)['status']);

        $tripRepo->completeTrip($tripId, 50150, 150, '17:00:00');
        $trip = $tripRepo->find($tripId);
        $this->assertEquals('completed', $trip['status']);
        $this->assertEquals(150, $trip['distance_km']);
        $this->assertEquals(50000, $trip['km_start']);
        $this->assertEquals(50150, $trip['km_end']);
    }

    public function testFuelAndExpenseFullWorkflow(): void
    {
        $tripRepo = new TripRepository($this->db, $this->customerA_KoordinatorContext);
        $fuelRepo = new FuelExpenseRepository($this->db, $this->customerA_DriverContext);
        $coordinatorFuelRepo = new FuelExpenseRepository($this->db, $this->customerA_KoordinatorContext);

        $tripId = $tripRepo->create([
            'customer_id' => 1,
            'vehicle_id' => 1,
            'driver_id' => 8,
            'trip_number' => 'TRP-FE-2026-001',
            'origin' => 'Makassar',
            'destination' => 'Parepare',
            'trip_date' => '2026-07-10',
            'purpose_type' => 'dinas',
            'input_by' => 8,
        ]);
        $tripRepo->startTrip($tripId, 50000, '06:00:00');
        $tripRepo->completeTrip($tripId, 50800, 800, '20:00:00');

        $fuelId = $fuelRepo->createFuelReport([
            'customer_id' => 1,
            'trip_id' => $tripId,
            'vehicle_id' => 1,
            'reported_by' => 8,
            'fuel_date' => '2026-07-10',
            'fuel_type' => 'pertalite',
            'liters' => 20,
            'price_per_liter' => 10000,
        ]);
        $this->assertEquals('pending', $fuelRepo->findFuelReport($fuelId)['status']);

        $expenseId = $fuelRepo->createExpenseReport([
            'customer_id' => 1,
            'trip_id' => $tripId,
            'vehicle_id' => 1,
            'reported_by' => 8,
            'expense_date' => '2026-07-10',
            'category' => 'tol',
            'description' => 'Tol Trans Sulawesi',
            'amount' => 50000,
        ]);
        $this->assertEquals('pending', $fuelRepo->findExpenseReport($expenseId)['status']);

        $coordinatorFuelRepo->approveFuelReport($fuelId, 7);
        $this->assertEquals('approved', $fuelRepo->findFuelReport($fuelId)['status']);

        $coordinatorFuelRepo->approveExpenseReport($expenseId, 7);
        $this->assertEquals('approved', $fuelRepo->findExpenseReport($expenseId)['status']);
    }

    public function testIssueReportWithResolveWorkflow(): void
    {
        $driverRepo = new DriverSelfServiceRepository($this->db, $this->customerA_DriverContext);
        $coordinatorRepo = new DriverSelfServiceRepository($this->db, $this->customerA_KoordinatorContext);

        $issueId = $driverRepo->createIssue([
            'customer_id' => 1,
            'vehicle_id' => 1,
            'report_number' => 'ISS-WF-2026-001',
            'reported_by' => 8,
            'category' => 'ac_kelistrikan',
            'description' => 'AC tidak dingin',
            'severity' => 'medium',
        ]);
        $this->assertGreaterThan(0, $issueId);

        $coordinatorRepo->updateIssueStatus($issueId, 'in_review');
        $issues = $coordinatorRepo->findIssues(['status' => 'in_review']);
        $this->assertNotEmpty($issues);

        $coordinatorRepo->updateIssueStatus($issueId, 'resolved', 'AC sudah diperbaiki di bengkel');
        $resolvedIssues = $coordinatorRepo->findIssues(['status' => 'resolved']);
        $this->assertNotEmpty($resolvedIssues);
        $this->assertEquals('AC sudah diperbaiki di bengkel', $resolvedIssues[0]['resolved_notes']);
    }

    public function testMaintenanceScheduleAndLogWorkflow(): void
    {
        $maintRepo = new MaintenanceRepository($this->db, $this->customerA_KoordinatorContext);

        $scheduleId = $maintRepo->createSchedule([
            'customer_id' => 1,
            'vehicle_id' => 1,
            'service_type' => 'Servis 50.000 KM',
            'trigger_type' => 'km_based',
            'km_threshold' => 50000,
            'reminder_days_before' => 7,
            'created_by' => 7,
        ]);
        $this->assertEquals('scheduled', $maintRepo->find($scheduleId)['status']);

        $logId = $maintRepo->createLog([
            'schedule_id' => $scheduleId,
            'customer_id' => 1,
            'vehicle_id' => 1,
            'service_type' => 'Servis 50.000 KM',
            'service_date' => '2026-07-10',
            'km_at_service' => 50000,
            'workshop_name' => 'Auto2000',
            'cost' => 1500000,
            'next_service_km' => 60000,
            'logged_by' => 7,
        ]);
        $this->assertGreaterThan(0, $logId);

        $maintRepo->updateScheduleStatus($scheduleId, 'completed');
        $this->assertEquals('completed', $maintRepo->find($scheduleId)['status']);

        $logs = $maintRepo->findLogsByVehicle(1);
        $this->assertNotEmpty($logs);
        $this->assertEquals('Auto2000', $logs[0]['workshop_name']);
    }

    public function testChecklistAndPhotosWorkflow(): void
    {
        $tripRepo = new TripRepository($this->db, $this->customerA_KoordinatorContext);
        $dssRepo = new DriverSelfServiceRepository($this->db, $this->customerA_DriverContext);

        $tripId = $tripRepo->create([
            'customer_id' => 1,
            'vehicle_id' => 2,
            'driver_id' => 9,
            'trip_number' => 'TRP-CP-2026-001',
            'origin' => 'Makassar',
            'destination' => 'Sungguminasa',
            'trip_date' => '2026-07-10',
            'purpose_type' => 'material',
            'input_by' => 9,
        ]);

        $dssRepo->createChecklist([
            'trip_id' => $tripId,
            'check_type' => 'pre_trip',
            'submitted_by' => 9,
            'items' => [
                ['name' => 'Ban', 'status' => 'ok', 'note' => ''],
                ['name' => 'Lampu', 'status' => 'not_ok', 'note' => 'Lampu sein kiri mati'],
            ],
            'overall_condition' => 'minor_issue',
        ]);

        $dssRepo->addPhoto([
            'trip_id' => $tripId,
            'photo_type' => 'pre_trip',
            'position' => 'front',
            'file_path' => 'uploads/trip_photos/cp_front.jpg',
            'uploaded_by' => 9,
        ]);

        $tripRepo->startTrip($tripId, 75000, '07:00:00');
        $tripRepo->completeTrip($tripId, 75120, 120, '16:00:00');

        $dssRepo->createChecklist([
            'trip_id' => $tripId,
            'check_type' => 'post_trip',
            'submitted_by' => 9,
            'items' => [
                ['name' => 'Ban', 'status' => 'ok', 'note' => ''],
                ['name' => 'Bahan Bakar', 'status' => 'ok', 'note' => 'Sisa 1/4'],
            ],
            'overall_condition' => 'good',
        ]);

        $checklists = $dssRepo->findChecklistsByTrip($tripId);
        $this->assertCount(2, $checklists);

        $photos = $dssRepo->findPhotosByTrip($tripId);
        $this->assertCount(1, $photos);
    }

    public function testCrossCustomerDataIsolation(): void
    {
        $repoA = new VehicleRequestRepository($this->db, $this->customerA_KoordinatorContext);
        $repoB = new VehicleRequestRepository($this->db, $this->customerB_KoordinatorContext);

        $repoA->create([
            'customer_id' => 1,
            'request_number' => 'REQ-ISO-A-001',
            'requested_by' => 8,
            'destination' => 'Maros',
            'purpose' => 'Test isolasi',
            'departure_date' => '2026-07-10',
            'return_date' => '2026-07-10',
        ]);

        $repoB->create([
            'customer_id' => 2,
            'request_number' => 'REQ-ISO-B-001',
            'requested_by' => 12,
            'destination' => 'Pinrang',
            'purpose' => 'Test isolasi',
            'departure_date' => '2026-07-10',
            'return_date' => '2026-07-10',
        ]);

        $requestsA = $repoA->findAll();
        foreach ($requestsA as $r) {
            $this->assertEquals(1, $r['customer_id']);
        }

        $requestsB = $repoB->findAll();
        foreach ($requestsB as $r) {
            $this->assertEquals(2, $r['customer_id']);
        }
    }
}
