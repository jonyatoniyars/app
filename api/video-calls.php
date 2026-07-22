<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
session_write_close();

$user   = require_auth();
$pdo    = db();
$method = $_SERVER['REQUEST_METHOD'];
$id     = gp('id');
$action = gp('action', '');

$SEL = "SELECT vcr.id,vcr.note,vcr.status,vcr.created_at AS createdAt,
    r.id AS rId,r.name AS rName,r.phone AS rPhone,
    v.id AS vId,v.name AS vName
    FROM video_call_requests vcr
    JOIN users r ON r.id=vcr.requester_id
    JOIN users v ON v.id=vcr.receiver_id";

function fmtCall(array $r): array {
    return ['id'=>$r['id'],'note'=>$r['note'],'status'=>$r['status'],'createdAt'=>$r['createdAt'],
        'requester'=>['id'=>$r['rId'],'name'=>$r['rName'],'phone'=>$r['rPhone']],
        'receiver'=>['id'=>$r['vId'],'name'=>$r['vName']]];
}

// GET: List video calls
if ($method==='GET' && !$id) {
    $where=[]; $params=[];
    if ($user['role']==='HEALTH_WORKER') { $where[]="vcr.requester_id=:uid"; $params[':uid']=$user['id']; }
    elseif ($user['role']==='DOCTOR')    { $where[]="vcr.receiver_id=:uid";  $params[':uid']=$user['id']; }
    elseif ($user['role']==='ADMIN')     {} // Admin sees all
    $status=gp('status','');
    if ($status) { $where[]="vcr.status=:s"; $params[':s']=$status; }
    $wStr=$where?'WHERE '.implode(' AND ',$where):'';
    global $SEL;
    $s=$pdo->prepare("$SEL $wStr ORDER BY vcr.created_at DESC LIMIT 50");
    $s->execute($params);
    json_ok(array_map('fmtCall',$s->fetchAll()));
}

// GET: Escalation details
if ($method==='GET' && $id && gp('details') === 'escalation') {
    $s = $pdo->prepare("SELECT vce.*, d.name as doctorName, hw.name as hwName 
        FROM video_call_escalations vce
        JOIN users d ON d.id = vce.assigned_doctor_id
        JOIN users hw ON hw.id = vce.health_worker_id
        WHERE vce.original_call_id = :id");
    $s->execute([':id' => $id]);
    $escalation = $s->fetch();
    
    if (!$escalation) {
        json_ok(null);
    } else {
        json_ok([
            'id' => $escalation['id'],
            'callId' => $escalation['original_call_id'],
            'healthWorker' => ['id' => $escalation['health_worker_id'], 'name' => $escalation['hwName']],
            'assignedDoctor' => ['id' => $escalation['assigned_doctor_id'], 'name' => $escalation['doctorName']],
            'escalatedToAdmin' => (bool)$escalation['escalated_to_admin'],
            'escalationReason' => $escalation['escalation_reason'],
            'status' => $escalation['status'],
            'createdAt' => $escalation['created_at']
        ]);
    }
}

// POST: Create video call (initiates by Health Worker)
if ($method==='POST' && !$action) {
    if ($user['role']!=='HEALTH_WORKER') json_error('FORBIDDEN','Only health workers can request calls',403);
    
    $a=$pdo->prepare("SELECT doctor_id FROM doctor_assignments WHERE health_worker_id=:id");
    $a->execute([':id'=>$user['id']]); 
    $row=$a->fetch();
    if (!$row) json_error('BAD_REQUEST','You are not assigned to any doctor. Ask admin to assign you.');
    
    $ex=$pdo->prepare("SELECT id FROM video_call_requests WHERE requester_id=:id AND status='PENDING'");
    $ex->execute([':id'=>$user['id']]);
    if ($ex->fetch()) json_error('BAD_REQUEST','You already have a pending call request.');
    
    $b=json_body(); 
    $newId=bin2hex(random_bytes(8));
    $pdo->prepare("INSERT INTO video_call_requests(id,requester_id,receiver_id,note,status) VALUES(:id,:req,:rec,:note,'PENDING')")
        ->execute([':id'=>$newId,':req'=>$user['id'],':rec'=>$row['doctor_id'],':note'=>$b['note']??null]);
    
    // Check if doctor is online
    $docOnline = $pdo->prepare("SELECT is_online FROM doctor_online_status WHERE doctor_id = :id");
    $docOnline->execute([':id' => $row['doctor_id']]);
    $onlineStatus = $docOnline->fetch();
    $doctorIsOnline = $onlineStatus && $onlineStatus['is_online'];
    
    // If doctor is offline, auto-escalate to admin
    if (!$doctorIsOnline) {
        $escalationId = bin2hex(random_bytes(8));
        $pdo->prepare("INSERT INTO video_call_escalations 
            (id, original_call_id, health_worker_id, assigned_doctor_id, escalated_to_admin, escalation_reason, status) 
            VALUES (:id, :callId, :hwId, :docId, 1, :reason, 'ESCALATED_ADMIN')")
            ->execute([
                ':id' => $escalationId,
                ':callId' => $newId,
                ':hwId' => $user['id'],
                ':docId' => $row['doctor_id'],
                ':reason' => 'Doctor offline'
            ]);
    }
    
    global $SEL;
    $s=$pdo->prepare("$SEL WHERE vcr.id=:id"); 
    $s->execute([':id'=>$newId]);
    json_ok(fmtCall($s->fetch()),201);
}

// PATCH: Doctor respond to call
if ($method==='PATCH' && $id && !$action) {
    if (!in_array($user['role'],['DOCTOR','ADMIN'])) json_error('FORBIDDEN','Only doctors can respond',403);
    
    $b=json_body(); 
    $status=$b['status']??'';
    if (!in_array($status,['ACCEPTED','DECLINED'])) json_error('BAD_REQUEST','Status must be ACCEPTED or DECLINED');
    
    $s=$pdo->prepare("SELECT * FROM video_call_requests WHERE id=:id LIMIT 1"); 
    $s->execute([':id'=>$id]);
    $call=$s->fetch(); 
    if(!$call) json_error('NOT_FOUND','Call request not found',404);
    
    if ($user['role']==='DOCTOR'&&$call['receiver_id']!==$user['id']) json_error('FORBIDDEN','Not your request',403);
    if ($call['status']!=='PENDING') json_error('BAD_REQUEST','Request already '.strtolower($call['status']));
    
    $pdo->prepare("UPDATE video_call_requests SET status=:s, updated_at=NOW() WHERE id=:id")
        ->execute([':s'=>$status,':id'=>$id]);
    
    // Update escalation status if exists
    $esc = $pdo->prepare("SELECT id FROM video_call_escalations WHERE original_call_id = :id");
    $esc->execute([':id' => $id]);
    if ($escalation = $esc->fetch()) {
        $pdo->prepare("UPDATE video_call_escalations SET status = :s WHERE id = :id")
            ->execute([':s' => $status === 'ACCEPTED' ? 'ACCEPTED' : 'DOCTOR_DECLINED', ':id' => $escalation['id']]);
    }
    
    json_ok(['id'=>$id,'status'=>$status]);
}

// POST: Admin escalate/reroute call
if ($method==='POST' && $action === 'reroute') {
    require_role('ADMIN');
    
    $b = json_body();
    $callId = $b['callId'] ?? null;
    $rerouteToDocId = $b['rerouteToDocId'] ?? null;
    
    if (!$callId || !$rerouteToDocId) json_error('BAD_REQUEST', 'callId and rerouteToDocId required');
    
    // Get original call
    $callStmt = $pdo->prepare("SELECT * FROM video_call_requests WHERE id = :id");
    $callStmt->execute([':id' => $callId]);
    $call = $callStmt->fetch();
    if (!$call) json_error('NOT_FOUND', 'Call not found');
    
    // Update video call receiver
    $pdo->prepare("UPDATE video_call_requests SET receiver_id = :docId, status = 'PENDING', updated_at = NOW() WHERE id = :id")
        ->execute([':docId' => $rerouteToDocId, ':id' => $callId]);
    
    // Update escalation
    $pdo->prepare("UPDATE video_call_escalations 
        SET admin_reroute_to_id = :docId, admin_accepted_at = NOW(), status = 'ACCEPTED' 
        WHERE original_call_id = :callId")
        ->execute([':docId' => $rerouteToDocId, ':callId' => $callId]);
    
    json_ok(['id' => $callId, 'rerouted' => true, 'newReceiver' => $rerouteToDocId]);
}

// PUT: Update doctor online status
if ($method==='PUT' && $action === 'status') {
    if ($user['role'] !== 'DOCTOR') json_error('FORBIDDEN', 'Only doctors can update status', 403);
    
    $b = json_body();
    $isOnline = (bool)($b['isOnline'] ?? true);
    
    $stmt = $pdo->prepare("SELECT id FROM doctor_online_status WHERE doctor_id = :id");
    $stmt->execute([':id' => $user['id']]);
    $exists = $stmt->fetch();
    
    if ($exists) {
        $pdo->prepare("UPDATE doctor_online_status SET is_online = :status, last_activity_at = NOW() WHERE doctor_id = :id")
            ->execute([':status' => $isOnline ? 1 : 0, ':id' => $user['id']]);
    } else {
        $statusId = bin2hex(random_bytes(8));
        $pdo->prepare("INSERT INTO doctor_online_status (id, doctor_id, is_online, last_activity_at) 
            VALUES (:id, :docId, :status, NOW())")
            ->execute([':id' => $statusId, ':docId' => $user['id'], ':status' => $isOnline ? 1 : 0]);
    }
    
    json_ok(['isOnline' => $isOnline]);
}
