<?php
  if (session_status() === PHP_SESSION_NONE) session_start();

  $profilePicturePath = '../img/default.jpg';
  $t_db_full_name     = '';
  $isAdviser          = false;

  if (isset($_SESSION['email']) && $_SESSION['role'] === 'teacher') {
      $teacherEmail = $_SESSION['email'];
      $stmt = $conn->prepare("SELECT u.UserID, CONCAT(t.fName, ' ', t.lName) AS FullName FROM user u JOIN teacher t ON u.UserID = t.UserID WHERE u.Email = ?");
      $stmt->bind_param("s", $teacherEmail);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($result->num_rows > 0) {
          $user           = $result->fetch_assoc();
          $userID         = $user['UserID'];
          $t_db_full_name = $user['FullName'];

          $picStmt = $conn->prepare("SELECT path FROM profile_picture WHERE user_id = ? ORDER BY uploaded_at DESC LIMIT 1");
          $picStmt->bind_param("i", $userID);
          $picStmt->execute();
          $picResult = $picStmt->get_result();
          if ($picResult->num_rows > 0) $profilePicturePath = $picResult->fetch_assoc()['path'];
          $picStmt->close();

          $adviserStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM section WHERE AdviserID = ?");
          $adviserStmt->bind_param("i", $userID);
          $adviserStmt->execute();
          $adviserResult = $adviserStmt->get_result();
          if ($row = $adviserResult->fetch_assoc()) $isAdviser = ($row['cnt'] > 0);
          $adviserStmt->close();
      }
      $stmt->close();
  }
?>
<!-- ── Teacher Nav: Styles ── -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@700&display=swap" rel="stylesheet"/>

<style>
  :root {
    --sidebar-width: 240px;
    --header-height: 60px;
    --header-bg: #1a1f2e;
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
    margin: 0;
    padding: 0;
    background: #F5F0E8;
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
    top: 0;
    left: 0;
    width: 100%;
    z-index: 1002;
    transition: left var(--transition-speed) ease, width var(--transition-speed) ease;
  }

  /* When sidebar open on desktop: shift header right */
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
    width: 38px;
    height: 38px;
    border-radius: 50%;
    object-fit: cover;
  }

  #headerTitle {
    font-family: 'Merriweather', serif;
    font-size: 16px;
    font-weight: 700;
    color: #ffffff;
    white-space: nowrap;
    display: none;
  }

  .sidebar-toggle {
    width: 40px;
    height: 36px;
    border: none;
    background: transparent;
    color: rgba(255,255,255,0.6);
    font-size: 22px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: color 0.18s;
    flex-shrink: 0;
  }
  .sidebar-toggle:hover {
    color: #ffffff;
  }

  /* Hide logo & toggle in header when sidebar open on desktop */
  .app-header.sidebar-open #headerLogo,
  .app-header.sidebar-open #sidebarToggle {
    display: none !important;
  }

  /* ── Header Date & Time ── */
  .header-right {
    display: flex;
    align-items: center;
    gap: 20px;
    color: rgba(255,255,255,0.85);
    font-size: 14px;
    font-weight: 500;
    letter-spacing: 0.3px;
  }
  .header-date, .header-time {
    display: flex;
    align-items: center;
    gap: 6px;
  }
  .header-right i {
    color: rgba(255,255,255,0.5);
    font-size: 15px;
  }
  @media (max-width: 768px) {
    .header-right {
      display: none; /* Hide on mobile to save space */
    }
  }

  /* ── Profile ── */
  .profile-dropdown {
    position: relative;
    display: flex;
    align-items: center;
  }

  .profile-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    cursor: pointer;
    border: 2px solid rgba(0,0,0,0.15);
    transition: border-color 0.2s;
  }
  .profile-circle:hover { border-color: #555; }

  .profile-dropdown-content {
    position: absolute;
    right: 0;
    top: calc(100% + 8px);
    background: #fff;
    border-radius: 10px;
    min-width: 160px;
    box-shadow: 0 8px 24px rgba(0,0,0,0.14);
    z-index: 1300;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.2s, visibility 0.2s;
    pointer-events: none;
  }
  .profile-dropdown.active .profile-dropdown-content {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
  }
  .profile-dropdown-content a {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 11px 16px;
    color: #222;
    text-decoration: none;
    font-size: 14px;
  }
  .profile-dropdown-content a:hover { background: #e9ecef; color: #222; }
  .profile-dropdown-content hr { margin: 4px 0; border-color: #eee; }

  /* ── Mobile overlay ── */
  .sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,0.38);
    z-index: 1001;
  }
  .sidebar-overlay.active { display: block; }

  /* ── Sidebar footer user card ── */
  .sidebar-footer {
    padding: 10px 12px 14px;
    flex-shrink: 0;
    position: relative;
  }
  .footer-user-btn {
    display: flex; align-items: center; gap: 10px;
    padding: 8px 10px; border-radius: 10px;
    cursor: pointer; transition: background 0.18s;
    width: 100%; border: none; background: transparent;
  }
  .footer-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    object-fit: cover;
    flex-shrink: 0; background: #3a8e6e;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; font-weight: 700; color: #fff;
    overflow: hidden; position: relative;
  }
  .footer-avatar img {
    width: 100%; height: 100%;
    object-fit: cover; border-radius: 50%;
    position: absolute; inset: 0;
  }
  .footer-user-info { flex: 1; min-width: 0; text-align: left; }
  .footer-user-name {
    font-size: 12.5px; font-weight: 600;
    color: rgba(255,255,255,0.88);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; line-height: 1.2;
  }
  .footer-user-role { font-size: 10.5px; color: var(--sidebar-muted); margin-top: 1px; }
  .footer-caret { font-size: 10px; color: var(--sidebar-muted); transition: transform 0.2s; flex-shrink: 0; }
  .sidebar-footer.open .footer-caret { transform: rotate(180deg); }

  /* Popup menu pops upward */
  .footer-popup {
    display: none;
    position: absolute;
    bottom: calc(100% + 6px);
    left: 12px; right: 12px;
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 -6px 24px rgba(0,0,0,0.22);
    overflow: hidden;
    animation: popUp 0.18s ease;
    z-index: 10;
  }
  .sidebar-footer.open .footer-popup { display: block; }
  @keyframes popUp {
    from { opacity: 0; transform: translateY(6px); }
    to   { opacity: 1; transform: translateY(0); }
  }
  .footer-popup a {
    display: flex; align-items: center; gap: 10px;
    padding: 11px 16px; color: #222;
    text-decoration: none; font-size: 13.5px;
    border-bottom: 1px solid #f0f0f0; transition: background 0.15s;
  }
  .footer-popup a:last-child { border-bottom: none; }
  .footer-popup a:hover { background: #f5f5f5; }
  .footer-popup a.danger { color: #c0392b; }
  .footer-popup a.danger:hover { background: #fff5f5; }
  .footer-popup a i { font-size: 15px; width: 18px; }

  /* ── Sidebar ── */
  .admin-sidebar {
    width: var(--sidebar-width);
    height: 100vh;
    background: var(--sidebar-bg);
    position: fixed;
    left: 0; top: 0;
    z-index: 1003;
    display: flex;
    flex-direction: column;
    box-shadow: 4px 0 24px rgba(0,0,0,0.28);
    transform: translateX(-100%);
    transition: transform var(--transition-speed) ease;
    overflow: hidden;
  }
  .admin-sidebar.open { transform: translateX(0); }

  /* ── Sidebar topbar ── */
  .sidebar-topbar {
    height: var(--header-height);
    background: rgba(0,0,0,0.18);
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 14px;
    flex-shrink: 0;
    border-bottom: 1px solid rgba(255,255,255,0.07);
  }

  .sidebar-topbar img {
    width: 30px; height: 30px;
    border-radius: 50%; object-fit: cover;
    opacity: 0.85;
  }

  .sidebar-topbar .sidebar-title {
    font-family: 'Merriweather', serif;
    font-size: 14px; font-weight: 700;
    color: rgba(255,255,255,0.80);
    flex: 1; white-space: nowrap;
    letter-spacing: 0.5px;
  }

  .sidebar-close-btn {
    width: 30px; height: 30px;
    background: rgba(255,255,255,0.07);
    border: none; border-radius: 50%;
    color: rgba(255,255,255,0.55);
    font-size: 15px;
    display: flex; align-items: center; justify-content: center;
    cursor: pointer; transition: background 0.18s; flex-shrink: 0;
  }
  .sidebar-close-btn:hover { background: rgba(255,255,255,0.14); color: #fff; }

  /* ── Portal label + user block ── */
  .sidebar-portal-label {
    padding: 20px 20px 10px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .sidebar-portal-label i {
    font-size: 11px;
    color: var(--sidebar-muted);
  }
  .sidebar-portal-label span {
    font-size: 10px; font-weight: 800;
    letter-spacing: 2px; text-transform: uppercase;
    color: var(--sidebar-muted);
  }
  .portal-label-rule {
    flex: 1;
    height: 1px;
    background: rgba(255,255,255,0.09);
    margin-left: 6px;
  }

  .sidebar-user-block {
    padding: 5px 20px 20px;
    flex-shrink: 0;
    cursor: default;
  }
  .sidebar-avatar-circle {
    width: 48px; height: 48px;
    border-radius: 50%;
    background: #3a8e6e;
    display: flex; align-items: center; justify-content: center;
    font-size: 17px; font-weight: 700;
    color: #fff; letter-spacing: 0.5px;
    margin-bottom: 10px;
    box-shadow: 0 3px 12px rgba(0,0,0,0.3);
    border: 2px solid rgba(255,255,255,0.13);
    transition: transform 0.2s;
    overflow: hidden; position: relative;
  }

  /* Nav links */
  .sidebar-content {
    flex: 1;
    overflow-y: auto;
    padding: 8px 0;
  }
  .sidebar-content::-webkit-scrollbar { width: 4px; }
  .sidebar-content::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }

  .admin-sidebar .nav-link {
    display: flex; align-items: center; gap: 10px;
    padding: 9px 14px; margin: 2px 8px;
    border-radius: 8px;
    color: var(--sidebar-text);
    text-decoration: none; font-size: 13.5px;
    transition: background 0.18s, color 0.18s;
  }
  .admin-sidebar .nav-link i { font-size: 16px; min-width: 20px; opacity: 0.75; }
  .admin-sidebar .nav-link:hover {
    background: var(--sidebar-hover); color: #fff;
  }
  .admin-sidebar .nav-link:hover i { opacity: 1; }
  .admin-sidebar .nav-link.active {
    background: var(--sidebar-active); color: #fff; font-weight: 600;
  }
  .admin-sidebar .nav-link.active i { opacity: 1; }



  /* ── Page content wrapper ── */
  /* This is what actually shifts when sidebar opens/closes */
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

<!-- Inline script to set initial state BEFORE paint to avoid flicker -->
<script>
  (function() {
    var open = localStorage.getItem('sidebarOpen') === 'true';
    window.__sidebarInitOpen = open;
    // If sidebar was open on desktop, pre-apply styles via a class on <html>
    // to avoid the content "jump" on load
    if (open && window.innerWidth > 768) {
      document.documentElement.classList.add('nav-preopen');
    }
  })();
</script>
<style>
  /* Applied before DOMContentLoaded to prevent layout jump */
  html.nav-preopen .page-content {
    margin-left: var(--sidebar-width) !important;
  }
  html.nav-preopen .app-header {
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
  <div class="header-right">
    <div class="header-date">
      <i class="bi bi-calendar3"></i> <span id="headerDateDisplay"></span>
    </div>
    <div class="header-time">
      <i class="bi bi-clock"></i> <span id="headerTimeDisplay"></span>
    </div>
  </div>
</nav>

<!-- ── Overlay (mobile only) ── -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ── Sidebar ── -->
<?php
  // Prefer DB full name, then session name, then email
  $t_full_name = !empty($t_db_full_name) ? $t_db_full_name
              : (isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'Teacher');
  $t_parts    = explode(' ', trim($t_full_name));
  $t_initials = count($t_parts) >= 2
              ? strtoupper(substr($t_parts[0],0,1).substr(end($t_parts),0,1))
              : strtoupper(substr($t_full_name,0,2));
  $t_has_pic  = ($profilePicturePath !== '../img/default.jpg') && file_exists($profilePicturePath);
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
    <i class="bi bi-person-workspace"></i>
    <span>Teacher Portal</span>
    <div class="portal-label-rule"></div>
  </div>



  <div class="sidebar-content">
    <nav class="nav flex-column">
      <a class="nav-link" href="../teacher/tdashboard.php">
        <i class="bi bi-speedometer2"></i><span>Dashboard</span>
      </a>
      <a class="nav-link" href="../teacher/list.php">
        <i class="bi bi-clipboard"></i><span>Student List</span>
      </a>
      <a class="nav-link" href="../teacher/select_class.php">
        <i class="bi bi-calendar-check"></i><span>Attendance</span>
      </a>
      <a class="nav-link" href="../teacher/grading_sheet.php">
        <i class="bi bi-book"></i><span>Grades</span>
      </a>
      <?php if ($isAdviser): ?>
      <a class="nav-link" href="../teacher/values.php">
        <i class="bi bi-card-list"></i><span>Values</span>
      </a>
      <a class="nav-link" href="../teacher/record.php">
        <i class="bi bi-folder"></i><span>Card</span>
      </a>
      <a class="nav-link" href="../teacher/achievement.php">
        <i class="bi bi-award"></i><span>Achievements</span>
      </a>
      <a class="nav-link" href="../teacher/announcement.php">
        <i class="bi bi-bell"></i><span>Announcements</span>
      </a>
      <?php endif; ?>
    </nav>
  </div>

  <div class="sidebar-footer" id="sidebarFooter">
    <!-- Popup menu (above) -->
    <div class="footer-popup" id="footerPopup">
      <a href="profile.php"><i class="bi bi-person-circle"></i> Profile</a>
      <a href="../logout.php" class="danger"><i class="bi bi-box-arrow-right"></i> Sign Out</a>
    </div>
    <!-- Clickable user card -->
    <button class="footer-user-btn" id="footerUserBtn" type="button">
      <div class="footer-avatar">
        <?php if ($t_has_pic): ?>
          <img src="<?php echo htmlspecialchars($profilePicturePath); ?>" alt="Profile" onerror="this.style.display='none';">
        <?php else: ?>
          <?php echo $t_initials; ?>
        <?php endif; ?>
      </div>
      <div class="footer-user-info">
        <div class="footer-user-name"><?php echo $t_full_name; ?></div>
        <div class="footer-user-role">
          <?php
            if ($isAdviser && isset($_SESSION['section_name'])) {
              echo 'Adviser &middot; '.htmlspecialchars($_SESSION['section_name']);
            } elseif ($isAdviser) {
              echo 'Adviser';
            } else {
              echo 'Teacher';
            }
          ?>
        </div>
      </div>
      <i class="bi bi-chevron-up footer-caret"></i>
    </button>
  </div>
</div>

<!-- ── Page content wrapper opens here (closed in each page before </body>) ── -->
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
  const footerWrap  = document.getElementById('sidebarFooter');
  const footerBtn   = document.getElementById('footerUserBtn');

  var isOpen = window.__sidebarInitOpen || false;

  function isMobile() { return window.innerWidth <= 768; }

  function applyState(open) {
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
    document.documentElement.classList.remove('nav-preopen');
  }

  applyState(isOpen);

  function openSidebar()   { isOpen = true;  localStorage.setItem('sidebarOpen', 'true');  applyState(true); }
  function closeSidebar()  { isOpen = false; localStorage.setItem('sidebarOpen', 'false'); applyState(false); }
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

  // Footer user popup toggle
  if (footerBtn) footerBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    footerWrap.classList.toggle('open');
  });
  document.addEventListener('click', function(e) {
    if (footerWrap && !footerWrap.contains(e.target)) footerWrap.classList.remove('open');
  });

  // Live Date & Time
  function updateHeaderClock() {
    const now = new Date();
    const dateOptions = { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' };
    const timeOptions = { hour: 'numeric', minute: '2-digit', second: '2-digit', hour12: true };
    
    const dateEl = document.getElementById('headerDateDisplay');
    const timeEl = document.getElementById('headerTimeDisplay');
    
    if(dateEl) dateEl.textContent = now.toLocaleDateString('en-US', dateOptions);
    if(timeEl) timeEl.textContent = now.toLocaleTimeString('en-US', timeOptions);
  }
  updateHeaderClock();
  setInterval(updateHeaderClock, 1000);
});
</script>