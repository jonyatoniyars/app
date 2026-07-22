<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
session_write_close();

require_role('ADMIN');
$user   = current_user();
$pdo    = db();
$method = $_SERVER['REQUEST_METHOD'];

// GET: List all prescriptions with filters
if ($method === 'GET') {
    $page = max(1, (int)gp('page', 1));
    $limit = min(100, max(1, (int)gp('limit', 20)));
    $offset = ($page - 1) * $limit;

    $search = gp('search', '');
    $doctorId = gp('doctorId', '');
    $status = gp('status', '');
    $startDate = gp('startDate', '');
    $endDate = gp('endDate', '');

    $where = [];
    $params = [];

    if ($search) {
        $where[] = "(p.patient_name LIKE :search OR p.chief_complaints LIKE :search OR p.id LIKE :search)";
        $params[':search'] = "%$search%";
    }

    if ($doctorId) {
        $where[] = "p.doctor_id = :docId";
        $params[':docId'] = $doctorId;
    }

    if ($status && in_array($status, ['DRAFT', 'SUBMITTED', 'REVIEWED'])) {
        $where[] = "p.status = :status";
        $params[':status'] = $status;
    }

    if ($startDate) {
        $where[] = "DATE(p.created_at) >= :startDate";
        $params[':startDate'] = $startDate;
    }

    if ($endDate) {
        $where[] = "DATE(p.created_at) <= :endDate";
        $params[':endDate'] = $endDate;
    }

    $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Get total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM prescriptions p $whereStr");
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];

    // Get prescriptions
    $sql = "SELECT p.id, p.patient_name, p.patient_age, p.patient_gender, p.chief_complaints,
            p.on_examination, p.advice, p.status, p.reviewed_at, p.review_notes, p.created_at,
            hw.id as hwId, hw.name as hwName,
            d.id as docId, d.name as docName,
            rev.id as revId, rev.name as revName
            FROM prescriptions p
            JOIN users hw ON hw.id = p.health_worker_id
            LEFT JOIN users d ON d.id = p.doctor_id
            LEFT JOIN users rev ON rev.id = p.reviewed_by_id
            $whereStr
            ORDER BY p.created_at DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params, [':limit' => $limit, ':offset' => $offset]));
    $prescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function($p) {
        return [
            'id' => $p['id'],
            'patientName' => $p['patient_name'],
            'patientAge' => (int)$p['patient_age'],
            'patientGender' => $p['patient_gender'],
            'chiefComplaints' => $p['chief_complaints'],
            'onExamination' => $p['on_examination'],
            'advice' => $p['advice'],
            'status' => $p['status'],
            'healthWorker' => ['id' => $p['hwId'], 'name' => $p['hwName']],
            'doctor' => $p['docId'] ? ['id' => $p['docId'], 'name' => $p['docName']] : null,
            'reviewedBy' => $p['revId'] ? ['id' => $p['revId'], 'name' => $p['revName']] : null,
            'reviewedAt' => $p['reviewed_at'],
            'reviewNotes' => $p['review_notes'],
            'createdAt' => $p['created_at']
        ];
    }, $prescriptions);

    $totalPages = ceil($total / $limit);
    json_ok($data, 200, [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'totalPages' => $totalPages
    ]);
}

// GET: Dashboard statistics
if (gp('action') === 'statistics') {
    // Total prescriptions
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM prescriptions");
    $stmt->execute();
    $totalRx = $stmt->fetch()['total'];

    // By status
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM prescriptions GROUP BY status");
    $stmt->execute();
    $byStatus = [];
    foreach ($stmt->fetchAll() as $row) {
        $byStatus[$row['status']] = $row['count'];
    }

    // Pending review count
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM prescriptions WHERE status = 'SUBMITTED'");
    $stmt->execute();
    $pendingReview = $stmt->fetch()['total'];

    // Active users count
    $stmt = $pdo->prepare("SELECT role, COUNT(*) as count FROM users WHERE status = 'ACTIVE' GROUP BY role");
    $stmt->execute();
    $activeUsers = [];
    foreach ($stmt->fetchAll() as $row) {
        $activeUsers[$row['role']] = $row['count'];
    }

    // Suspended users
    $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE status = 'SUSPENDED'");
    $stmt->execute();
    $suspendedUsers = $stmt->fetch()['total'];

    json_ok([
        'totalPrescriptions' => $totalRx,
        'byStatus' => $byStatus,
        'pendingReview' => $pendingReview,
        'activeUsers' => $activeUsers,
        'suspendedUsers' => $suspendedUsers,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

// PATCH: Admin override - update prescription assignment
if ($method === 'PATCH' && gp('action') === 'assign-doctor') {
    $body = json_body();
    $prescriptionId = $body['prescriptionId'] ?? null;
    $doctorId = $body['doctorId'] ?? null;

    if (!$prescriptionId || !$doctorId) {
        json_error('BAD_REQUEST', 'prescriptionId and doctorId required');
    }

    $pStmt = $pdo->prepare("SELECT id FROM prescriptions WHERE id = :id");
    $pStmt->execute([':id' => $prescriptionId]);
    if (!$pStmt->fetch()) json_error('NOT_FOUND', 'Prescription not found');

    $dStmt = $pdo->prepare("SELECT id FROM users WHERE id = :id AND role = 'DOCTOR'");
    $dStmt->execute([':id' => $doctorId]);
    if (!$dStmt->fetch()) json_error('NOT_FOUND', 'Doctor not found');

    $pdo->prepare("UPDATE prescriptions SET doctor_id = :docId, updated_at = NOW() WHERE id = :id")
        ->execute([':docId' => $doctorId, ':id' => $prescriptionId]);

    log_audit($pdo, $user['id'], 'ADMIN_ASSIGN_DOCTOR', 'prescription', $prescriptionId);

    json_ok(['prescriptionId' => $prescriptionId, 'assigned' => true]);
}

// GET: Export prescriptions (CSV or JSON)
if (gp('action') === 'export') {
    $format = gp('format', 'json'); // json or csv
    $doctorId = gp('doctorId', '');
    $status = gp('status', '');

    $where = [];
    $params = [];

    if ($doctorId) {
        $where[] = "p.doctor_id = :docId";
        $params[':docId'] = $doctorId;
    }

    if ($status) {
        $where[] = "p.status = :status";
        $params[':status'] = $status;
    }

    $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $sql = "SELECT p.id, p.patient_name, p.patient_age, p.patient_gender, p.chief_complaints,
            p.status, p.created_at, hw.name as hwName, d.name as docName
            FROM prescriptions p
            JOIN users hw ON hw.id = p.health_worker_id
            LEFT JOIN users d ON d.id = p.doctor_id
            $whereStr
            ORDER BY p.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="prescriptions_export_' . date('Y-m-d') . '.csv"');

        $output = fopen('php://output', 'w');
        fputcsv($output, ['ID', 'Patient Name', 'Age', 'Gender', 'Chief Complaints', 'Status', 'Health Worker', 'Doctor', 'Created At']);

        foreach ($results as $row) {
            fputcsv($output, [
                $row['id'],
                $row['patient_name'],
                $row['patient_age'],
                $row['patient_gender'],
                $row['chief_complaints'],
                $row['status'],
                $row['hwName'],
                $row['docName'] ?? 'N/A',
                $row['created_at']
            ]);
        }
        fclose($output);
        exit;
    } else {
        json_ok($results);
    }
}

function log_audit(PDO $pdo, string $adminId, string $action, string $entityType, string $entityId): void {
    $logId = bin2hex(random_bytes(8));
    $pdo->prepare("INSERT INTO audit_logs (id, admin_id, action, target_entity_type, target_entity_id) 
        VALUES (:id, :aid, :act, :ent, :eid)")
        ->execute([
            ':id' => $logId,
            ':aid' => $adminId,
            ':act' => $action,
            ':ent' => $entityType,
            ':eid' => $entityId
        ]);
}
