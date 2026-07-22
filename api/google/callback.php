<?php
/**
 * Google OAuth 2.0 Callback Handler
 * Endpoint: /api/google/callback.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/google.php';
require_once __DIR__ . '/../../config/helpers.php';

$user = require_auth();
$code = gp('code');
$state = gp('state');

if (!$code) {
    json_error('BAD_REQUEST', 'Missing authorization code');
}

// Verify state token (prevent CSRF)
if (!isset($_SESSION['google_oauth_state']) || $_SESSION['google_oauth_state'] !== $state) {
    json_error('FORBIDDEN', 'Invalid state token');
}

try {
    // Exchange authorization code for access token
    $response = http_post(GOOGLE_OAUTH_TOKEN_URL, [
        'code'          => $code,
        'client_id'     => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'grant_type'    => 'authorization_code',
    ]);

    if (empty($response['access_token'])) {
        throw new Exception('Failed to obtain access token');
    }

    // Store tokens in session
    $_SESSION[GOOGLE_TOKEN_SESSION_KEY] = $response['access_token'];
    if (!empty($response['refresh_token'])) {
        $_SESSION[GOOGLE_REFRESH_TOKEN_KEY] = $response['refresh_token'];
    }

    // Store in database for later use (non-session)
    $pdo = db();
    $tokenId = bin2hex(random_bytes(8));
    $pdo->prepare("INSERT INTO google_oauth_tokens(id, user_id, access_token, refresh_token, expires_at, created_at)
        VALUES(:id, :uid, :access, :refresh, :expires, NOW())
        ON DUPLICATE KEY UPDATE access_token=:access, refresh_token=:refresh, expires_at=:expires, updated_at=NOW()")
        ->execute([
            ':id'     => $tokenId,
            ':uid'    => $user['id'],
            ':access' => $response['access_token'],
            ':refresh' => $response['refresh_token'] ?? null,
            ':expires' => date('Y-m-d H:i:s', time() + ($response['expires_in'] ?? 3600)),
        ]);

    json_ok(['message' => 'Google OAuth connected successfully'], 200);
} catch (Exception $e) {
    json_error('EXTERNAL_ERROR', 'Google OAuth failed: ' . $e->getMessage(), 500);
}

// Helper: Make HTTP POST request
function http_post($url, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        throw new Exception("HTTP $httpCode: $response");
    }
    return json_decode($response, true);
}
