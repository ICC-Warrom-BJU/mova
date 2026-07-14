<?php

require_once __DIR__ . '/../BaseTestCase.php';

class DriverSelfServiceRepositoryTest extends BaseTestCase
{
    private DriverSelfServiceRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new DriverSelfServiceRepository($this->db, $this->customerA_KoordinatorContext);
        $this->ensureTripExists();
    }

    public function testCreateChecklist(): void
    {
        $id = $this->repo->createChecklist([
            'trip_id' => 1,
            'check_type' => 'pre_trip',
            'submitted_by' => 8,
            'items' => [
                ['name' => 'Ban & Roda', 'status' => 'ok', 'note' => ''],
                ['name' => 'Lampu', 'status' => 'ok', 'note' => ''],
                ['name' => 'Kaca & Spion', 'status' => 'not_ok', 'note' => 'Spion retak'],
            ],
            'overall_condition' => 'minor_issue',
            'notes' => 'Spion perlu diganti',
        ]);

        $this->assertGreaterThan(0, $id);

        $checklists = $this->repo->findChecklistsByTrip(1);
        $this->assertCount(1, $checklists);
        $this->assertEquals('pre_trip', $checklists[0]['check_type']);
        $this->assertEquals('minor_issue', $checklists[0]['overall_condition']);

        $items = json_decode($checklists[0]['items'], true);
        $this->assertCount(3, $items);
        $this->assertEquals('Spion retak', $items[2]['note']);
    }

    public function testCreatePostTripChecklist(): void
    {
        $this->repo->createChecklist([
            'trip_id' => 1,
            'check_type' => 'post_trip',
            'submitted_by' => 8,
            'items' => [
                ['name' => 'Ban & Roda', 'status' => 'ok', 'note' => ''],
                ['name' => 'Bahan Bakar', 'status' => 'ok', 'note' => '1/2 tank'],
            ],
            'overall_condition' => 'good',
        ]);

        $checklists = $this->repo->findChecklistsByTrip(1);
        $postTrip = array_values(array_filter($checklists, fn($c) => $c['check_type'] === 'post_trip'));
        $this->assertCount(1, $postTrip);
        $this->assertEquals('good', $postTrip[0]['overall_condition']);
    }

    public function testCreateIssue(): void
    {
        $id = $this->repo->createIssue([
            'customer_id' => 1,
            'vehicle_id' => 1,
            'report_number' => 'ISS-2026-0001',
            'reported_by' => 8,
            'category' => 'mesin',
            'description' => 'Mesin overheat setelah perjalanan jauh',
            'severity' => 'high',
        ]);

        $this->assertGreaterThan(0, $id);

        $issues = $this->repo->findIssues();
        $this->assertNotEmpty($issues);
        $this->assertEquals('mesin', $issues[0]['category']);
        $this->assertEquals('high', $issues[0]['severity']);
        $this->assertEquals('open', $issues[0]['status']);
    }

    public function testUpdateIssueStatus(): void
    {
        $id = $this->repo->createIssue([
            'customer_id' => 1,
            'vehicle_id' => 1,
            'report_number' => 'ISS-2026-0002',
            'reported_by' => 8,
            'category' => 'ban',
            'description' => 'Ban kempes',
            'severity' => 'medium',
        ]);

        $affected = $this->repo->updateIssueStatus($id, 'resolved', 'Ban sudah diganti');
        $this->assertEquals(1, $affected);

        $issues = $this->repo->findIssues();
        $this->assertEquals('resolved', $issues[0]['status']);
        $this->assertNotNull($issues[0]['resolved_at']);
    }

    public function testAddPhoto(): void
    {
        $id = $this->repo->addPhoto([
            'trip_id' => 1,
            'photo_type' => 'pre_trip',
            'position' => 'front',
            'file_path' => 'uploads/trip_photos/test_front.jpg',
            'uploaded_by' => 8,
        ]);

        $this->assertGreaterThan(0, $id);

        $photos = $this->repo->findPhotosByTrip(1);
        $this->assertCount(1, $photos);
        $this->assertEquals('front', $photos[0]['position']);
    }

    public function testFindIssuesWithStatusFilter(): void
    {
        $this->repo->createIssue([
            'customer_id' => 1,
            'vehicle_id' => 1,
            'report_number' => 'ISS-2026-FLT-001',
            'reported_by' => 8,
            'category' => 'body',
            'description' => 'Body penyok',
            'severity' => 'low',
        ]);

        $this->repo->createIssue([
            'customer_id' => 1,
            'vehicle_id' => 1,
            'report_number' => 'ISS-2026-FLT-002',
            'reported_by' => 8,
            'category' => 'mesin',
            'description' => 'Mesin bermasalah',
            'severity' => 'high',
        ]);

        $openIssues = $this->repo->findIssues(['status' => 'open']);
        $this->assertCount(2, $openIssues);
    }

    public function testFindIssuesWithDateFilter(): void
    {
        $this->repo->createIssue([
            'customer_id' => 1,
            'vehicle_id' => 1,
            'report_number' => 'ISS-2026-DT-001',
            'reported_by' => 8,
            'category' => 'lainnya',
            'description' => 'Test date filter',
            'severity' => 'low',
        ]);

        $issues = $this->repo->findIssues([
            'date_start' => '2026-07-01',
            'date_end' => '2026-07-31',
        ]);

        $this->assertNotEmpty($issues);
    }

    public function testIssueTenantIsolation(): void
    {
        $this->repo->createIssue([
            'customer_id' => 1,
            'vehicle_id' => 1,
            'report_number' => 'ISS-2026-ISO-001',
            'reported_by' => 8,
            'category' => 'ban',
            'description' => 'Test isolation',
            'severity' => 'low',
        ]);

        $repoB = new DriverSelfServiceRepository($this->db, $this->customerB_KoordinatorContext);
        $issuesB = $repoB->findIssues();
        $this->assertEmpty($issuesB);
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
                'trip_number' => 'TRP-DSS-BASE-001',
                'origin' => 'Makassar',
                'destination' => 'Maros',
                'trip_date' => '2026-07-08',
                'purpose_type' => 'dinas',
                'input_by' => 7,
            ]);
        }
    }
}
