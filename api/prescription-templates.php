<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
session_write_close();

$user   = require_auth();
$pdo    = db();
$method = $_SERVER['REQUEST_METHOD'];
$id     = gp('id');

// GET: List prescription templates
if ($method === 'GET' && !$id) {
    $role = $user['role'];
    $where = [];
    $params = [];

    // Health Workers and Doctors see only approved templates
    if ($role !== 'ADMIN') {
        $where[] = "pt.status = 'APPROVED'";
    }

    $whereStr = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $sql = "SELECT pt.id, pt.name, pt.description, pt.status, pt.created_by_id, 
            pt.approved_by_id, pt.approved_at, pt.created_at,
            creator.name AS creatorName, approver.name AS approverName
            FROM prescription_templates pt
            JOIN users creator ON creator.id = pt.created_by_id
            LEFT JOIN users approver ON approver.id = pt.approved_by_id
            $whereStr
            ORDER BY pt.created_at DESC LIMIT 100";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function ($row) {
        return [
            'id' => $row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'status' => $row['status'],
            'createdBy' => ['id' => $row['created_by_id'], 'name' => $row['creatorName']],
            'approvedBy' => $row['approver_name'] ? ['id' => $row['approved_by_id'], 'name' => $row['approverName']] : null,
            'approvedAt' => $row['approved_at'],
            'createdAt' => $row['created_at']
        ];
    }, $results);

    json_ok($data);
}

// GET: Single template with full content
if ($method === 'GET' && $id) {
    $sql = "SELECT pt.* FROM prescription_templates pt WHERE pt.id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $result = $stmt->fetch();

    if (!$result) json_error('NOT_FOUND', 'Template not found', 404);

    // Check access
    $role = $user['role'];
    if ($role !== 'ADMIN' && $result['status'] !== 'APPROVED' && $result['created_by_id'] !== $user['id']) {
        json_error('FORBIDDEN', 'Cannot access this template', 403);
    }

    json_ok([
        'id' => $result['id'],
        'name' => $result['name'],
        'description' => $result['description'],
        'templateContent' => json_decode($result['template_content'], true),
        'status' => $result['status'],
        'createdById' => $result['created_by_id'],
        'approvalNotes' => $result['approval_notes'],
        'createdAt' => $result['created_at'],
        'updatedAt' => $result['updated_at']
    ]);
}

// POST: Create new template (Doctors/Health Workers)
if ($method === 'POST') {
    if ($user['role'] === 'HEALTH_WORKER') {
        json_error('FORBIDDEN', 'Only Doctors and Admins can create templates', 403);
    }

    $body = json_body();
    $name = $body['name'] ?? null;
    $description = $body['description'] ?? '';
    $templateContent = $body['templateContent'] ?? null;

    if (!$name || !$templateContent) {
        json_error('BAD_REQUEST', 'name and templateContent are required');
    }

    $templateId = bin2hex(random_bytes(8));
    $status = $user['role'] === 'ADMIN' ? 'APPROVED' : 'PENDING_APPROVAL';

    $stmt = $pdo->prepare("INSERT INTO prescription_templates 
        (id, name, description, template_content, created_by_id, status) 
        VALUES (:id, :name, :desc, :content, :creator, :status)");

    $stmt->execute([
        ':id' => $templateId,
        ':name' => $name,
        ':desc' => $description,
        ':content' => json_encode($templateContent),
        ':creator' => $user['id'],
        ':status' => $status
    ]);

    // Log audit
    log_audit($pdo, $user['id'], 'CREATE_TEMPLATE', 'prescription_template', $templateId);

    json_ok([
        'id' => $templateId,
        'status' => $status,
        'message' => $status === 'PENDING_APPROVAL' ? 'Template submitted for approval' : 'Template created and approved'
    ], 201);
}

// PATCH: Update template (creator or admin)
if ($method === 'PATCH' && $id) {
    $sql = "SELECT * FROM prescription_templates WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);
    $template = $stmt->fetch();

    if (!$template) json_error('NOT_FOUND', 'Template not found', 404);

    // Check permission
    if ($user['role'] !== 'ADMIN' && $template['created_by_id'] !== $user['id']) {
        json_error('FORBIDDEN', 'Cannot modify this template', 403);
    }

    $body = json_body();
    $name = $body['name'] ?? $template['name'];
    $description = $body['description'] ?? $template['description'];
    $templateContent = $body['templateContent'] ?? json_decode($template['template_content'], true);

    $updateStmt = $pdo->prepare("UPDATE prescription_templates 
        SET name = :name, description = :desc, template_content = :content, updated_at = NOW()
        WHERE id = :id");

    $updateStmt->execute([
        ':name' => $name,
        ':desc' => $description,
        ':content' => json_encode($templateContent),
        ':id' => $id
    ]);

    log_audit($pdo, $user['id'], 'UPDATE_TEMPLATE', 'prescription_template', $id);

    json_ok(['id' => $id, 'updated' => true]);
}

// POST: Approve/Reject template (Admin only)
if ($method === 'POST' && gp('action') === 'review') {
    require_role('ADMIN');

    $body = json_body();
    $templateId = $body['templateId'] ?? null;
    $approved = $body['approved'] ?? false;
    $approvalNotes = $body['approvalNotes'] ?? '';

    if (!$templateId) json_error('BAD_REQUEST', 'templateId required');

    $sql = "SELECT * FROM prescription_templates WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $templateId]);
    $template = $stmt->fetch();

    if (!$template) json_error('NOT_FOUND', 'Template not found', 404);

    $newStatus = $approved ? 'APPROVED' : 'REJECTED';
    $updateStmt = $pdo->prepare("UPDATE prescription_templates 
        SET status = :status, approved_by_id = :adminId, approved_at = NOW(), approval_notes = :notes, updated_at = NOW()
        WHERE id = :id");

    $updateStmt->execute([
        ':status' => $newStatus,
        ':adminId' => $user['id'],
        ':notes' => $approvalNotes,
        ':id' => $templateId
    ]);

    log_audit($pdo, $user['id'], 'REVIEW_TEMPLATE', 'prescription_template', $templateId);

    json_ok(['id' => $templateId, 'status' => $newStatus, 'message' => 'Template ' . strtolower($newStatus)]);
}

// DELETE: Remove template (Admin only)
if ($method === 'DELETE' && $id) {
    require_role('ADMIN');

    $sql = "SELECT id FROM prescription_templates WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id]);

    if (!$stmt->fetch()) json_error('NOT_FOUND', 'Template not found', 404);

    $pdo->prepare("DELETE FROM prescription_templates WHERE id = :id")->execute([':id' => $id]);
    log_audit($pdo, $user['id'], 'DELETE_TEMPLATE', 'prescription_template', $id);

    json_ok(['deleted' => true]);
}

function log_audit(PDO $pdo, string $userId, string $action, string $entityType, string $entityId): void {
    $logId = bin2hex(random_bytes(8));
    $pdo->prepare("INSERT INTO audit_logs (id, admin_id, action, target_entity_type, target_entity_id) 
        VALUES (:id, :uid, :act, :ent, :eid)")
        ->execute([':id' => $logId, ':uid' => $userId, ':act' => $action, ':ent' => $entityType, ':eid' => $entityId]);
}
