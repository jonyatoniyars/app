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
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=5,viewport-fit=cover"/>
<meta name="apple-mobile-web-app-capable" content="yes"/>
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"/>
<meta name="theme-color" content="#2998ab"/>
<title>BPDA Telemedicine App</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="assets/css/style.css"/>
<link rel="stylesheet" href="assets/css/mobile.css"/>
<link rel="stylesheet" href="assets/css/footer.css"/>
</head>
<body class="<?= $isLoggedIn ? 'app-mode' : 'auth-mode' ?>">

<?php if (!$isLoggedIn): ?>
<div class="auth-shell">
  <div class="auth-brand" onclick="showCard('login-card')" style="cursor:pointer">
    <div class="brand-icon">
      <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
    </div>
    <div>
      <div class="brand-name">BPDA Telemedicine</div>
      <div class="brand-sub">Smart App Platform</div>
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
    <div style="display:flex;justify-content:flex-end;align-items:center;margin-bottom:8px">
      <button class="btn btn-primary btn-block" id="login-btn" onclick="doLogin()">Sign In</button>
    </div>
    <p class="auth-switch">Don't have an account? <a href="#" onclick="showCard('register-card')">Register</a></p>
  </div>

  <!-- REGISTER -->
  <div class="auth-card" id="register-card" style="display:none">
    <h2 class="auth-title">Create Account</h2>
    <p class="auth-sub">Requires admin approval before login</p>
    <div id="reg-error"   class="alert alert-error"   style="display:none"></div>
    <div id="reg-success" class="alert alert-success" style="display:none">✅ Registered! Wait for admin approval.</div>
    <div class="form-group">
      <label class="form-label">Full Name</label>
      <input class="form-input" id="reg-name" type="text" placeholder="Your Name"/>
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
    <div class="sb-logo" onclick="go('dashboard')" style="cursor:pointer">
      <div class="sb-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
      </div>
      <div class="desktop-only">
        <div class="sb-name">BPDA Telemedicine</div>
        <div class="sb-sub">Smart App Platform</div>
      </div>
    </div>
    <nav class="sb-nav" id="sb-nav"></nav>
    <button class="mobile-menu-btn mobile-only" onclick="toggleMobileMenu()" title="Toggle Menu">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="3" y1="6" x2="21" y2="6"></line>
        <line x1="3" y1="12" x2="21" y2="12"></line>
        <line x1="3" y1="18" x2="21" y2="18"></line>
      </svg>
      Menu
    </button>
    <div class="sb-footer desktop-only">
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
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
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
window.ROLE = '<?= $user['role'] ?>';
const USER = <?= json_encode(['id'=>$user['id'],'name'=>$user['name'],'role'=>$user['role'],'canWritePrescription'=>$user['canWritePrescription'],'superAdminId'=>defined('SUPER_ADMIN_ID')?SUPER_ADMIN_ID:null]) ?>;

function toggleMobileMenu() {
  const nav = document.getElementById('sb-nav');
  const btn = document.querySelector('.mobile-menu-btn');
  nav.classList.toggle('mobile-open');
  btn.classList.toggle('active');
}

// Close menu when an item is clicked
document.addEventListener('DOMContentLoaded', function() {
  const navItems = document.querySelectorAll('.nav-item');
  navItems.forEach(item => {
    item.addEventListener('click', () => {
      const nav = document.getElementById('sb-nav');
      const btn = document.querySelector('.mobile-menu-btn');
      nav.classList.remove('mobile-open');
      btn.classList.remove('active');
    });
  });
});
</script>
<?php endif; ?>

<script src="assets/js/app.js"></script>
<script src="assets/js/admin.js"></script>
<script src="assets/js/pages.js"></script>

<!-- Footer (visible only in app mode) -->
<?php if ($isLoggedIn): ?>
<footer class="app-footer">
  <div class="footer-left">
    <a href="#" class="footer-link" onclick="pgAbout()">About Us</a>
    <a href="#" class="footer-link" onclick="pgPrivacy()">Privacy Policy</a>
    <a href="#" class="footer-link" onclick="pgTerms()">Terms & Conditions</a>
  </div>
  
  <div class="footer-social">
    <a href="https://facebook.com/bpda-telemedicine" target="_blank" class="social-icon" title="Facebook">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
    </a>
    <a href="https://instagram.com/bpda-telemedicine" target="_blank" class="social-icon" title="Instagram">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0m0 22C6.477 22 2 17.523 2 12S6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/><path d="M12 5.5c-3.584 0-6.5 2.916-6.5 6.5s2.916 6.5 6.5 6.5 6.5-2.916 6.5-6.5-2.916-6.5-6.5-6.5zm0 11c-2.481 0-4.5-2.019-4.5-4.5s2.019-4.5 4.5-4.5 4.5 2.019 4.5 4.5-2.019 4.5-4.5 4.5z"/></svg>
    </a>
    <a href="https://wa.me/1234567890" target="_blank" class="social-icon" title="WhatsApp">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.67-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.076 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421-7.403h-.004c-1.025 0-2.031.312-2.896.902C7.969 8.164 7.2 9.39 7.2 10.9c0 .789.21 1.561.611 2.246l.196.314-1.265 3.63 3.738-1.23.41.195c.712.344 1.514.528 2.322.528h.004c2.976 0 5.4-2.414 5.4-5.39 0-1.439-.561-2.79-1.58-3.809-1.02-1.02-2.37-1.581-3.809-1.581"/></svg>
    </a>
    <a href="https://youtube.com/@bpda-telemedicine" target="_blank" class="social-icon" title="YouTube">
      <svg viewBox="0 0 24 24" fill="currentColor"><path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
    </a>
  </div>

  <div class="footer-chat">
    <button class="chat-btn" onclick="openTwakChat()">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path></svg>
      Live Chat
    </button>
  </div>
</footer>

<!-- Twak.to Chat Widget -->
<script>
function openTwakChat() {
  if(window.Tawk_API){
    window.Tawk_API.toggle();
  }
}
</script>
<script type="text/javascript">
var Tawk_API=Tawk_API||{}, Tawk_LoadStart=new Date();
(function(){
var s1=document.createElement("script"),s0=document.getElementsByTagName("script")[0];
s1.async=true;
s1.src='https://embed.tawk.to/YOUR_TAWK_PROPERTY_ID/DEFAULT';
s1.charset='UTF-8';
s1.setAttribute('crossorigin','*');
s0.parentNode.insertBefore(s1,s0);
})();
</script>
<?php endif; ?>

</body>
</html>
