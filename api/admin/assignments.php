<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
session_write_close();

require_role('ADMIN');
$pdo    = db();
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $rows = $pdo->query("SELECT da.id,da.assigned_at AS assignedAt,
        d.id AS dId,d.name AS dName,d.email AS dEmail,
        w.id AS wId,w.name AS wName,w.email AS wEmail,w.phone AS wPhone,w.status AS wStatus
        FROM doctor_assignments da
        JOIN users d ON d.id=da.doctor_id
        JOIN users w ON w.id=da.health_worker_id
        ORDER BY da.assigned_at DESC")->fetchAll();
    json_ok(array_map(fn($r)=>[
        'id'=>$r['id'],'assignedAt'=>$r['assignedAt'],
        'doctor'=>['id'=>$r['dId'],'name'=>$r['dName'],'email'=>$r['dEmail']],
        'healthWorker'=>['id'=>$r['wId'],'name'=>$r['wName'],'email'=>$r['wEmail'],'phone'=>$r['wPhone'],'status'=>$r['wStatus']],
    ],$rows));
}

if ($method === 'POST') {
    $b    = json_body();
    $docId= $b['doctorId']       ?? '';
    $hwId = $b['healthWorkerId'] ?? '';
    if (!$docId||!$hwId) json_error('BAD_REQUEST','doctorId and healthWorkerId required');

    $d=$pdo->prepare("SELECT id,name FROM users WHERE id=:id AND role='DOCTOR' LIMIT 1"); $d->execute([':id'=>$docId]); $doc=$d->fetch();
    $w=$pdo->prepare("SELECT id,name FROM users WHERE id=:id AND role='HEALTH_WORKER' LIMIT 1"); $w->execute([':id'=>$hwId]); $hw=$w->fetch();
    if (!$doc) json_error('NOT_FOUND','Doctor not found',404);
    if (!$hw)  json_error('NOT_FOUND','Health worker not found',404);

    $newId = bin2hex(random_bytes(8));
    $pdo->prepare("INSERT INTO doctor_assignments(id,doctor_id,health_worker_id) VALUES(:id,:d,:w)
        ON DUPLICATE KEY UPDATE doctor_id=:d,id=:id")
        ->execute([':id'=>$newId,':d'=>$docId,':w'=>$hwId]);
    audit('ASSIGNED','DoctorAssignment',$newId,['doctor'=>$doc['name'],'hw'=>$hw['name']]);
    json_ok(['doctorId'=>$docId,'healthWorkerId'=>$hwId],201);
}

if ($method === 'DELETE') {
    $hwId = gp('healthWorkerId');
    if (!$hwId) json_error('BAD_REQUEST','healthWorkerId query param required');
    $pdo->prepare("DELETE FROM doctor_assignments WHERE health_worker_id=:hw")->execute([':hw'=>$hwId]);
    audit('UNASSIGNED','DoctorAssignment','',['hwId'=>$hwId]);
    json_ok(['message'=>'Assignment removed']);
}
