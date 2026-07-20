<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
session_write_close(); // Release lock before processing

require_method('POST');

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
rate_limit("login:$ip", 5, 900);

$b = json_body();
$identifier = trim($b['identifier'] ?? '');
$password   = $b['password'] ?? '';

if (!$identifier || !$password)
    json_error('BAD_REQUEST', 'Email/phone and password are required');

$pdo   = db();
$field = preg_match('/^01/', $identifier) ? 'phone' : 'email';
$stmt  = $pdo->prepare("SELECT * FROM users WHERE $field = :id LIMIT 1");
$stmt->execute([':id' => $identifier]);
$user  = $stmt->fetch();

// Constant-time comparison prevents user enumeration
$dummy = '$2y$12$zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz';
$ok    = password_verify($password, $user['password_hash'] ?? $dummy);

if (!$user || !$ok)
    json_error('INVALID_CREDENTIALS', 'Invalid email/phone or password', 401);

if ($user['status'] === 'PENDING')
    json_error('PENDING', 'Your account is pending admin approval.', 403);

if ($user['status'] === 'SUSPENDED')
    json_error('SUSPENDED', 'Your account has been suspended. Contact admin.', 403);

// Re-open session just to write
session_start();
login_user($user);
session_write_close();

json_ok([
    'id'                   => $user['id'],
    'name'                 => $user['name'],
    'email'                => $user['email'],
    'phone'                => $user['phone'],
    'role'                 => $user['role'],
    'canWritePrescription' => (bool)$user['can_write_prescription'],
]);
