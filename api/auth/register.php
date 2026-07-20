<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
session_write_close();

require_method('POST');

$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
rate_limit("register:$ip", 5, 900);

$b     = json_body();
$name  = clean($b['name']  ?? '');
$email = strtolower(trim($b['email'] ?? ''));
$phone = trim($b['phone']  ?? '');
$pass  = $b['password']    ?? '';
$role  = $b['role']        ?? '';

if (!$name)                              json_error('BAD_REQUEST', 'Name is required');
if (!$email && !$phone)                  json_error('BAD_REQUEST', 'Provide at least an email or phone number');
if (strlen($pass) < 6)                   json_error('BAD_REQUEST', 'Password must be at least 6 characters');
if (!in_array($role, ['HEALTH_WORKER','DOCTOR'])) json_error('BAD_REQUEST', 'Invalid role');
if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('BAD_REQUEST', 'Invalid email address');
if ($phone && !preg_match('/^01[3-9]\d{8}$/', $phone)) json_error('BAD_REQUEST', 'Invalid phone. Format: 01712345678');

$pdo = db();
if ($email) {
    $s = $pdo->prepare("SELECT id FROM users WHERE email = :e LIMIT 1");
    $s->execute([':e' => $email]);
    if ($s->fetch()) json_error('CONFLICT', 'Email already registered', 409);
}
if ($phone) {
    $s = $pdo->prepare("SELECT id FROM users WHERE phone = :p LIMIT 1");
    $s->execute([':p' => $phone]);
    if ($s->fetch()) json_error('CONFLICT', 'Phone already registered', 409);
}

$id   = bin2hex(random_bytes(8));
$hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);

$pdo->prepare("INSERT INTO users(id,name,email,phone,password_hash,role,status,can_write_prescription) VALUES(:id,:name,:email,:phone,:hash,:role,'PENDING',0)")
    ->execute([':id'=>$id,':name'=>$name,':email'=>$email?:null,':phone'=>$phone?:null,':hash'=>$hash,':role'=>$role]);

json_ok(['message' => 'Registered successfully. Waiting for admin approval.'], 201);
