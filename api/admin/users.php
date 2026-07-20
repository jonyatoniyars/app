<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
session_write_close();

require_role('ADMIN');
$pdo    = db();
$method = $_SERVER['REQUEST_METHOD'];
$id     = gp('id');

if ($method === 'GET') {
    $page   = max(1,(int)gp('page',1));
    $limit  = min(100,max(1,(int)gp('limit',20)));
    $search = gp('search','');
    $status = gp('status','');
    $role   = gp('role','');

    $where  = ["u.role != 'ADMIN'"]; $params = [];
    if ($search) { $where[] = "(u.name LIKE :s OR u.email LIKE :s OR u.phone LIKE :s)"; $params[':s']="%$search%"; }
    if ($status && in_array($status,['PENDING','ACTIVE','SUSPENDED'])) { $where[] = "u.status=:st"; $params[':st']=$status; }
    if ($role   && in_array($role,['DOCTOR','HEALTH_WORKER']))         { $where[] = "u.role=:r";   $params[':r']=$role;   }

    $sql = "SELECT u.id,u.name,u.email,u.phone,u.role,u.status,u.can_write_prescription AS canWritePrescription,u.created_at AS createdAt FROM users u WHERE ".implode(' AND ',$where)." ORDER BY u.created_at DESC";
    $r   = paginate($pdo, $sql, $params, $page, $limit);
    json_ok($r['rows'], 200, $r['meta']);
}

if ($method === 'PATCH' && $id) {
    $b    = json_body();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id=:id LIMIT 1");
    $stmt->execute([':id'=>$id]); $user = $stmt->fetch();
    if (!$user) json_error('NOT_FOUND','User not found',404);
    if ($user['role']==='ADMIN') json_error('FORBIDDEN','Cannot modify admin accounts',403);

    if (array_key_exists('status',$b)) {
        $st  = $b['status'];
        if (!in_array($st,['ACTIVE','SUSPENDED'])) json_error('BAD_REQUEST','Invalid status');
        $cwp = ($st==='ACTIVE' && $user['role']==='HEALTH_WORKER') ? 1 : $user['can_write_prescription'];
        $pdo->prepare("UPDATE users SET status=:s,can_write_prescription=:cwp WHERE id=:id")->execute([':s'=>$st,':cwp'=>$cwp,':id'=>$id]);
        audit("USER_$st",'User',$id);
        json_ok(['id'=>$id,'status'=>$st,'canWritePrescription'=>(bool)$cwp]);
    }

    if (array_key_exists('canWritePrescription',$b)) {
        if ($user['role']!=='HEALTH_WORKER') json_error('BAD_REQUEST','Permission only applies to health workers');
        $cwp = (bool)$b['canWritePrescription'] ? 1 : 0;
        $pdo->prepare("UPDATE users SET can_write_prescription=:c WHERE id=:id")->execute([':c'=>$cwp,':id'=>$id]);
        audit($cwp?'PERM_GRANTED':'PERM_REVOKED','User',$id);
        json_ok(['id'=>$id,'canWritePrescription'=>(bool)$cwp]);
    }
    json_error('BAD_REQUEST','Nothing to update');
}
