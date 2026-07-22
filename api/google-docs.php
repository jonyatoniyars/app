<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
session_write_close();

$user   = require_auth();
$pdo    = db();
$method = $_SERVER['REQUEST_METHOD'];
$id     = gp('id');

// Google OAuth 2.0 Config (must be set in environment or .env file)
define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID'] ?? 'your-client-id.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? 'your-secret');
define('GOOGLE_REDIRECT_URI', 'https://' . $_SERVER['HTTP_HOST'] . '/api/google-docs.php?action=callback');

// GET: List Google Docs integrations for prescriptions
if ($method === 'GET' && !$id) {
    $role = $user['role'];
    $where = [];
    $params = [];

    if ($role === 'HEALTH_WORKER') {
        $where[] = "p.health_worker_id = :uid";
        $params[':uid'] = $user['id'];
    } elseif ($role === 'DOCTOR') {
        $where[] = "p.doctor_id = :uid OR p.reviewed_by_id = :uid";
        $params[':uid'] = $user['id'];
    }

    $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT gd.id, gd.prescription_id, gd.google_doc_id, gd.google_doc_url, 
            gd.document_title, gd.last_synced_at,
            p.id AS prescriptionId, p.patient_name, p.status,
            hw.name AS hwName, d.name AS doctorName
            FROM google_docs_integrations gd
            JOIN prescriptions p ON p.id = gd.prescription_id
            JOIN users hw ON hw.id = p.health_worker_id
            LEFT JOIN users d ON d.id = p.doctor_id
            $whereStr";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function ($row) {
        return [
            'id' => $row['id'],
            'prescriptionId' => $row['prescriptionId'],
            'prescription' => [
                'id' => $row['prescriptionId'],
                'patientName' => $row['patient_name'],
                'status' => $row['status'],
                'healthWorker' => ['name' => $row['hwName']],
                'doctor' => $row['doctorName'] ? ['name' => $row['doctorName']] : null
            ],
            'googleDocId' => $row['google_doc_id'],
            'googleDocUrl' => $row['google_doc_url'],
            'documentTitle' => $row['document_title'],
            'lastSyncedAt' => $row['last_synced_at']
        ];
    }, $results);

    json_ok($data);
}

// GET: Single Google Doc integration
if ($method === 'GET' && $id) {
    $sql = "SELECT gd.*, p.id as px FROM google_docs_integrations gd
            JOIN prescriptions p ON p.id = gd.prescription_id
            WHERE gd.id = :id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $result = $stmt->fetch();

    if (!$result) json_error('NOT_FOUND', 'Integration not found', 404);
    json_ok($result, 200);
}

// POST: Create Google Docs integration
if ($method === 'POST') {
    $body = json_body();
    $prescriptionId = $body['prescriptionId'] ?? null;

    if (!$prescriptionId) json_error('BAD_REQUEST', 'prescriptionId required');

    // Verify prescription exists and user has access
    $pCheck = $pdo->prepare("SELECT id, health_worker_id, doctor_id FROM prescriptions WHERE id = :id");
    $pCheck->execute([':id' => $prescriptionId]);
    $prescription = $pCheck->fetch();

    if (!$prescription) json_error('NOT_FOUND', 'Prescription not found');

    // Check access
    if ($user['role'] === 'HEALTH_WORKER' && $prescription['health_worker_id'] !== $user['id']) {
        json_error('FORBIDDEN', 'Cannot create Google Docs for this prescription', 403);
    }
    if ($user['role'] === 'DOCTOR' && $prescription['doctor_id'] !== $user['id'] && $prescription['health_worker_id'] !== $user['id']) {
        json_error('FORBIDDEN', 'Cannot create Google Docs for this prescription', 403);
    }

    // Create Google Doc (simplified - in production, call Google Docs API)
    $googleDocId = bin2hex(random_bytes(16));
    $integrationId = bin2hex(random_bytes(8));
    $googleDocUrl = "https://docs.google.com/document/d/$googleDocId/edit";
    $documentTitle = "Prescription - Patient (Rx:" . substr($prescriptionId, 0, 8) . ")";

    $stmt = $pdo->prepare("INSERT INTO google_docs_integrations 
        (id, prescription_id, google_doc_id, google_doc_url, document_title) 
        VALUES (:id, :pid, :gdid, :gurl, :title)");
    
    $stmt->execute([
        ':id' => $integrationId,
        ':pid' => $prescriptionId,
        ':gdid' => $googleDocId,
        ':gurl' => $googleDocUrl,
        ':title' => $documentTitle
    ]);

    // Log audit trail
    audit_log($pdo, $user['id'], 'CREATE_GOOGLE_DOCS', 'prescription', $prescriptionId);

    json_ok(['id' => $integrationId, 'googleDocUrl' => $googleDocUrl], 201);
}

// DELETE: Remove Google Docs integration
if ($method === 'DELETE' && $id) {
    $stmt = $pdo->prepare("SELECT prescription_id FROM google_docs_integrations WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $result = $stmt->fetch();

    if (!$result) json_error('NOT_FOUND', 'Integration not found');

    // Verify access
    $pCheck = $pdo->prepare("SELECT health_worker_id, doctor_id FROM prescriptions WHERE id = :id");
    $pCheck->execute([':id' => $result['prescription_id']]);
    $prescription = $pCheck->fetch();

    if ($user['role'] !== 'ADMIN' && $user['role'] !== 'DOCTOR') {
        json_error('FORBIDDEN', 'Only Admin or Doctor can delete integrations', 403);
    }

    $pdo->prepare("DELETE FROM google_docs_integrations WHERE id = :id")->execute([':id' => $id]);
    audit_log($pdo, $user['id'], 'DELETE_GOOGLE_DOCS', 'prescription', $result['prescription_id']);

    json_ok(['deleted' => true], 200);
}

function audit_log(PDO $pdo, string $adminId, string $action, string $entityType, string $entityId): void {
    $logId = bin2hex(random_bytes(8));
    $pdo->prepare("INSERT INTO audit_logs (id, admin_id, action, target_entity_type, target_entity_id) 
        VALUES (:id, :aid, :act, :ent, :eid)")
        ->execute([':id' => $logId, ':aid' => $adminId, ':act' => $action, ':ent' => $entityType, ':eid' => $entityId]);
}
