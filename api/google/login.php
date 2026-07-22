<?php
/**
 * Google OAuth 2.0 Initiation
 * Endpoint: /api/google/login.php
 * Redirects user to Google OAuth consent screen
 */

require_once __DIR__ . '/../../config/google.php';

// Generate random state token for CSRF protection
$state = bin2hex(random_bytes(16));
session_write_close();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['google_oauth_state'] = $state;

// Build OAuth authorization URL
$params = [
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope'         => implode(' ', GOOGLE_OAUTH_SCOPES),
    'access_type'   => 'offline',  // Request refresh token
    'state'         => $state,
    'prompt'        => 'consent',  // Force re-consent to get refresh token
];

$url = GOOGLE_OAUTH_AUTH_URL . '?' . http_build_query($params);

// Redirect to Google OAuth
header("Location: $url");
exit;
