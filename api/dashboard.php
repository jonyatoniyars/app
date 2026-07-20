<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';
session_write_close();

require_method('GET');
$user = require_auth();
$pdo  = db();

try {
    if ($user['role'] === 'ADMIN') {
        json_ok([
            'totalUsers'         => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role!='ADMIN'")->fetchColumn(),
            'pendingApprovals'   => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status='PENDING'")->fetchColumn(),
            'totalPrescriptions' => (int)$pdo->query("SELECT COUNT(*) FROM prescriptions")->fetchColumn(),
            'activeMedicines'    => (int)$pdo->query("SELECT COUNT(*) FROM medicines WHERE is_active=1")->fetchColumn(),
        ]);
    }

    if ($user['role'] === 'DOCTOR') {
        $s = $pdo->prepare("SELECT health_worker_id FROM doctor_assignments WHERE doctor_id=:id");
        $s->execute([':id'=>$user['id']]);
        $hwIds = $s->fetchAll(PDO::FETCH_COLUMN);
        $cnt   = count($hwIds);
        $pendingReviews = 0;
        if ($cnt > 0) {
            $ph = implode(',', array_fill(0,$cnt,'?'));
            $s2 = $pdo->prepare("SELECT COUNT(*) FROM prescriptions WHERE health_worker_id IN($ph) AND status='SUBMITTED'");
            $s2->execute($hwIds);
            $pendingReviews = (int)$s2->fetchColumn();
        }
        $s3 = $pdo->prepare("SELECT COUNT(*) FROM prescriptions WHERE reviewed_by_id=:id AND status='REVIEWED'"); $s3->execute([':id'=>$user['id']]);
        $s4 = $pdo->prepare("SELECT COUNT(*) FROM video_call_requests WHERE receiver_id=:id AND status='PENDING'"); $s4->execute([':id'=>$user['id']]);
        json_ok(['assignedWorkers'=>$cnt,'pendingReviews'=>$pendingReviews,'totalReviewed'=>(int)$s3->fetchColumn(),'pendingVideoCalls'=>(int)$s4->fetchColumn()]);
    }

    if ($user['role'] === 'HEALTH_WORKER') {
        $id = $user['id'];
        $s1=$pdo->prepare("SELECT COUNT(*) FROM prescriptions WHERE health_worker_id=:id"); $s1->execute([':id'=>$id]);
        $s2=$pdo->prepare("SELECT COUNT(*) FROM prescriptions WHERE health_worker_id=:id AND status='SUBMITTED'"); $s2->execute([':id'=>$id]);
        $s3=$pdo->prepare("SELECT COUNT(*) FROM prescriptions WHERE health_worker_id=:id AND status='REVIEWED'"); $s3->execute([':id'=>$id]);
        $s4=$pdo->prepare("SELECT COUNT(*) FROM video_call_requests WHERE requester_id=:id AND status='PENDING'"); $s4->execute([':id'=>$id]);
        json_ok(['totalPrescriptions'=>(int)$s1->fetchColumn(),'submittedPrescriptions'=>(int)$s2->fetchColumn(),'reviewedPrescriptions'=>(int)$s3->fetchColumn(),'pendingVideoCalls'=>(int)$s4->fetchColumn()]);
    }
} catch (Throwable $e) {
    json_error('SERVER_ERROR', 'Dashboard error: '.$e->getMessage(), 500);
}
