<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';
session_write_close();

$user   = require_auth();
$pdo    = db();
$method = $_SERVER['REQUEST_METHOD'];
$id     = gp('id');
$forms  = ['TABLET','CAPSULE','SYRUP','INJECTION','OINTMENT','DROPS','INHALER','SUPPOSITORY','PATCH','OTHER'];

if ($method === 'GET') {
    $page   = max(1,(int)gp('page',1));
    $limit  = min(200,max(1,(int)gp('limit',50)));
    $search = gp('search','');
    $where  = []; $params = [];
    if ($user['role'] !== 'ADMIN') { $where[] = "is_active=1"; }
    if ($search) { $where[] = "(name LIKE :s OR generic_name LIKE :s)"; $params[':s']="%$search%"; }
    $wStr = $where ? 'WHERE '.implode(' AND ',$where) : '';
    $sql  = "SELECT id,name,generic_name AS genericName,form,is_active AS isActive,created_at AS createdAt FROM medicines $wStr ORDER BY name ASC";
    $r    = paginate($pdo,$sql,$params,$page,$limit);
    json_ok($r['rows'],200,$r['meta']);
}

if ($method === 'POST') {
    require_role('ADMIN');
    $b    = json_body();
    $name = clean($b['name'] ?? '');
    $gen  = clean($b['genericName'] ?? '');
    $form = strtoupper($b['form'] ?? 'TABLET');
    if (!$name) json_error('BAD_REQUEST','Medicine name required');
    if (!in_array($form,$forms)) json_error('BAD_REQUEST','Invalid form');
    $newId = bin2hex(random_bytes(8));
    $pdo->prepare("INSERT INTO medicines(id,name,generic_name,form,is_active) VALUES(:id,:n,:g,:f,1)")
        ->execute([':id'=>$newId,':n'=>$name,':g'=>$gen?:null,':f'=>$form]);
    audit('MEDICINE_ADDED','Medicine',$newId,['name'=>$name]);
    json_ok(['id'=>$newId,'name'=>$name,'genericName'=>$gen,'form'=>$form,'isActive'=>true],201);
}

if ($method === 'PATCH' && $id) {
    require_role('ADMIN');
    $b = json_body(); $sets=[]; $params=[':id'=>$id];
    if (isset($b['name']))        { $sets[]='name=:n';        $params[':n']=clean($b['name']); }
    if (isset($b['genericName'])) { $sets[]='generic_name=:g';$params[':g']=clean($b['genericName']); }
    if (isset($b['isActive']))    { $sets[]='is_active=:ia';  $params[':ia']=(int)(bool)$b['isActive']; }
    if (!$sets) json_error('BAD_REQUEST','Nothing to update');
    $pdo->prepare("UPDATE medicines SET ".implode(',',$sets)." WHERE id=:id")->execute($params);
    audit('MEDICINE_UPDATED','Medicine',$id);
    json_ok(['id'=>$id,'updated'=>true]);
}

if ($method === 'DELETE' && $id) {
    require_role('ADMIN');
    $pdo->prepare("UPDATE medicines SET is_active=0 WHERE id=:id")->execute([':id'=>$id]);
    audit('MEDICINE_DEACTIVATED','Medicine',$id);
    json_ok(['id'=>$id,'isActive'=>false]);
}
