<?php
/**
 * PalliCare Setup
 * Visit once after uploading. DELETE this file after setup!
 */
$error = $success = $info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host   = trim($_POST['host']    ?? 'localhost');
    $dbname = trim($_POST['dbname']  ?? '');
    $dbuser = trim($_POST['dbuser']  ?? '');
    $dbpass = $_POST['dbpass']       ?? '';

    if (!$dbname || !$dbuser) {
        $error = 'Database name and username are required.';
    } else {
        try {
            $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $dbuser, $dbpass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `$dbname`");

            // Run schema (tables only)
            $sql = file_get_contents(__DIR__ . '/database/schema.sql');
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                if ($stmt) $pdo->exec($stmt);
            }

            // ── Generate ALL hashes fresh with PHP ──────────────────────────
            $h123 = password_hash('password123', PASSWORD_BCRYPT, ['cost' => 12]);

            // Seed users with PHP-generated hashes
            $users = [
                ['id'=>'u-admin-001', 'name'=>'System Admin',    'email'=>'admin@pallicare.dev',      'phone'=>null, 'hash'=>$h123, 'role'=>'ADMIN',        'status'=>'ACTIVE', 'cwp'=>0],
                ['id'=>'u-doc-001',   'name'=>'Dr. Abdul Karim', 'email'=>'dr.karim@pallicare.dev',   'phone'=>null, 'hash'=>$h123, 'role'=>'DOCTOR',       'status'=>'ACTIVE', 'cwp'=>0],
                ['id'=>'u-doc-002',   'name'=>'Dr. Fatema Mina', 'email'=>'dr.mina@pallicare.dev',    'phone'=>null, 'hash'=>$h123, 'role'=>'DOCTOR',       'status'=>'ACTIVE', 'cwp'=>0],
                ['id'=>'u-hw-001',    'name'=>'Abdur Rahim',     'email'=>'hw.rahim@pallicare.dev',   'phone'=>'01712345678', 'hash'=>$h123, 'role'=>'HEALTH_WORKER','status'=>'ACTIVE', 'cwp'=>1],
                ['id'=>'u-hw-002',    'name'=>'Nasrin Akter',    'email'=>'hw.nasrin@pallicare.dev',  'phone'=>'01823456789', 'hash'=>$h123, 'role'=>'HEALTH_WORKER','status'=>'ACTIVE', 'cwp'=>1],
                ['id'=>'u-hw-003',    'name'=>'Jalal Uddin',     'email'=>'hw.jalal@pallicare.dev',   'phone'=>'01934567890', 'hash'=>$h123, 'role'=>'HEALTH_WORKER','status'=>'PENDING','cwp'=>0],
            ];
            $uStmt = $pdo->prepare("INSERT IGNORE INTO users(id,name,email,phone,password_hash,role,status,can_write_prescription) VALUES(:id,:name,:email,:phone,:hash,:role,:status,:cwp)");
            foreach ($users as $u) $uStmt->execute([':id'=>$u['id'],':name'=>$u['name'],':email'=>$u['email'],':phone'=>$u['phone'],':hash'=>$u['hash'],':role'=>$u['role'],':status'=>$u['status'],':cwp'=>$u['cwp']]);

            // Assignments
            $pdo->exec("INSERT IGNORE INTO doctor_assignments(id,doctor_id,health_worker_id) VALUES('a-001','u-doc-001','u-hw-001'),('a-002','u-doc-001','u-hw-002')");

            // Medicines
            $meds = [
                ['m-001','Paracetamol 500mg','Paracetamol','TABLET'],
                ['m-002','Amoxicillin 500mg','Amoxicillin','CAPSULE'],
                ['m-003','Metformin 500mg','Metformin','TABLET'],
                ['m-004','Omeprazole 20mg','Omeprazole','CAPSULE'],
                ['m-005','Amlodipine 5mg','Amlodipine','TABLET'],
                ['m-006','Cetirizine 10mg','Cetirizine','TABLET'],
                ['m-007','Azithromycin 500mg','Azithromycin','TABLET'],
                ['m-008','Antacid Suspension','Magnesium Hydroxide','SYRUP'],
                ['m-009','Salbutamol Inhaler','Salbutamol','INHALER'],
                ['m-010','ORS Powder','Oral Rehydration Salts','OTHER'],
                ['m-011','Zinc 20mg','Zinc Sulphate','TABLET'],
                ['m-012','Vitamin C 500mg','Ascorbic Acid','TABLET'],
                ['m-013','Iron + Folate','Ferrous Sulphate','TABLET'],
                ['m-014','Clotrimazole Cream','Clotrimazole','OINTMENT'],
                ['m-015','Diclofenac 50mg','Diclofenac Sodium','TABLET'],
                ['m-016','Metronidazole 400mg','Metronidazole','TABLET'],
                ['m-017','Ciprofloxacin 500mg','Ciprofloxacin','TABLET'],
                ['m-018','Cough Syrup','Dextromethorphan','SYRUP'],
                ['m-019','Eye Drops (Chloram.)','Chloramphenicol','DROPS'],
                ['m-020','Atenolol 50mg','Atenolol','TABLET'],
            ];
            $mStmt = $pdo->prepare("INSERT IGNORE INTO medicines(id,name,generic_name,form,is_active) VALUES(:id,:name,:gen,:form,1)");
            foreach ($meds as [$id,$name,$gen,$form]) $mStmt->execute([':id'=>$id,':name'=>$name,':gen'=>$gen,':form'=>$form]);

            // Sample prescription
            $pdo->exec("INSERT IGNORE INTO prescriptions(id,health_worker_id,patient_name,patient_age,patient_gender,chief_complaints,on_examination,advice,status,reviewed_by_id,reviewed_at,review_notes)
                VALUES('rx-001','u-hw-001','Mohammad Hossain',45,'male','Fever, cough, sore throat for 3 days','Temp: 38.5°C, Throat congested','Rest, drink plenty of fluids','REVIEWED','u-doc-001',NOW(),'Approved.')");
            $pdo->exec("INSERT IGNORE INTO prescription_items(id,prescription_id,medicine_id,dose,frequency,duration,instructions)
                VALUES('pi-001','rx-001','m-001','1 tablet','3 times daily','5 days','After meals'),
                      ('pi-002','rx-001','m-002','1 capsule','2 times daily','7 days','After meals')");

            // Write DB config
            $cfg = file_get_contents(__DIR__ . '/config/database.php');
            $cfg = preg_replace("/'localhost'/",  "'$host'",   $cfg, 1);
            $cfg = preg_replace("/'your_db_name'/","'$dbname'", $cfg, 1);
            $cfg = preg_replace("/'your_db_user'/","'$dbuser'", $cfg, 1);
            $cfg = preg_replace("/'your_db_password'/","'$dbpass'", $cfg, 1);
            file_put_contents(__DIR__ . '/config/database.php', $cfg);

            $success = true;
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>PalliCare Setup</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,'Inter',sans-serif;background:#f1f5f9;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.box{background:#fff;border-radius:16px;padding:32px;max-width:460px;width:100%;box-shadow:0 4px 24px rgba(0,0,0,.08);border:1px solid #e2e8f0}
h1{font-size:20px;font-weight:700;margin-bottom:4px}
.sub{font-size:13px;color:#64748b;margin-bottom:22px}
.warn{background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:12px 14px;font-size:12px;color:#92400e;margin-bottom:20px}
label{display:block;font-size:12px;font-weight:500;color:#334155;margin-bottom:4px;margin-top:12px}
input{width:100%;padding:9px 12px;border:1px solid #e2e8f0;border-radius:8px;font-size:13px;font-family:inherit;outline:none}
input:focus{border-color:#2998ab;box-shadow:0 0 0 3px rgba(41,152,171,.12)}
.hint{font-size:11px;color:#94a3b8;margin-top:3px}
.btn{width:100%;margin-top:18px;padding:11px;background:#2998ab;color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer}
.btn:hover{background:#1e7a8c}
.alert-e{background:#fef2f2;color:#dc2626;border:1px solid #fecaca;border-radius:8px;padding:12px;font-size:13px;margin-bottom:16px}
.alert-s{background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;padding:18px;font-size:13px;margin-bottom:16px}
.creds{background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:14px;margin-top:14px;font-size:12px}
.creds p{margin-bottom:5px;color:#475569}
code{background:#e2e8f0;padding:1px 6px;border-radius:4px;font-family:monospace;color:#1e293b}
a.go{display:block;text-align:center;margin-top:16px;background:#2998ab;color:#fff;padding:11px;border-radius:8px;text-decoration:none;font-weight:600;font-size:14px}
</style>
</head>
<body>
<div class="box">
<h1>🏥 PalliCare Setup</h1>
<p class="sub">Connect your Namecheap cPanel MySQL database</p>

<?php if ($success): ?>
<div class="alert-s">
  ✅ <strong>Setup complete!</strong> All tables created, users seeded with correct passwords.
</div>
<div class="creds">
  <p><strong>All demo passwords: <code>password123</code></strong></p>
  <p>Admin: <code>admin@pallicare.dev</code></p>
  <p>Doctor: <code>dr.karim@pallicare.dev</code></p>
  <p>Doctor: <code>dr.mina@pallicare.dev</code></p>
  <p>Health Worker: <code>hw.rahim@pallicare.dev</code></p>
  <p>Health Worker: <code>hw.nasrin@pallicare.dev</code></p>
  <p style="color:#dc2626;margin-top:8px">⚠️ Delete <code>setup.php</code> from File Manager now!</p>
</div>
<a class="go" href="/">→ Go to PalliCare Login</a>

<?php else: ?>
<div class="warn">⚠️ <strong>Delete this file after setup is complete.</strong></div>
<?php if ($error): ?><div class="alert-e"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<form method="POST">
  <label>MySQL Host</label>
  <input name="host" value="localhost"/>
  <p class="hint">Usually "localhost" on Namecheap</p>

  <label>Database Name *</label>
  <input name="dbname" placeholder="youraccount_pallicare" required/>
  <p class="hint">From cPanel → MySQL Databases</p>

  <label>Database Username *</label>
  <input name="dbuser" placeholder="youraccount_user" required/>

  <label>Database Password</label>
  <input name="dbpass" type="password" placeholder="Your DB password"/>

  <button type="submit" class="btn">Run Setup & Create Tables</button>
</form>
<?php endif; ?>
</div>
</body>
</html>
