<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
session_write_close();

$user   = require_auth();
$pdo    = db();
$method = $_SERVER['REQUEST_METHOD'];
$id     = gp('id');

$SEL = "SELECT p.id,p.patient_name AS patientName,p.patient_age AS patientAge,
    p.patient_gender AS patientGender,p.chief_complaints AS chiefComplaints,
    p.on_examination AS onExamination,p.advice,p.status,
    p.reviewed_at AS reviewedAt,p.review_notes AS reviewNotes,
    p.created_at AS createdAt,p.updated_at AS updatedAt,
    hw.id AS hwId,hw.name AS hwName,
    dr.id AS drId,dr.name AS drName
    FROM prescriptions p
    JOIN users hw ON hw.id=p.health_worker_id
    LEFT JOIN users dr ON dr.id=p.reviewed_by_id";

function fmtRx(array $r, array $items=[]): array {
    return ['id'=>$r['id'],'patientName'=>$r['patientName'],'patientAge'=>(int)$r['patientAge'],
        'patientGender'=>$r['patientGender'],'chiefComplaints'=>$r['chiefComplaints'],
        'onExamination'=>$r['onExamination'],'advice'=>$r['advice'],'status'=>$r['status'],
        'reviewedAt'=>$r['reviewedAt'],'reviewNotes'=>$r['reviewNotes'],
        'createdAt'=>$r['createdAt'],'updatedAt'=>$r['updatedAt'],
        'healthWorker'=>['id'=>$r['hwId'],'name'=>$r['hwName']],
        'reviewedBy'=>$r['drId']?['id'=>$r['drId'],'name'=>$r['drName']]:null,
        'items'=>$items];
}

// GET list
if ($method==='GET' && !$id) {
    $page=max(1,(int)gp('page',1)); $limit=min(50,max(1,(int)gp('limit',20)));
    $search=gp('search',''); $status=gp('status','');
    $where=[]; $params=[];

    if ($user['role']==='HEALTH_WORKER') { $where[]="p.health_worker_id=:hwid"; $params[':hwid']=$user['id']; }
    elseif ($user['role']==='DOCTOR') {
        $s=$pdo->prepare("SELECT health_worker_id FROM doctor_assignments WHERE doctor_id=:did");
        $s->execute([':did'=>$user['id']]); $hwIds=$s->fetchAll(PDO::FETCH_COLUMN);
        if (empty($hwIds)) json_ok([],200,['page'=>1,'limit'=>$limit,'total'=>0,'totalPages'=>0]);
        $ph=implode(',',array_map(fn($i)=>":hw$i",array_keys($hwIds)));
        $where[]="p.health_worker_id IN($ph)";
        foreach ($hwIds as $i=>$v) $params[":hw$i"]=$v;
    }
    if ($status && in_array($status,['DRAFT','SUBMITTED','REVIEWED'])) { $where[]="p.status=:st"; $params[':st']=$status; }
    if ($search) { $where[]="(p.patient_name LIKE :s OR p.chief_complaints LIKE :s)"; $params[':s']="%$search%"; }

    global $SEL;
    $wStr=$where?'WHERE '.implode(' AND ',$where):'';
    $r=paginate($pdo,"$SEL $wStr ORDER BY p.created_at DESC",$params,$page,$limit);
    json_ok(array_map(fn($row)=>fmtRx($row),$r['rows']),200,$r['meta']);
}

// GET single
if ($method==='GET' && $id) {
    global $SEL;
    $s=$pdo->prepare("$SEL WHERE p.id=:id LIMIT 1"); $s->execute([':id'=>$id]);
    $row=$s->fetch(); if(!$row) json_error('NOT_FOUND','Prescription not found',404);
    if ($user['role']==='HEALTH_WORKER' && $row['hwId']!==$user['id']) json_error('FORBIDDEN','Access denied',403);

    $si=$pdo->prepare("SELECT pi.id,pi.dose,pi.frequency,pi.duration,pi.instructions,m.id AS medicineId,m.name AS medicineName,m.form FROM prescription_items pi JOIN medicines m ON m.id=pi.medicine_id WHERE pi.prescription_id=:id");
    $si->execute([':id'=>$id]);
    json_ok(fmtRx($row,$si->fetchAll()));
}

// POST create
if ($method==='POST') {
    if ($user['role']!=='HEALTH_WORKER') json_error('FORBIDDEN','Only health workers can write prescriptions',403);
    if (!$user['canWritePrescription'])  json_error('FORBIDDEN','Prescription writing permission revoked. Contact admin.',403);
    $b=json_body();
    if (empty($b['patientName'])||!isset($b['patientAge'])||empty($b['chiefComplaints'])||empty($b['items']))
        json_error('BAD_REQUEST','patientName, patientAge, chiefComplaints and items are required');
    if (!is_array($b['items'])||count($b['items'])<1) json_error('BAD_REQUEST','Add at least one medicine');

    $medIds=array_column($b['items'],'medicineId');
    $ph=implode(',',array_fill(0,count($medIds),'?'));
    $sm=$pdo->prepare("SELECT id FROM medicines WHERE id IN($ph) AND is_active=1");
    $sm->execute($medIds);
    if ($sm->rowCount()!==count($medIds)) json_error('BAD_REQUEST','One or more medicines are not in the approved list');

    $rxId=bin2hex(random_bytes(8));
    $st=in_array($b['status']??'',['DRAFT','SUBMITTED'])?$b['status']:'DRAFT';
    $pdo->prepare("INSERT INTO prescriptions(id,health_worker_id,patient_name,patient_age,patient_gender,chief_complaints,on_examination,advice,status) VALUES(:id,:hw,:pn,:pa,:pg,:cc,:oe,:adv,:st)")
        ->execute([':id'=>$rxId,':hw'=>$user['id'],':pn'=>clean($b['patientName']),':pa'=>(int)$b['patientAge'],':pg'=>$b['patientGender'],':cc'=>clean($b['chiefComplaints']),':oe'=>isset($b['onExamination'])?clean($b['onExamination']):null,':adv'=>isset($b['advice'])?clean($b['advice']):null,':st'=>$st]);
    foreach ($b['items'] as $item) {
        $pdo->prepare("INSERT INTO prescription_items(id,prescription_id,medicine_id,dose,frequency,duration,instructions) VALUES(:id,:rx,:m,:d,:f,:dur,:inst)")
            ->execute([':id'=>bin2hex(random_bytes(8)),':rx'=>$rxId,':m'=>$item['medicineId'],':d'=>clean($item['dose']),':f'=>clean($item['frequency']),':dur'=>clean($item['duration']),':inst'=>isset($item['instructions'])?clean($item['instructions']):null]);
    }
    global $SEL;
    $s=$pdo->prepare("$SEL WHERE p.id=:id LIMIT 1"); $s->execute([':id'=>$rxId]);
    json_ok(fmtRx($s->fetch()),201);
}

// PATCH review
if ($method==='PATCH' && $id) {
    if (!in_array($user['role'],['DOCTOR','ADMIN'])) json_error('FORBIDDEN','Only doctors can review prescriptions',403);
    $b=json_body();
    $s=$pdo->prepare("SELECT p.*,hw.id AS hwId FROM prescriptions p JOIN users hw ON hw.id=p.health_worker_id WHERE p.id=:id");
    $s->execute([':id'=>$id]); $rx=$s->fetch();
    if (!$rx) json_error('NOT_FOUND','Prescription not found',404);
    if ($rx['status']!=='SUBMITTED') json_error('BAD_REQUEST','Can only review SUBMITTED prescriptions');
    if ($user['role']==='DOCTOR') {
        $a=$pdo->prepare("SELECT id FROM doctor_assignments WHERE doctor_id=:d AND health_worker_id=:hw");
        $a->execute([':d'=>$user['id'],':hw'=>$rx['hwId']]);
        if (!$a->fetch()) json_error('FORBIDDEN','This health worker is not assigned to you',403);
    }
    $pdo->prepare("UPDATE prescriptions SET status='REVIEWED',reviewed_by_id=:did,reviewed_at=NOW(),review_notes=:notes WHERE id=:id")
        ->execute([':did'=>$user['id'],':notes'=>$b['reviewNotes']??null,':id'=>$id]);
    global $SEL;
    $s=$pdo->prepare("$SEL WHERE p.id=:id LIMIT 1"); $s->execute([':id'=>$id]);
    $si=$pdo->prepare("SELECT pi.id,pi.dose,pi.frequency,pi.duration,pi.instructions,m.id AS medicineId,m.name AS medicineName,m.form FROM prescription_items pi JOIN medicines m ON m.id=pi.medicine_id WHERE pi.prescription_id=:id");
    $si->execute([':id'=>$id]);
    json_ok(fmtRx($s->fetch(),$si->fetchAll()));
}
