<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
session_write_close();

$user = require_role('DOCTOR');
$pdo  = db();
$method = $_SERVER['REQUEST_METHOD'];

// GET: Fetch workers assigned to this doctor
if ($method === 'GET') {
    $s = $pdo->prepare("
        SELECT w.id, w.name, w.email, w.phone, w.status, da.assigned_at
        FROM users w
        JOIN doctor_assignments da ON w.id = da.health_worker_id
        WHERE da.doctor_id = :doctor_id AND w.role = 'HEALTH_WORKER'
        ORDER BY da.assigned_at DESC
    ");
    $s->execute([':doctor_id' => $user['id']]);
    $workers = $s->fetchAll(PDO::FETCH_ASSOC);

    // Format response
    $result = array_map(function($w) use ($user) {
        return [
            'id' => $w['id'],
            'name' => $w['name'],
            'email' => $w['email'],
            'phone' => $w['phone'],
            'status' => $w['status'],
            'assignedAt' => $w['assigned_at'],
            'doctorId' => $user['id'],
            'doctorName' => $user['name']
        ];
    }, $workers);

    json_ok($result, 200, ['total' => count($workers)]);
}

// POST: Get worker details with prescriptions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = json_body();
    $workerId = $body['workerId'] ?? null;

    if (!$workerId) {
        json_error('BAD_REQUEST', 'workerId required');
    }

    // Verify worker is assigned to this doctor
    $check = $pdo->prepare("
        SELECT w.id FROM users w
        JOIN doctor_assignments da ON w.id = da.health_worker_id
        WHERE da.doctor_id = :doctor_id AND w.id = :worker_id
    ");
    $check->execute([':doctor_id' => $user['id'], ':worker_id' => $workerId]);
    if (!$check->fetch()) {
        json_error('FORBIDDEN', 'Worker not assigned to you', 403);
    }

    // Get worker's recent prescriptions
    $rxStmt = $pdo->prepare("
        SELECT id, patient_name, status, created_at 
        FROM prescriptions 
        WHERE health_worker_id = :worker_id
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $rxStmt->execute([':worker_id' => $workerId]);
    $prescriptions = $rxStmt->fetchAll(PDO::FETCH_ASSOC);

    json_ok(['prescriptions' => $prescriptions]);
}
