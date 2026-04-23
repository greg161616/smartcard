<?php
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    header('Location: ../login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$profilePicturePath = '../img/default.jpg';

$picStmt = $conn->prepare("SELECT path FROM profile_picture WHERE user_id = ? ORDER BY uploaded_at DESC LIMIT 1");
$picStmt->bind_param("i", $user_id);
$picStmt->execute();
$picResult = $picStmt->get_result();
if ($picResult->num_rows > 0) {
    $profilePicturePath = $picResult->fetch_assoc()['path'];
}
$picStmt->close();
?>
<!-- ── Head Nav: Styles ── -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@700&display=swap" rel="stylesheet"/>

<style>
  :root {
    --sidebar-width: 240px;
    --header-height: 60px;
    --header-bg: #ecedf3;
    --sidebar-bg: #1a1f2e;
    --sidebar-accent: #2a3142;
    --sidebar-hover: rgba(255,255,255,0.10);
    --sidebar-active: rgba(255,255,255,0.14);
    --sidebar-text: rgba(255,255,255,0.75);
    --sidebar-muted: rgba(255,255,255,0.38);
    --transition-speed: 0.28s;
  }

  * { box-sizing: border-box; }

  body {
    margin: 0; padding: 0;
    background: #F5F0E8;
  }

  /* ── Header ── */
  .app-header {
    height: var(--header-height);
    background: var(--header-bg);
    display: flex; align-items: center;
    justify-content: space-between;
    padding: 0 16px;
    position: fixed; top: 0; left: 0;
    width: 100%; z-index: 1002;
    transition: left var(--transition-speed) ease, width var(--transition-speed) ease;
  }
  .app-header.sidebar-open {
    left: var(--sidebar-width);
    width: calc(100% - var(--sidebar-width));
  }
  @media (max-width: 768px) {
    .app-header.sidebar-open {
      left: 0 !important; width: 100% !important;
    }
  }

  .header-left { display: flex; align-items: center; gap: 10px; }

  #headerLogo { width: 38px; height: 38px; border-radius: 50%; object-fit: cover; }
  #headerTitle {
    font-family: 'Merriweather', serif;
    font-size: 16px; font-weight: 700;
    color: #111; white-space: nowrap; display: none;
  }

  .sidebar-toggle {
    width: 40px; height: 36px;
    border: 1px solid #b0b0b0; background: #ecedf3;
    border-radius: 45px; color: #000; font-size: 15px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: background 0.18s; flex-shrink: 0;
  }
  .sidebar-toggle:hover { background: rgba(0,0,0,0.18); }

  .app-header.sidebar-open #headerLogo,
  .app-header.sidebar-open #sidebarToggle { display: none !important; }

  /* ── Profile ── */
  .profile-dropdown { position: relative; display: flex; align-items: center; }
  .profile-circle {
    width: 40px; height: 40px; border-radius: 50%; object-fit: cover;
    cursor: pointer; border: 2px solid rgba(0,0,0,0.15); transition: border-color 0.2s;
  }
  .profile-circle:hover { border-color: #555; }
  .profile-dropdown-content {
    position: absolute; right: 0; top: calc(100% + 8px);
    background: #fff; border-radius: 10px; min-width: 160px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.14); z-index: 1300;
    opacity: 0; visibility: hidden;
    transition: opacity 0.2s, visibility 0.2s; pointer-events: none;
  }
  .profile-dropdown.active .profile-dropdown-content {
    opacity: 1; visibility: visible; pointer-events: auto;
  }
  .profile-dropdown-content a {
    display: flex; align-items: center; gap: 8px;
    padding: 11px 16px; color: #222; text-decoration: none; font-size: 14px;
  }
  .profile-dropdown-content a:hover { background: #e9ecef; color: #222; }
  .profile-dropdown-content hr { margin: 4px 0; border-color: #eee; }

  /* ── Overlay ── */
  .sidebar-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.38); z-index: 1001;
  }
  .sidebar-overlay.active { display: block; }

  /* ── Sidebar ── */
  .admin-sidebar {
    width: var(--sidebar-width); height: 100vh;
    background: var(--sidebar-bg);
    position: fixed; left: 0; top: 0; z-index: 1003;
    display: flex; flex-direction: column;
    box-shadow: 4px 0 24px rgba(0,0,0,0.28);
    transform: translateX(-100%);
    transition: transform var(--transition-speed) ease;
    overflow: hidden;
  }
  .admin-sidebar.open { transform: translateX(0); }

  .sidebar-topbar {
    height: var(--header-height);
    background: rgba(0,0,0,0.18);
    display: flex; align-items: center;
    gap: 10px; padding: 0 14px; flex-shrink: 0;
    border-bottom: 1px solid rgba(255,255,255,0.07);
  }
  .sidebar-topbar img { width: 30px; height: 30px; border-radius: 50%; object-fit: cover; opacity: 0.85; }
  .sidebar-topbar .sidebar-title {
    font-family: 'Merriweather', serif;
    font-size: 14px; font-weight: 700;
    color: rgba(255,255,255,0.80); flex: 1; white-space: nowrap; letter-spacing: 0.5px;
  }
  .sidebar-close-btn {
    width: 30px; height: 30px;
    background: rgba(255,255,255,0.07); border: none;
    border-radius: 50%; color: rgba(255,255,255,0.55); font-size: 15px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: background 0.18s; flex-shrink: 0;
  }
  .sidebar-close-btn:hover { background: rgba(255,255,255,0.14); color: #fff; }

  /* Portal label + user block */
  .sidebar-portal-label {
    padding: 16px 16px 0; flex-shrink: 0;
  }
  .sidebar-portal-label span {
    font-size: 9px; font-weight: 700;
    letter-spacing: 3px; text-transform: uppercase;
    color: var(--sidebar-muted);
  }
  .portal-label-rule {
    height: 1px; background: rgba(255,255,255,0.09); margin-top: 8px;
  }
  .sidebar-user-block {
    padding: 14px 16px 12px; flex-shrink: 0; cursor: pointer;
  }
  .sidebar-avatar-circle {
    width: 48px; height: 48px; border-radius: 50%;
    background: #3a8e6e;
    display: flex; align-items: center; justify-content: center;
    font-size: 17px; font-weight: 700;
    color: #fff; letter-spacing: 0.5px; margin-bottom: 10px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.3);
    border: 2px solid rgba(255,255,255,0.13);
    transition: transform 0.2s; overflow: hidden; position: relative;
  }
  .sidebar-avatar-circle img {
    width: 100%; height: 100%;
    object-fit: cover; border-radius: 50%; position: absolute; inset: 0;
  }
  .sidebar-user-block:hover .sidebar-avatar-circle { transform: scale(1.05); }
  .sidebar-user-name {
    font-size: 13.5px; font-weight: 700; color: #fff; line-height: 1.2; margin-bottom: 2px;
  }
  .sidebar-user-role { font-size: 11px; color: var(--sidebar-muted); font-weight: 400; }

  .sidebar-content {
    flex: 1; overflow-y: auto; overflow-x: hidden;
    padding: 8px 0; min-height: 0;
  }
  .sidebar-content::-webkit-scrollbar { width: 4px; }
  .sidebar-content::-webkit-scrollbar-thumb { background: rgba(255,255,255,0.15); border-radius: 4px; }

  .admin-sidebar .nav-link {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 14px; margin: 2px 8px; border-radius: 8px;
    color: var(--sidebar-text); text-decoration: none; font-size: 13.5px;
    transition: background 0.18s, color 0.18s;
  }
  .admin-sidebar .nav-link i { font-size: 16px; min-width: 20px; opacity: 0.75; }
  .admin-sidebar .nav-link:hover { background: var(--sidebar-hover); color: #fff; }
  .admin-sidebar .nav-link:hover i { opacity: 1; }
  .admin-sidebar .nav-link.active { background: var(--sidebar-active); color: #fff; font-weight: 600; }
  .admin-sidebar .nav-link.active i { opacity: 1; }

  /* Collapse sub-menu items */
  .admin-sidebar .collapse .nav-link {
    font-size: 13px; padding: 8px 12px;
    margin: 1px 8px 1px 24px;
  }
  .nav-link .bi-caret-down-fill {
    font-size: 11px; margin-left: auto;
    flex-shrink: 0; transition: transform 0.25s ease;
  }

  .sidebar-footer { padding: 12px; border-top: 1px solid rgba(255,255,255,0.08); flex-shrink: 0; }
  .sidebar-footer .logout-link {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 14px; border-radius: 8px;
    color: var(--sidebar-muted); text-decoration: none; font-size: 13.5px;
    transition: background 0.18s, color 0.18s;
  }
  .sidebar-footer .logout-link:hover { background: var(--sidebar-hover); color: #fff; }

  /* ── Page content wrapper ── */
  .page-content {
    padding-top: var(--header-height);
    min-height: 100vh;
    margin-left: 0;
    transition: margin-left var(--transition-speed) ease;
  }
  .page-content.sidebar-open { margin-left: var(--sidebar-width); }
  @media (max-width: 768px) {
    .page-content.sidebar-open { margin-left: 0 !important; }
  }
</style>

<!-- Pre-open state to avoid layout flicker -->
<script>
  (function() {
    var open = localStorage.getItem('sidebarOpen_admin') === 'true';
    window.__sidebarInitOpen = open;
    if (open && window.innerWidth > 768) {
      document.documentElement.classList.add('nav-preopen-head');
    }
  })();
</script>
<style>
  html.nav-preopen-head .page-content { margin-left: var(--sidebar-width) !important; }
  html.nav-preopen-head .app-header {
    left: var(--sidebar-width) !important;
    width: calc(100% - var(--sidebar-width)) !important;
  }
</style>

<!-- ── Header ── -->
<nav class="app-header" id="appHeader">
  <div class="header-left">
    <img src="../img/logo.png" alt="Logo" id="headerLogo">
    <span id="headerTitle">SMARTCARD</span>
    <button class="sidebar-toggle" id="sidebarToggle" title="Toggle Sidebar">
      <i class="bi bi-layout-sidebar" id="toggleIcon"></i>
    </button>
  </div>
  <div class="profile-dropdown" id="profileDropdown">
    <img src="<?php echo htmlspecialchars($profilePicturePath); ?>" alt="Profile" class="profile-circle">
    <div class="profile-dropdown-content">
      <a href="profile.php"><i class="bi bi-person"></i> Profile</a>
      <hr>
      <a href="../logout.php" class="text-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </div>
  </div>
</nav>

<!-- ── Overlay ── -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ── Sidebar ── -->
<?php
  $h_full_name = isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name'])
              : (isset($_SESSION['email']) ? htmlspecialchars($_SESSION['email']) : 'Head');
  $h_parts    = explode(' ', trim($h_full_name));
  $h_initials = count($h_parts) >= 2
              ? strtoupper(substr($h_parts[0],0,1).substr(end($h_parts),0,1))
              : strtoupper(substr($h_full_name,0,2));
  $h_has_pic  = ($profilePicturePath !== '../img/default.jpg') && file_exists($profilePicturePath);
?>
<div class="admin-sidebar" id="adminSidebar">
  <div class="sidebar-topbar">
    <img src="../img/logo.png" alt="Logo">
    <span class="sidebar-title">SMARTCARD</span>
    <button class="sidebar-close-btn" id="sidebarCloseBtn" title="Close">
      <i class="bi bi-layout-sidebar-reverse"></i>
    </button>
  </div>

  <div class="sidebar-portal-label">
    <span>Head Portal</span>
    <div class="portal-label-rule"></div>
  </div>

  <div class="sidebar-user-block">
    <div class="sidebar-avatar-circle">
      <?php if ($h_has_pic): ?>
        <img src="<?php echo htmlspecialchars($profilePicturePath); ?>" alt="Profile" onerror="this.style.display='none';">
      <?php else: ?>
        <?php echo $h_initials; ?>
      <?php endif; ?>
    </div>
    <div class="sidebar-user-name"><?php echo $h_full_name; ?></div>
    <div class="sidebar-user-role">Head · Administration</div>
  </div>

  <div class="sidebar-content">
    <nav class="nav flex-column px-1 mt-1">

      <a class="nav-link" href="../administration/dashboard.php">
        <i class="bi bi-speedometer2"></i><span>Dashboard</span>
      </a>

      <!-- Master List (collapsible) -->
      <a class="nav-link" id="masterListToggle" role="button" style="cursor:pointer;">
        <i class="bi bi-people"></i><span>Master List</span>
        <i class="bi bi-caret-down-fill" id="masterCaret"></i>
      </a>
      <div id="masterListCollapse" style="overflow:hidden; max-height:0; transition:max-height 0.28s ease;">
        <a class="nav-link" href="../administration/studentlist.php">
          <i class="bi bi-person"></i><span>Student</span>
        </a>
        <a class="nav-link" href="../administration/teacher.php">
          <i class="bi bi-person-badge"></i><span>Teacher</span>
        </a>
      </div>

      <a class="nav-link" href="../administration/section.php">
        <i class="bi bi-building"></i><span>Sections</span>
      </a>
      <a class="nav-link" href="../administration/subject.php">
        <i class="bi bi-book"></i><span>Subjects</span>
      </a>
      <a class="nav-link" href="../administration/view_logs.php">
        <i class="bi bi-journal-text"></i><span>Logs</span>
      </a>
      <a class="nav-link" href="../administration/backup.php">
        <i class="bi bi-cloud-arrow-up"></i><span>Backup &amp; Restore</span>
      </a>

    </nav>
  </div>
  <div class="sidebar-footer">
    <a href="../logout.php" class="logout-link">
      <i class="bi bi-box-arrow-right"></i><span>Sign Out</span>
    </a>
  </div>
</div>

<!-- ── Page content wrapper opens here (close </div> before </body> in each page) ── -->
<div class="page-content" id="pageContent">

<script>
document.addEventListener('DOMContentLoaded', function () {
  const sidebar     = document.getElementById('adminSidebar');
  const overlay     = document.getElementById('sidebarOverlay');
  const toggleBtn   = document.getElementById('sidebarToggle');
  const closeBtn    = document.getElementById('sidebarCloseBtn');
  const toggleIcon  = document.getElementById('toggleIcon');
  const header      = document.getElementById('appHeader');
  const pageContent = document.getElementById('pageContent');
  const profileDrop = document.getElementById('profileDropdown');

  var isOpen = window.__sidebarInitOpen || false;

  function isMobile() { return window.innerWidth <= 768; }

  function applyState(open) {
    document.documentElement.classList.remove('nav-preopen-head');
    if (open && !isMobile()) {
      sidebar.classList.add('open');
      header.classList.add('sidebar-open');
      pageContent.classList.add('sidebar-open');
      overlay.classList.remove('active');
      toggleIcon.className = 'bi bi-layout-sidebar-reverse';
    } else if (open && isMobile()) {
      sidebar.classList.add('open');
      overlay.classList.add('active');
      header.classList.remove('sidebar-open');
      pageContent.classList.remove('sidebar-open');
      toggleIcon.className = 'bi bi-layout-sidebar-reverse';
    } else {
      sidebar.classList.remove('open');
      overlay.classList.remove('active');
      header.classList.remove('sidebar-open');
      pageContent.classList.remove('sidebar-open');
      toggleIcon.className = 'bi bi-layout-sidebar';
    }
  }

  applyState(isOpen);

  function openSidebar()   { isOpen = true;  localStorage.setItem('sidebarOpen_admin', 'true');  applyState(true); }
  function closeSidebar()  { isOpen = false; localStorage.setItem('sidebarOpen_admin', 'false'); applyState(false); }
  function toggleSidebar() { isOpen ? closeSidebar() : openSidebar(); }

  toggleBtn.addEventListener('click', toggleSidebar);
  closeBtn.addEventListener('click',  closeSidebar);
  overlay.addEventListener('click',   closeSidebar);
  window.addEventListener('resize', function() { applyState(isOpen); });

  // Master List collapsible
  const masterToggle   = document.getElementById('masterListToggle');
  const masterCollapse = document.getElementById('masterListCollapse');
  const masterCaret    = document.getElementById('masterCaret');
  let masterOpen = false;

  function setMaster(open, animate) {
    masterOpen = open;
    masterCollapse.style.transition = animate ? 'max-height 0.28s ease' : 'none';
    masterCollapse.style.maxHeight  = open ? masterCollapse.scrollHeight + 'px' : '0';
    masterCaret.style.transform = open ? 'rotate(180deg)' : 'rotate(0deg)';
  }

  masterToggle.addEventListener('click', function() { setMaster(!masterOpen, true); });

  // Active link highlighting + auto-expand Master List
  const links = document.querySelectorAll('.admin-sidebar .nav-link');
  const currentPath = window.location.pathname.replace(/\\/g, '/');
  links.forEach(function(link) {
    const href = link.getAttribute('href');
    if (!href || href === '#') return;
    if (currentPath.endsWith(href.replace(/^\.\./, ''))) {
      link.classList.add('active');
      if (href.includes('/administration/studentlist.php') || href.includes('/administration/teacher.php')) {
        setMaster(true, false);
      }
    }
  });

  // Profile dropdown
  let profileTimeout;
  profileDrop.addEventListener('mouseenter', function() { clearTimeout(profileTimeout); profileDrop.classList.add('active'); });
  profileDrop.addEventListener('mouseleave', function() { profileTimeout = setTimeout(function() { profileDrop.classList.remove('active'); }, 150); });
});
</script>