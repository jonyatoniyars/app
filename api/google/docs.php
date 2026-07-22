<?php
/**
 * Google Docs Integration for Prescriptions
 * Endpoints:
 * - POST   /api/google/docs.php: Create/link Google Doc to prescription
 * - GET    /api/google/docs.php?id=<rx_id>: Get Google Doc details
 * - PATCH  /api/google/docs.php: Share document with user
 * - DELETE /api/google/docs.php?id=<doc_id>: Revoke document access
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/google.php';
require_once __DIR__ . '/../../config/helpers.php';

$user = require_auth();
$pdo = db();
$method = $_SERVER['REQUEST_METHOD'];

// Helper: Get access token (with refresh if needed)
function get_google_token($user_id) {
    $pdo = db();
    $row = $pdo->prepare("SELECT access_token, refresh_token, expires_at FROM google_oauth_tokens WHERE user_id=:uid")
        ->execute([':uid' => $user_id])->fetch();
    
    if (!$row) return null;
    
    // Check if token expired, refresh if needed
    if (strtotime($row['expires_at']) < time() && $row['refresh_token']) {
        $refreshed = refresh_google_token($user_id, $row['refresh_token']);
        return $refreshed['access_token'] ?? null;
    }
    
    return $row['access_token'];
}

// Helper: Refresh access token
function refresh_google_token($user_id, $refresh_token) {
    $ch = curl_init(GOOGLE_OAUTH_TOKEN_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type'    => 'refresh_token',
        'refresh_token' => $refresh_token,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
    ]));
    
    $response = json_decode(curl_exec($ch), true);
    curl_close($ch);
    
    if (!isset($response['access_token'])) {
        throw new Exception('Failed to refresh Google token');
    }
    
    // Update in database
    $pdo = db();
    $pdo->prepare("UPDATE google_oauth_tokens SET access_token=:access, expires_at=:exp WHERE user_id=:uid")
        ->execute([
            ':access' => $response['access_token'],
            ':exp'    => date('Y-m-d H:i:s', time() + $response['expires_in']),
            ':uid'    => $user_id,
        ]);
    
    return $response;
}

// Helper: Make authenticated Google API call
function google_api($method, $endpoint, $token, $data = null) {
    $url = GOOGLE_DRIVE_API_URL . $endpoint;
    if (strpos($endpoint, '/v1/') === 0) {
        $url = GOOGLE_DOCS_API_URL . $endpoint;
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
    ]);
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode >= 400) {
        throw new Exception("Google API error ($httpCode): $response");
    }
    
    return json_decode($response, true);
}

// POST: Create new Google Doc linked to prescription
if ($method === 'POST') {
    $b = json_body();
    $rxId = $b['prescriptionId'] ?? null;
    $title = $b['title'] ?? null;
    
    if (!$rxId || !$title) {
        json_error('BAD_REQUEST', 'prescriptionId and title are required');
    }
    
    // Verify prescription exists and user has access
    $rx = $pdo->prepare("SELECT id, doctor_id, health_worker_id FROM prescriptions WHERE id=:id")->execute([':id' => $rxId])->fetch();
    if (!$rx) json_error('NOT_FOUND', 'Prescription not found');
    
    // Access control: only doctor, assigned health worker, or admin
    if ($user['role'] === 'HEALTH_WORKER' && $rx['health_worker_id'] !== $user['id']) {
        json_error('FORBIDDEN', 'Access denied to this prescription');
    }
    if ($user['role'] === 'DOCTOR' && $rx['doctor_id'] !== $user['id']) {
        json_error('FORBIDDEN', 'Access denied to this prescription');
    }
    
    try {
        $token = get_google_token($user['id']);
        if (!$token) json_error('UNAUTHORIZED', 'Google OAuth not connected. Please link your Google account first.');
        
        // Create blank document in Google Drive
        $docResp = google_api('POST', '/files', $token, [
            'name'     => $title,
            'mimeType' => 'application/vnd.google-apps.document',
            'parents'  => ['root'],
        ]);
        
        $docId = $docResp['id'] ?? null;
        if (!$docId) throw new Exception('Failed to create Google Doc');
        
        $docUrl = "https://docs.google.com/document/d/$docId/edit";
        
        // Save integration in database
        $integId = bin2hex(random_bytes(8));
        $pdo->prepare("INSERT INTO google_docs_integrations(id, prescription_id, google_doc_id, google_doc_url, document_title, created_at)
            VALUES(:id, :rx, :docid, :url, :title, NOW())")
            ->execute([
                ':id'     => $integId,
                ':rx'     => $rxId,
                ':docid'  => $docId,
                ':url'    => $docUrl,
                ':title'  => $title,
            ]);
        
        json_ok([
            'id'        => $integId,
            'docId'     => $docId,
            'docUrl'    => $docUrl,
            'title'     => $title,
            'createdAt' => date('c'),
        ], 201);
        
    } catch (Exception $e) {
        json_error('EXTERNAL_ERROR', 'Google Docs creation failed: ' . $e->getMessage(), 500);
    }
}

// GET: Fetch Google Doc details for prescription
if ($method === 'GET' && gp('id')) {
    $rxId = gp('id');
    $row = $pdo->prepare("SELECT * FROM google_docs_integrations WHERE prescription_id=:id")
        ->execute([':id' => $rxId])->fetch();
    
    if (!$row) json_error('NOT_FOUND', 'No Google Doc linked to this prescription');
    
    json_ok([
        'id'        => $row['id'],
        'docId'     => $row['google_doc_id'],
        'docUrl'    => $row['google_doc_url'],
        'title'     => $row['document_title'],
        'createdAt' => $row['created_at'],
        'lastSynced'=> $row['last_synced_at'],
    ]);
}

// PATCH: Share document with another user (grant edit access)
if ($method === 'PATCH') {
    $b = json_body();
    $docId = $b['docId'] ?? null;
    $shareEmail = $b['email'] ?? null;
    
    if (!$docId || !$shareEmail) {
        json_error('BAD_REQUEST', 'docId and email are required');
    }
    
    try {
        $token = get_google_token($user['id']);
        if (!$token) json_error('UNAUTHORIZED', 'Google OAuth not connected');
        
        // Grant edit permission
        $shareResp = google_api('POST', "/files/$docId/permissions", $token, [
            'role'           => 'editor',
            'type'           => 'user',
            'emailAddress'   => $shareEmail,
            'sendNotificationEmail' => true,
        ]);
        
        json_ok([
            'message' => 'Document shared successfully',
            'permissionId' => $shareResp['id'] ?? null,
        ]);
        
    } catch (Exception $e) {
        json_error('EXTERNAL_ERROR', 'Failed to share document: ' . $e->getMessage(), 500);
    }
}

// DELETE: Revoke access to document
if ($method === 'DELETE' && gp('id')) {
    $docId = gp('id');
    
    try {
        $token = get_google_token($user['id']);
        if (!$token) json_error('UNAUTHORIZED', 'Google OAuth not connected');
        
        // Trash the document
        google_api('PATCH', "/files/$docId", $token, ['trashed' => true]);
        
        // Remove integration record
        $pdo->prepare("DELETE FROM google_docs_integrations WHERE google_doc_id=:id")
            ->execute([':id' => $docId]);
        
        json_ok(['message' => 'Document access revoked']);
        
    } catch (Exception $e) {
        json_error('EXTERNAL_ERROR', 'Failed to revoke access: ' . $e->getMessage(), 500);
    }
}

json_error('METHOD_NOT_ALLOWED', 'Invalid request method');
