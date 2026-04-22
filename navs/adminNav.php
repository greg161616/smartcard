<?php
// Get profile picture path
$profilePicturePath = '../img/default.jpg';
if (isset($_SESSION['user_id'])) {
    $userID = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT path FROM profile_picture WHERE user_id = ? ORDER BY uploaded_at DESC LIMIT 1");
    $stmt->bind_param("i", $userID);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $profilePicturePath = $result->fetch_assoc()['path'];
    }
    $stmt->close();
}
?>
<!-- ── Admin Nav: Styles ── -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@700&display=swap" rel="stylesheet"/>

<style>
  :root {
    --sidebar-width: 220px;
    --header-height: 60px;
    --header-bg: #ecedf3;
    --sidebar-bg: #ffffff;
    --sidebar-hover: #4f5560;
    --transition-speed: 0.28s;
  }

  * { box-sizing: border-box; }

  body {
    margin: 0;
    padding: 0;
    background: #ecedf3;
  }

  /* ── Header ── */
  .app-header {
    height: var(--header-height);
    background: var(--header-bg);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 16px;
    position: fixed;
    top: 0; left: 0;
    width: 100%;
    z-index: 1002;
    transition: left var(--transition-speed) ease, width var(--transition-speed) ease;
  }
  .app-header.sidebar-open {
    left: var(--sidebar-width);
    width: calc(100% - var(--sidebar-width));
  }
  @media (max-width: 768px) {
    .app-header.sidebar-open {
      left: 0 !important;
      width: 100% !important;
    }
  }

  .header-left {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  #headerLogo {
    width: 38px; height: 38px;
    border-radius: 50%; object-fit: cover;
  }

  #headerTitle {
    font-family: 'Merriweather', serif;
    font-size: 16px; font-weight: 700;
    color: #111; white-space: nowrap;
    display: none;
  }

  .sidebar-toggle {
    width: 40px; height: 36px;
    border: 1px solid #b0b0b0;
    background: #ecedf3;
    border-radius: 45px;
    color: #000; font-size: 15px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer;
    transition: background 0.18s;
    flex-shrink: 0;
  }
  .sidebar-toggle:hover { background: rgba(0,0,0,0.18); }

  /* Hide logo & toggle when sidebar open on desktop */
  .app-header.sidebar-open #headerLogo,
  .app-header.sidebar-open #sidebarToggle {
    display: none !important;
  }

  /* ── Profile ── */
  .profile-dropdown {
    position: relative;
    display: flex; align-items: center;
  }
  .profile-circle {
    width: 40px; height: 40px;
    border-radius: 50%; object-fit: cover;
    cursor: pointer;
    border: 2px solid rgba(0,0,0,0.15);
    transition: border-color 0.2s;
  }
  .profile-circle:hover { border-color: #555; }
  .profile-dropdown-content {
    position: absolute;
    right: 0; top: calc(100% + 8px);
    background: #fff; border-radius: 10px;
    min-width: 160px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.14);
    z-index: 1300;
    opacity: 0; visibility: hidden;
    transition: opacity 0.2s, visibility 0.2s;
    pointer-events: none;
  }
  .profile-dropdown.active .profile-dropdown-content {
    opacity: 1; visibility: visible; pointer-events: auto;
  }
  .profile-dropdown-content a {
    display: flex; align-items: center; gap: 8px;
    padding: 11px 16px; color: #222;
    text-decoration: none; font-size: 14px;
  }
  .profile-dropdown-content a:hover { background: #e9ecef; color: #222; }
  .profile-dropdown-content hr { margin: 4px 0; border-color: #eee; }

  /* ── Overlay (mobile) ── */
  .sidebar-overlay {
    display: none; position: fixed; inset: 0;
    background: rgba(0,0,0,0.38); z-index: 1001;
  }
  .sidebar-overlay.active { display: block; }

  /* ── Sidebar ── */
  .admin-sidebar {
    width: var(--sidebar-width);
    height: 100vh;
    background: var(--sidebar-bg);
    position: fixed; left: 0; top: 0;
    z-index: 1003;
    display: flex; flex-direction: column;
    box-shadow: 3px 0 18px rgba(0,0,0,0.13);
    transform: translateX(-100%);
    transition: transform var(--transition-speed) ease;
    overflow: hidden;
  }
  .admin-sidebar.open { transform: translateX(0); }

  .sidebar-topbar {
    height: var(--header-height);
    background: var(--header-bg);
    display: flex; align-items: center;
    gap: 10px; padding: 0 12px; flex-shrink: 0;
  }
  .sidebar-topbar img {
    width: 34px; height: 34px;
    border-radius: 50%; object-fit: cover;
  }
  .sidebar-topbar .sidebar-title {
    font-family: 'Merriweather', serif;
    font-size: 15px; font-weight: 700;
    color: #555; flex: 1; white-space: nowrap;
  }
  .sidebar-close-btn {
    width: 32px; height: 32px;
    background: #ecedf3; border: none;
    border-radius: 50%; color: #6b6b6b; font-size: 17px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: background 0.18s; flex-shrink: 0;
  }
  .sidebar-close-btn:hover { background: rgba(0,0,0,0.18); }

  .sidebar-content {
    flex: 1; overflow-y: auto; overflow-x: hidden;
    padding: 8px 0; min-height: 0;
  }
  .sidebar-content::-webkit-scrollbar { width: 4px; }
  .sidebar-content::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }

  .admin-sidebar .nav-link {
    display: flex; align-items: center; gap: 10px;
    padding: 10px 16px; margin: 2px 8px;
    border-radius: 8px; color: #333;
    text-decoration: none; font-size: 14.5px;
    transition: background 0.18s, color 0.18s;
  }
  .admin-sidebar .nav-link i { font-size: 17px; min-width: 20px; }
  .admin-sidebar .nav-link:hover,
  .admin-sidebar .nav-link.active {
    background: var(--sidebar-hover); color: #fff;
  }

  .sidebar-footer {
    padding: 10px;
    border-top: 1px solid #ebebeb; flex-shrink: 0;
  }

  /* ── Page content wrapper ── */
  .page-content {
    padding-top: var(--header-height);
    min-height: 100vh;
    margin-left: 0;
    transition: margin-left var(--transition-speed) ease;
  }
  .page-content.sidebar-open {
    margin-left: var(--sidebar-width);
  }
  @media (max-width: 768px) {
    .page-content.sidebar-open {
      margin-left: 0 !important;
    }
  }
</style>

<!-- Pre-open: apply initial state before paint to avoid flicker -->
<script>
  (function() {
    var open = localStorage.getItem('sidebarOpen_principal') === 'true';
    window.__sidebarInitOpen = open;
    if (open && window.innerWidth > 768) {
      document.documentElement.classList.add('nav-preopen-admin');
    }
  })();
</script>
<style>
  html.nav-preopen-admin .page-content { margin-left: var(--sidebar-width) !important; }
  html.nav-preopen-admin .app-header {
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
<div class="admin-sidebar" id="adminSidebar">
  <div class="sidebar-topbar">
    <img src="../img/logo.png" alt="Logo">
    <span class="sidebar-title">SMARTCARD</span>
    <button class="sidebar-close-btn" id="sidebarCloseBtn" title="Close">
      <i class="bi bi-layout-sidebar-reverse"></i>
    </button>
  </div>
  <div class="sidebar-content">
    <nav class="nav flex-column px-1 mt-1">
      <a class="nav-link" href="../admin/principalDash.php">
        <i class="bi bi-speedometer2"></i><span>Dashboard</span>
      </a>
      <a class="nav-link" href="../admin/teacher.php">
        <i class="bi bi-person-badge"></i><span>Teacher</span>
      </a>
      <a class="nav-link" href="../admin/select_sec.php">
        <i class="bi bi-folder2-open"></i><span>Grades</span>
      </a>
      <a class="nav-link" href="../admin/achievement.php">
        <i class="bi bi-award"></i><span>Top Students</span>
      </a>
      <a class="nav-link" href="../admin/calendar.php">
        <i class="bi bi-calendar3"></i><span>Calendar</span>
      </a>
      <a class="nav-link" href="../admin/view_logs.php">
        <i class="bi bi-list-check"></i><span>Logs</span>
      </a>
    </nav>
  </div>
  <div class="sidebar-footer"></div>
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
    document.documentElement.classList.remove('nav-preopen-admin');
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

  function openSidebar()   { isOpen = true;  localStorage.setItem('sidebarOpen_principal', 'true');  applyState(true); }
  function closeSidebar()  { isOpen = false; localStorage.setItem('sidebarOpen_principal', 'false'); applyState(false); }
  function toggleSidebar() { isOpen ? closeSidebar() : openSidebar(); }

  toggleBtn.addEventListener('click', toggleSidebar);
  closeBtn.addEventListener('click',  closeSidebar);
  overlay.addEventListener('click',   closeSidebar);
  window.addEventListener('resize', function() { applyState(isOpen); });

  // Active link highlighting
  const links = document.querySelectorAll('.admin-sidebar .nav-link');
  const currentPath = window.location.pathname.replace(/\\/g, '/');
  links.forEach(function(link) {
    const href = link.getAttribute('href');
    if (href && currentPath.endsWith(href.replace(/^\.\./, '')))
      link.classList.add('active');
  });

  // Profile dropdown
  let profileTimeout;
  profileDrop.addEventListener('mouseenter', function() { clearTimeout(profileTimeout); profileDrop.classList.add('active'); });
  profileDrop.addEventListener('mouseleave', function() { profileTimeout = setTimeout(function() { profileDrop.classList.remove('active'); }, 150); });
});
</script>