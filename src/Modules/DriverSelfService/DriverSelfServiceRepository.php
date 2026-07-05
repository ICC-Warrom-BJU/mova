<?php

class DriverSelfServiceRepository extends BaseRepository
{
    protected string $table = 'mova_trip_checklists';

    public function findChecklistsByTrip(int $tripId): array
    {
        $stmt = $this->db->prepare(
            "SELECT tc.*, u.name AS submitted_by_name
             FROM mova_trip_checklists tc
             JOIN mova_users u ON u.id = tc.submitted_by
             WHERE tc.trip_id = ?
             ORDER BY tc.submitted_at DESC"
        );
        $stmt->execute([$tripId]);
        return $stmt->fetchAll();
    }

    public function createChecklist(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO mova_trip_checklists (trip_id, check_type, submitted_by, items, overall_condition, notes)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['trip_id'],
            $data['check_type'],
            $data['submitted_by'],
            json_encode($data['items']),
            $data['overall_condition'],
            $data['notes'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function findPhotosByTrip(int $tripId): array
    {
        $stmt = $this->db->prepare(
            "SELECT tp.*, u.name AS uploaded_by_name
             FROM mova_trip_photos tp
             JOIN mova_users u ON u.id = tp.uploaded_by
             WHERE tp.trip_id = ?
             ORDER BY tp.uploaded_at ASC"
        );
        $stmt->execute([$tripId]);
        return $stmt->fetchAll();
    }

    public function addPhoto(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO mova_trip_photos (trip_id, photo_type, position, file_path, uploaded_by)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['trip_id'],
            $data['photo_type'],
            $data['position'],
            $data['file_path'],
            $data['uploaded_by'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function findIssues(array $filters = []): array
    {
        $sql = "SELECT ir.*, v.plate_number, u.name AS reported_by_name
                FROM mova_issue_reports ir
                JOIN mova_vehicles v ON v.id = ir.vehicle_id
                JOIN mova_users u ON u.id = ir.reported_by
                WHERE 1=1";
        $params = [];

        if (!empty($filters['customer_id'])) {
            $sql .= " AND ir.customer_id = ?";
            $params[] = $filters['customer_id'];
        } elseif (!$this->tenant->isSuperAdmin()) {
            $ids = $this->tenant->getAccessibleCustomerIds();
            if (empty($ids)) return [];
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $sql .= " AND ir.customer_id IN ($ph)";
            $params = array_merge($params, $ids);
        }

        if (!empty($filters['status'])) {
            $sql .= " AND ir.status = ?";
            $params[] = $filters['status'];
        }

        if (!empty($filters['severity'])) {
            $sql .= " AND ir.severity = ?";
            $params[] = $filters['severity'];
        }

        $sql .= " ORDER BY ir.created_at DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function createIssue(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO mova_issue_reports (customer_id, vehicle_id, trip_id, report_number,
             reported_by, category, description, severity, photo_paths)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $data['customer_id'],
            $data['vehicle_id'],
            $data['trip_id'] ?? null,
            $data['report_number'],
            $data['reported_by'],
            $data['category'],
            $data['description'],
            $data['severity'],
            isset($data['photo_paths']) ? json_encode($data['photo_paths']) : null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateIssueStatus(int $id, string $status, ?string $resolvedNotes = null): int
    {
        $data = ['status' => $status];
        if ($status === 'resolved' || $status === 'closed') {
            $data['resolved_at'] = date('Y-m-d H:i:s');
            $data['resolved_notes'] = $resolvedNotes;
        }
        return $this->scopedUpdate($data, 'id = ?', [$id]);
    }
}
