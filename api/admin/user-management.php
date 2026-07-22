<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
session_write_close();

require_role('ADMIN');
$user   = current_user();
$pdo    = db();
$method = $_SERVER['REQUEST_METHOD'];
$action = gp('action', '');

// GET: List users by type
if ($method === 'GET' && !$action) {
    $type = gp('type', 'all'); // all, doctors, health_workers, suspended
    $search = gp('search', '');
    $page = max(1, (int)gp('page', 1));
    $limit = min(50, max(1, (int)gp('limit', 20)));
    $offset = ($page - 1) * $limit;

    $where = [];
    $params = [];

    if ($type === 'doctors') {
        $where[] = "u.role = 'DOCTOR'";
    } elseif ($type === 'health_workers') {
        $where[] = "u.role = 'HEALTH_WORKER'";
    } elseif ($type === 'suspended') {
        $where[] = "u.status = 'SUSPENDED'";
    }

    if ($search) {
        $where[] = "(u.name LIKE :search OR u.email LIKE :search OR u.phone LIKE :search)";
        $params[':search'] = "%$search%";
    }

    $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    // Get total count
    $countStmt = $pdo->prepare("SELECT COUNT(*) as total FROM users u $whereStr");
    $countStmt->execute($params);
    $total = $countStmt->fetch()['total'];

    // Get paginated results
    $sql = "SELECT u.id, u.name, u.email, u.phone, u.role, u.status, u.can_write_prescription, u.created_at
            FROM users u
            $whereStr
            ORDER BY u.created_at DESC
            LIMIT :limit OFFSET :offset";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params, [':limit' => $limit, ':offset' => $offset]));
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function($u) {
        return [
            'id' => $u['id'],
            'name' => $u['name'],
            'email' => $u['email'],
            'phone' => $u['phone'],
            'role' => $u['role'],
            'status' => $u['status'],
            'canWritePrescription' => (bool)$u['can_write_prescription'],
            'createdAt' => $u['created_at']
        ];
    }, $users);

    $totalPages = ceil($total / $limit);
    json_ok($data, 200, [
        'page' => $page,
        'limit' => $limit,
        'total' => $total,
        'totalPages' => $totalPages
    ]);
}

// GET: Get user details
if ($method === 'GET' && $id = gp('id')) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $userDetails = $stmt->fetch();

    if (!$userDetails) json_error('NOT_FOUND', 'User not found', 404);

    // Get assignments if doctor or health worker
    $assignments = [];
    if ($userDetails['role'] === 'DOCTOR') {
        $aStmt = $pdo->prepare("
            SELECT hw.id, hw.name, hw.email, hw.phone, hw.status, da.assigned_at
            FROM doctor_assignments da
            JOIN users hw ON hw.id = da.health_worker_id
            WHERE da.doctor_id = :id
        ");
        $aStmt->execute([':id' => $id]);
        $assignments = $aStmt->fetchAll(PDO::FETCH_ASSOC);
    } elseif ($userDetails['role'] === 'HEALTH_WORKER') {
        $aStmt = $pdo->prepare("
            SELECT d.id, d.name, d.email, d.phone, da.assigned_at
            FROM doctor_assignments da
            JOIN users d ON d.id = da.doctor_id
            WHERE da.health_worker_id = :id
        ");
        $aStmt->execute([':id' => $id]);
        $assignments = $aStmt->fetchAll(PDO::FETCH_ASSOC);
    }

    json_ok([
        'id' => $userDetails['id'],
        'name' => $userDetails['name'],
        'email' => $userDetails['email'],
        'phone' => $userDetails['phone'],
        'role' => $userDetails['role'],
        'status' => $userDetails['status'],
        'canWritePrescription' => (bool)$userDetails['can_write_prescription'],
        'createdAt' => $userDetails['created_at'],
        'updatedAt' => $userDetails['updated_at'],
        'assignments' => $assignments
    ]);
}

// PATCH: Update user
if ($method === 'PATCH' && $id = gp('id')) {
    $body = json_body();
    $name = $body['name'] ?? null;
    $email = $body['email'] ?? null;
    $phone = $body['phone'] ?? null;
    $status = $body['status'] ?? null;
    $canWrite = isset($body['canWritePrescription']) ? (int)$body['canWritePrescription'] : null;

    $stmt = $pdo->prepare("SELECT id FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    if (!$stmt->fetch()) json_error('NOT_FOUND', 'User not found', 404);

    $updates = [];
    $params = [':id' => $id];

    if ($name) {
        $updates[] = "name = :name";
        $params[':name'] = $name;
    }
    if ($email) {
        $updates[] = "email = :email";
        $params[':email'] = $email;
    }
    if ($phone) {
        $updates[] = "phone = :phone";
        $params[':phone'] = $phone;
    }
    if ($status && in_array($status, ['ACTIVE', 'SUSPENDED', 'PENDING'])) {
        $updates[] = "status = :status";
        $params[':status'] = $status;
    }
    if ($canWrite !== null) {
        $updates[] = "can_write_prescription = :cwp";
        $params[':cwp'] = $canWrite;
    }

    if (empty($updates)) {
        json_error('BAD_REQUEST', 'No fields to update');
    }

    $updates[] = "updated_at = NOW()";
    $updateStr = implode(', ', $updates);
    $sql = "UPDATE users SET $updateStr WHERE id = :id";

    $pdo->prepare($sql)->execute($params);

    // Log audit
    log_audit($pdo, $user['id'], 'UPDATE_USER', 'user', $id);

    json_ok(['id' => $id, 'updated' => true]);
}

// POST: Assign doctor to health worker
if ($method === 'POST' && $action === 'assign') {
    $body = json_body();
    $doctorId = $body['doctorId'] ?? null;
    $healthWorkerId = $body['healthWorkerId'] ?? null;

    if (!$doctorId || !$healthWorkerId) {
        json_error('BAD_REQUEST', 'doctorId and healthWorkerId required');
    }

    // Verify users exist
    $dStmt = $pdo->prepare("SELECT id FROM users WHERE id = :id AND role = 'DOCTOR'");
    $dStmt->execute([':id' => $doctorId]);
    if (!$dStmt->fetch()) json_error('NOT_FOUND', 'Doctor not found');

    $hwStmt = $pdo->prepare("SELECT id FROM users WHERE id = :id AND role = 'HEALTH_WORKER'");
    $hwStmt->execute([':id' => $healthWorkerId]);
    if (!$hwStmt->fetch()) json_error('NOT_FOUND', 'Health worker not found');

    // Check if already assigned
    $checkStmt = $pdo->prepare("SELECT id FROM doctor_assignments WHERE doctor_id = :did AND health_worker_id = :hwid");
    $checkStmt->execute([':did' => $doctorId, ':hwid' => $healthWorkerId]);
    if ($checkStmt->fetch()) {
        json_error('BAD_REQUEST', 'Already assigned');
    }

    $assignId = bin2hex(random_bytes(8));
    $pdo->prepare("INSERT INTO doctor_assignments (id, doctor_id, health_worker_id) 
        VALUES (:id, :did, :hwid)")
        ->execute([':id' => $assignId, ':did' => $doctorId, ':hwid' => $healthWorkerId]);

    log_audit($pdo, $user['id'], 'ASSIGN_WORKER', 'doctor_assignment', $assignId);

    json_ok(['assignmentId' => $assignId, 'assigned' => true], 201);
}

// DELETE: Remove assignment
if ($method === 'DELETE' && $action === 'unassign' && $id = gp('assignmentId')) {
    $stmt = $pdo->prepare("SELECT id FROM doctor_assignments WHERE id = :id");
    $stmt->execute([':id' => $id]);
    if (!$stmt->fetch()) json_error('NOT_FOUND', 'Assignment not found');

    $pdo->prepare("DELETE FROM doctor_assignments WHERE id = :id")->execute([':id' => $id]);
    log_audit($pdo, $user['id'], 'UNASSIGN_WORKER', 'doctor_assignment', $id);

    json_ok(['deleted' => true]);
}

// POST: Suspend/Unsuspend user
if ($method === 'POST' && $action === 'toggle-suspend' && $id = gp('userId')) {
    $stmt = $pdo->prepare("SELECT status FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $userRecord = $stmt->fetch();

    if (!$userRecord) json_error('NOT_FOUND', 'User not found');

    $newStatus = $userRecord['status'] === 'SUSPENDED' ? 'ACTIVE' : 'SUSPENDED';
    $pdo->prepare("UPDATE users SET status = :status, updated_at = NOW() WHERE id = :id")
        ->execute([':status' => $newStatus, ':id' => $id]);

    log_audit($pdo, $user['id'], 'TOGGLE_SUSPEND', 'user', $id);

    json_ok(['id' => $id, 'newStatus' => $newStatus]);
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
