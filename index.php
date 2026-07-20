<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/helpers.php';

$user       = current_user();
$isLoggedIn = $user && $user['status'] === 'ACTIVE';

// CRITICAL: release session file lock so API calls don't block
session_write_close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>PalliCare — Community Health Platform</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="assets/css/style.css"/>
</head>
<body class="<?= $isLoggedIn ? 'app-mode' : 'auth-mode' ?>">

<?php if (!$isLoggedIn): ?>
<div class="auth-shell">
  <div class="auth-brand">
    <div class="brand-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
    </div>
    <div>
      <div class="brand-name">PalliCare</div>
      <div class="brand-sub">Community Health Platform</div>
    </div>
  </div>

  <!-- LOGIN -->
  <div class="auth-card" id="login-card">
    <h2 class="auth-title">Sign In</h2>
    <p class="auth-sub">Enter your credentials to continue</p>
    <div id="login-error" class="alert alert-error" style="display:none"></div>
    <div class="form-group">
      <label class="form-label">Email or Phone</label>
      <input class="form-input" id="login-id" type="text" placeholder="01712345678 or email@example.com" autocomplete="username"/>
    </div>
    <div class="form-group">
      <label class="form-label">Password</label>
      <input class="form-input" id="login-pw" type="password" placeholder="••••••••" autocomplete="current-password"/>
    </div>
    <button class="btn btn-primary btn-block" id="login-btn" onclick="doLogin()">Sign In</button>
    <p class="auth-switch">Don't have an account? <a href="#" onclick="showCard('register-card')">Register</a></p>
    <p class="demo-hint">Demo password: <code>password123</code></p>
  </div>

  <!-- REGISTER -->
  <div class="auth-card" id="register-card" style="display:none">
    <h2 class="auth-title">Create Account</h2>
    <p class="auth-sub">Requires admin approval before login</p>
    <div id="reg-error"   class="alert alert-error"   style="display:none"></div>
    <div id="reg-success" class="alert alert-success" style="display:none">✅ Registered! Wait for admin approval.</div>
    <div class="form-group">
      <label class="form-label">Full Name</label>
      <input class="form-input" id="reg-name" type="text" placeholder="Abdur Rahim"/>
    </div>
    <div class="form-group">
      <label class="form-label">Role</label>
      <select class="form-input" id="reg-role">
        <option value="HEALTH_WORKER">Community Health Worker</option>
        <option value="DOCTOR">Doctor</option>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">Email <span class="text-muted">(optional if phone given)</span></label>
      <input class="form-input" id="reg-email" type="email" placeholder="email@example.com"/>
    </div>
    <div class="form-group">
      <label class="form-label">Phone <span class="text-muted">(optional if email given)</span></label>
      <input class="form-input" id="reg-phone" type="tel" placeholder="01712345678"/>
    </div>
    <div class="form-group">
      <label class="form-label">Password <span class="text-muted">(min 6 chars)</span></label>
      <input class="form-input" id="reg-pw" type="password" placeholder="••••••••"/>
    </div>
    <button class="btn btn-primary btn-block" id="reg-btn" onclick="doRegister()">Register</button>
    <p class="auth-switch">Already have an account? <a href="#" onclick="showCard('login-card')">Sign in</a></p>
  </div>
</div>

<?php else: ?>
<div class="shell">
  <aside class="sidebar">
    <div class="sb-logo">
      <div class="sb-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
      </div>
      <div>
        <div class="sb-name">PalliCare</div>
        <div class="sb-sub">Health Platform</div>
      </div>
    </div>
    <nav class="sb-nav" id="sb-nav"></nav>
    <div class="sb-footer">
      <div class="sb-user">
        <div class="sb-avatar"><?= strtoupper(substr($user['name'],0,1)) ?></div>
        <div class="sb-info">
          <div class="sb-uname"><?= htmlspecialchars($user['name']) ?></div>
          <div class="sb-uemail"><?= htmlspecialchars($user['email'] ?? $user['phone'] ?? '') ?></div>
        </div>
      </div>
      <span class="role-badge role-<?= strtolower(str_replace('_','-',$user['role'])) ?>">
        <?= $user['role']==='HEALTH_WORKER' ? 'Health Worker' : ucfirst(strtolower($user['role'])) ?>
      </span>
      <button class="sb-logout" onclick="doLogout()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Sign out
      </button>
    </div>
  </aside>
  <main class="main-content">
    <div class="page-wrap" id="page-wrap">
      <div class="loading-center"><div class="spinner"></div></div>
    </div>
  </main>
</div>
<script>
const ROLE = '<?= $user['role'] ?>';
const USER = <?= json_encode(['id'=>$user['id'],'name'=>$user['name'],'role'=>$user['role'],'canWritePrescription'=>$user['canWritePrescription']]) ?>;
</script>
<?php endif; ?>

<script src="assets/js/app.js"></script>
</body>
</html>
