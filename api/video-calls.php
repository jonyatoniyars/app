<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
session_write_close();

$user   = require_auth();
$pdo    = db();
$method = $_SERVER['REQUEST_METHOD'];
$id     = gp('id');

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

if ($method==='GET') {
    $where=[]; $params=[];
    if ($user['role']==='HEALTH_WORKER') { $where[]="vcr.requester_id=:uid"; $params[':uid']=$user['id']; }
    elseif ($user['role']==='DOCTOR')    { $where[]="vcr.receiver_id=:uid";  $params[':uid']=$user['id']; }
    $status=gp('status','');
    if ($status) { $where[]="vcr.status=:s"; $params[':s']=$status; }
    $wStr=$where?'WHERE '.implode(' AND ',$where):'';
    global $SEL;
    $s=$pdo->prepare("$SEL $wStr ORDER BY vcr.created_at DESC LIMIT 50");
    $s->execute($params);
    json_ok(array_map('fmtCall',$s->fetchAll()));
}

if ($method==='POST') {
    if ($user['role']!=='HEALTH_WORKER') json_error('FORBIDDEN','Only health workers can request calls',403);
    $a=$pdo->prepare("SELECT doctor_id FROM doctor_assignments WHERE health_worker_id=:id");
    $a->execute([':id'=>$user['id']]); $row=$a->fetch();
    if (!$row) json_error('BAD_REQUEST','You are not assigned to any doctor. Ask admin to assign you.');
    $ex=$pdo->prepare("SELECT id FROM video_call_requests WHERE requester_id=:id AND status='PENDING'");
    $ex->execute([':id'=>$user['id']]);
    if ($ex->fetch()) json_error('BAD_REQUEST','You already have a pending call request.');
    $b=json_body(); $newId=bin2hex(random_bytes(8));
    $pdo->prepare("INSERT INTO video_call_requests(id,requester_id,receiver_id,note,status) VALUES(:id,:req,:rec,:note,'PENDING')")
        ->execute([':id'=>$newId,':req'=>$user['id'],':rec'=>$row['doctor_id'],':note'=>$b['note']??null]);
    global $SEL;
    $s=$pdo->prepare("$SEL WHERE vcr.id=:id"); $s->execute([':id'=>$newId]);
    json_ok(fmtCall($s->fetch()),201);
}

if ($method==='PATCH' && $id) {
    if (!in_array($user['role'],['DOCTOR','ADMIN'])) json_error('FORBIDDEN','Only doctors can respond',403);
    $b=json_body(); $status=$b['status']??'';
    if (!in_array($status,['ACCEPTED','DECLINED'])) json_error('BAD_REQUEST','Status must be ACCEPTED or DECLINED');
    $s=$pdo->prepare("SELECT * FROM video_call_requests WHERE id=:id LIMIT 1"); $s->execute([':id'=>$id]);
    $call=$s->fetch(); if(!$call) json_error('NOT_FOUND','Call request not found',404);
    if ($user['role']==='DOCTOR'&&$call['receiver_id']!==$user['id']) json_error('FORBIDDEN','Not your request',403);
    if ($call['status']!=='PENDING') json_error('BAD_REQUEST','Request already '.strtolower($call['status']));
    $pdo->prepare("UPDATE video_call_requests SET status=:s WHERE id=:id")->execute([':s'=>$status,':id'=>$id]);
    json_ok(['id'=>$id,'status'=>$status]);
}
