<?php
// ── JSON responses ────────────────────────────────────────────────────────────
function json_ok($data, int $status = 200, array $meta = []): void {
    http_response_code($status);
    header('Content-Type: application/json');
    $out = ['success' => true, 'data' => $data];
    if ($meta) $out['meta'] = $meta;
    echo json_encode($out);
    exit;
}

function json_error(string $code, string $message, int $status = 400, array $details = []): void {
    http_response_code($status);
    header('Content-Type: application/json');
    $err = ['code' => $code, 'message' => $message];
    if ($details) $err['details'] = $details;
    echo json_encode(['success' => false, 'error' => $err]);
    exit;
}

// ── Auth ─────────────────────────────────────────────────────────────────────
function current_user(): ?array {
    return $_SESSION['user'] ?? null;
}

function require_auth(): array {
    $user = current_user();
    if (!$user)                        json_error('UNAUTHORIZED', 'Please log in', 401);
    if ($user['status'] !== 'ACTIVE')  json_error('FORBIDDEN', 'Account not active', 403);
    return $user;
}

function require_role(string ...$roles): array {
    $user = require_auth();
    if (!in_array($user['role'], $roles))
        json_error('FORBIDDEN', 'Insufficient permissions', 403);
    return $user;
}

function login_user(array $row): void {
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id'                   => $row['id'],
        'name'                 => $row['name'],
        'email'                => $row['email'],
        'phone'                => $row['phone'],
        'role'                 => $row['role'],
        'status'               => $row['status'],
        'canWritePrescription' => (bool)$row['can_write_prescription'],
    ];
}

// ── Request helpers ───────────────────────────────────────────────────────────
function json_body(): array {
    $raw = file_get_contents('php://input');
    $d   = json_decode($raw, true);
    if (!is_array($d)) json_error('BAD_REQUEST', 'Invalid JSON body');
    return $d;
}

function gp(string $key, $default = null) {
    return $_GET[$key] ?? $default;
}

function require_method(string ...$methods): void {
    if (!in_array($_SERVER['REQUEST_METHOD'], $methods))
        json_error('METHOD_NOT_ALLOWED', 'Method not allowed', 405);
}

// ── Sanitize ─────────────────────────────────────────────────────────────────
function clean(string $val): string {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

// ── File-based rate limiter ───────────────────────────────────────────────────
function rate_limit(string $key, int $limit, int $windowSec): void {
    $dir  = sys_get_temp_dir() . '/pc_rl';
    if (!is_dir($dir)) @mkdir($dir, 0700, true);
    $file = $dir . '/' . md5($key) . '.json';
    $now  = time();
    $hits = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];
    $hits = array_filter($hits, fn($t) => $t > $now - $windowSec);
    if (count($hits) >= $limit)
        json_error('RATE_LIMITED', 'Too many requests. Please wait.', 429);
    $hits[] = $now;
    file_put_contents($file, json_encode(array_values($hits)), LOCK_EX);
}

// ── Pagination ────────────────────────────────────────────────────────────────
function paginate(PDO $pdo, string $sql, array $params, int $page, int $limit): array {
    $countSql = "SELECT COUNT(*) FROM ($sql) _c";
    $cs = $pdo->prepare($countSql);
    $cs->execute($params);
    $total = (int)$cs->fetchColumn();

    $offset = ($page - 1) * $limit;
    $rs = $pdo->prepare("$sql LIMIT :lim OFFSET :off");
    foreach ($params as $k => $v) $rs->bindValue($k, $v);
    $rs->bindValue(':lim',  $limit,  PDO::PARAM_INT);
    $rs->bindValue(':off',  $offset, PDO::PARAM_INT);
    $rs->execute();

    return [
        'rows' => $rs->fetchAll(),
        'meta' => ['page' => $page, 'limit' => $limit, 'total' => $total,
                   'totalPages' => max(1, (int)ceil($total / $limit))],
    ];
}

// ── Audit log ─────────────────────────────────────────────────────────────────
function audit(string $action, string $type = '', string $entityId = '', array $meta = []): void {
    $user = current_user();
    if (!$user) return;
    try {
        db()->prepare("INSERT INTO audit_logs(id,admin_id,action,target_entity_type,target_entity_id,metadata)
                       VALUES(:id,:aid,:act,:type,:eid,:meta)")
           ->execute([':id'=>bin2hex(random_bytes(8)),':aid'=>$user['id'],':act'=>$action,
                      ':type'=>$type,':eid'=>$entityId,':meta'=>json_encode($meta)]);
    } catch (Throwable $e) { /* non-critical */ }
}
