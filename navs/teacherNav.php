<?php

// Get teacher profile picture if logged in
$profilePicturePath = '../img/default.jpg'; // Default image

if (isset($_SESSION['email']) && $_SESSION['role'] === 'teacher') {
    $teacherEmail = $_SESSION['email'];
    
    // Get UserID first
    $stmt = $conn->prepare("
        SELECT u.UserID 
        FROM user u 
        JOIN teacher t ON u.UserID = t.UserID 
        WHERE u.Email = ?
    ");
    $stmt->bind_param("s", $teacherEmail);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        $userID = $user['UserID'];
        
        // Get profile picture
        $picStmt = $conn->prepare("SELECT path FROM profile_picture WHERE user_id = ? ORDER BY uploaded_at DESC LIMIT 1");
        $picStmt->bind_param("i", $userID);
        $picStmt->execute();
        $picResult = $picStmt->get_result();
        
        if ($picResult->num_rows > 0) {
            $profilePicture = $picResult->fetch_assoc();
            $profilePicturePath = $profilePicture['path'];
        }
        
        $picStmt->close();
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Balaytigue National High School</title>
  <link rel="icon" type="image/png" href="../img/logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@700&display=swap" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Inter&display=swap" rel="stylesheet" />
  <style>
  :root {
    --sidebar-width: 180px;
    --sidebar-collapsed-width: 70px;
    --mobile-breakpoint: 768px;
    --bg-sidebar: #fcfcfc;
    --bg-sidebar-hover: #5a6268;
    --accent: #ffffff;
  }

  .header {
    height: 70px;
    background: rgb(143, 139, 139);
    display: flex;
    align-items: center;
    position: relative;
    z-index: 1001;
  }

  .profile-circle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
    display: block;
    cursor: pointer;
    position: relative;
    transition: transform 0.2s ease;
  }

  .profile-circle:hover {
    transform: scale(1.05);
  }

  .profile-dropdown {
    position: relative;
    display: inline-block;
    height: 100%;
    display: flex;
    align-items: center;
  }

  .profile-dropdown-content {
    position: absolute;
    background-color: white;
    min-width: 160px;
    box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
    z-index: 1100;
    border-radius: 8px;
    right: 0;
    top: 100%;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.3s ease, visibility 0.3s ease;
    pointer-events: none;
  }

  .profile-dropdown-content a {
    color: black;
    padding: 12px 16px;
    text-decoration: none;
    display: block;
    text-align: left;
    font-size: 14px;
  }

  .profile-dropdown-content a:hover {
    background-color: #e0f7fa;
    color: #007b8a;
  }

  .profile-dropdown-content hr {
    margin: 5px 0;
  }

  .profile-dropdown:hover .profile-dropdown-content {
    opacity: 1;
    visibility: visible;
    pointer-events: auto;
  }

  .profile-dropdown::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 0;
    width: 100%;
    height: 10px;
    background: transparent;
  }

  /* Sidebar styles */
  .admin-sidebar {
    width: var(--sidebar-width);
    height: 100vh;
    background: var(--bg-sidebar);
    position: fixed;
    left: 0;
    top: 0;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    transition: width 0.3s ease, transform 0.3s ease;
    overflow-x: hidden;
    white-space: nowrap;
    box-shadow: 2px 0 10px rgba(0,0,0,0.1);
  }

  /* Collapsed state (desktop) */
  .admin-sidebar.collapsed {
    width: var(--sidebar-collapsed-width);
  }

  .admin-sidebar.collapsed .school-name,
  .admin-sidebar.collapsed .sidebar-header div:last-child div:last-child,
  .admin-sidebar.collapsed .nav-link span,
  .admin-sidebar.collapsed .sidebar-footer {
    display: none;
  }

  .admin-sidebar.collapsed .nav-link {
    justify-content: center;
    padding: 10px 0;
  }

  .admin-sidebar.collapsed .nav-link i {
    margin-right: 0;
    font-size: 24px;
  }

  .admin-sidebar.collapsed .logo {
    width: 50px;
    margin: 20px auto;
  }

  .admin-sidebar .logo {
    width: 70px;
    margin: 24px auto 8px auto;
    display: block;
    cursor: pointer;
    transition: transform 0.3s ease;
    border-radius: 50%;
  }

  .admin-sidebar .school-name {
    font-weight: bold;
    font-size: 20px;
    margin-top: 8px;
    color: rgb(0, 0, 0);
    font-family: 'Merriweather', serif;
    text-align: center;
  }

  .admin-sidebar .nav-link {
    color: #222;
    text-decoration: none;
    font-size: 17px;
    display: flex;
    align-items: center;
    margin-bottom: 5px;
    padding: 10px 15px;
    transition: all 0.3s ease;
    position: relative;
  }

  .admin-sidebar .nav-link:hover,
  .admin-sidebar .nav-link.active {
    background: var(--bg-sidebar-hover);
    color: var(--accent);
    border-radius: 8px;
  }

  .admin-sidebar .nav-link i {
    font-size: 20px;
    margin-right: 12px;
    min-width: 24px;
  }

  /* Tooltip for collapsed mode */
  .admin-sidebar.collapsed .nav-link:hover::after {
    content: attr(data-label);
    position: absolute;
    left: 100%;
    top: 50%;
    transform: translateY(-50%);
    background: var(--accent);
    color: white;
    padding: 5px 10px;
    border-radius: 4px;
    font-size: 13px;
    white-space: nowrap;
    z-index: 1100;
    margin-left: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.2);
  }

  .admin-sidebar.collapsed .nav-link:hover::before {
    content: '';
    position: absolute;
    left: 100%;
    top: 50%;
    transform: translateY(-50%);
    border-width: 5px 5px 5px 0;
    border-style: solid;
    border-color: transparent var(--accent) transparent transparent;
    margin-left: 0;
    z-index: 1100;
  }

  body {
    background: rgb(236, 240, 243);
    margin-left: var(--sidebar-width);
    transition: margin-left 0.3s ease;
  }

  body.sidebar-collapsed {
    margin-left: var(--sidebar-collapsed-width);
  }

  /* Sidebar layout */
  .sidebar-header {
    flex-shrink: 0;
    padding: 10px 0;
  }

  .sidebar-content {
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
    padding: 10px 0;
  }

  .sidebar-footer {
    flex-shrink: 0;
    padding: 10px 0;
    border-top: 1px solid #a0d8ef;
    background: var(--bg-sidebar);
  }

  /* Custom scrollbar for sidebar */
  .sidebar-content::-webkit-scrollbar {
    width: 6px;
  }

  .sidebar-content::-webkit-scrollbar-track {
    background: #a0d8ef;
    border-radius: 10px;
  }

  .sidebar-content::-webkit-scrollbar-thumb {
    background: var(--accent);
    border-radius: 10px;
  }

  .sidebar-content::-webkit-scrollbar-thumb:hover {
    background: #ffffff;
  }

  .sidebar-content {
    scrollbar-width: thin;
    scrollbar-color: var(--accent) #ffffff;
  }

  /* Toggle button */
  .sidebar-toggle {
    background: none;
    border: none;
    font-size: 24px;
    color: #333;
    cursor: pointer;
    margin-right: 15px;
    display: block;
    transition: transform 0.2s;
  }

  .sidebar-toggle:hover {
    transform: scale(1.1);
  }

  /* Overlay for mobile */
  .sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
    z-index: 999;
  }

  /* Mobile responsive styles */
  @media (max-width: 768px) {
    :root {
      --sidebar-width: 250px;
    }
    
    body {
      margin-left: 0 !important;
    }
    
    body.sidebar-collapsed {
      margin-left: 0;
    }
    
    .admin-sidebar {
      transform: translateX(-100%);
      top: 70px;
      height: calc(100% - 70px);
      width: var(--sidebar-width);
    }
    
    .admin-sidebar.mobile-open {
      transform: translateX(0);
    }
    
    /* Override collapsed styles on mobile */
    .admin-sidebar.collapsed {
      width: var(--sidebar-width);
    }
    
    .admin-sidebar.collapsed .school-name,
    .admin-sidebar.collapsed .sidebar-header div:last-child div:last-child,
    .admin-sidebar.collapsed .nav-link span,
    .admin-sidebar.collapsed .sidebar-footer {
      display: block;
    }
    
    .admin-sidebar.collapsed .nav-link {
      justify-content: flex-start;
      padding: 10px 15px;
    }
    
    .admin-sidebar.collapsed .nav-link i {
      margin-right: 12px;
      font-size: 20px;
    }
    
    .admin-sidebar.collapsed .logo {
      width: 70px;
      margin: 24px auto 8px auto;
    }
    
    /* Hide tooltips on mobile */
    .admin-sidebar.collapsed .nav-link:hover::after,
    .admin-sidebar.collapsed .nav-link:hover::before {
      display: none;
    }
    
    .sidebar-toggle {
      display: block;
    }
    
    .sidebar-overlay.active {
      display: block;
    }
    
    .header {
      padding-left: 15px;
      padding-right: 15px;
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 1001;
    }
    
    .profile-dropdown-content {
      right: 10px;
      top: 100%;
    }
    
    body {
      padding-top: 70px;
    }
  }
  </style>
</head>
<body>

<nav class="header navbar navbar-expand navbar-light bg-light px-4">
  <!-- Toggle button with dynamic icon -->
  <button class="sidebar-toggle" id="sidebarToggle">
    <i class="bi bi-list" id="toggleIcon"></i>
  </button>
  <div class="ms-auto">
    <div class="profile-dropdown" id="profileDropdown">
      <img src="<?php echo htmlspecialchars($profilePicturePath); ?>" alt="Profile Picture" class="profile-circle border border-secondary">
      <div class="profile-dropdown-content">
        <a href="profile.php"><i class="bi bi-person" style="margin-right: 8px;"></i> Profile</a>
        <hr>
        <a href="../logout.php" class="text-danger"><i class="bi bi-box-arrow-right" style="margin-right: 8px;"></i> Logout</a>
      </div>
    </div>
  </div>
</nav>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="admin-sidebar" id="adminSidebar">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <div>
            <img src="../img/logo.png" alt="BANAHIS Logo" class="logo">
        </div>
        <div>
            <div class="school-name">SMARTCARD</div>
        </div>
    </div>

    <!-- Scrollable Sidebar Content -->
    <div class="sidebar-content">
        <nav class="nav flex-column">
            <a class="nav-link" href="../teacher/tdashboard.php" data-label="Dashboard">
                <i class="bi bi-speedometer2"></i><span> Dashboard</span>
            </a>
            <a class="nav-link" href="../teacher/list.php" data-label="Student list">
                <i class="bi bi-clipboard"></i><span> Student list</span>
            </a>
            <a class="nav-link" href="../teacher/select_class.php" data-label="Attendance">
                <i class="bi bi-calendar-check"></i><span> Attendance</span>
            </a>
            <a class="nav-link" href="../teacher/grading_sheet.php" data-label="Grades">
                <i class="bi bi-book"></i><span> Grades</span>
            </a>
            <a class="nav-link" href="../teacher/values.php" data-label="Values">
                <i class="bi bi-card-list"></i><span> Values</span>
            </a>
            <a class="nav-link" href="../teacher/record.php" data-label="Card">
                <i class="bi bi-folder"></i><span> Card</span>
            </a>
            <a class="nav-link" href="../teacher/achievement.php" data-label="Achievements">
                <i class="bi bi-award"></i><span> Achievements</span>
            </a>
            <a class="nav-link" href="../teacher/announcement.php" data-label="Announcements">
                <i class="bi bi-bell"></i><span><small> Announcements</small></span>
            </a>
        </nav>
    </div>

    <!-- Sidebar Footer (hidden when collapsed) -->
    <div class="sidebar-footer">
        <div class="text-center">
            <!-- Optional footer content -->
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('adminSidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const toggleIcon = document.getElementById('toggleIcon');
    const overlay = document.getElementById('sidebarOverlay');
    const body = document.body;

    // Ensure sidebar starts collapsed on desktop
    if (window.innerWidth > 768) {
        sidebar.classList.add('collapsed');
        body.classList.add('sidebar-collapsed');
    }

    // Active link highlighting
    const links = document.querySelectorAll('.admin-sidebar .nav-link');
    const currentUrl = window.location.pathname.replace(/\\/g, '/');
    links.forEach(link => {
        const linkPath = link.pathname.replace(/\\/g, '/');
        link.classList.toggle('active', currentUrl.endsWith(linkPath));
    });

    // Function to check if we are on mobile
    function isMobile() {
        return window.innerWidth <= 768;
    }

    // Update toggle icon based on sidebar state and screen size
    function updateToggleIcon() {
        if (isMobile()) {
            toggleIcon.className = 'bi bi-list';
        } else {
            // Desktop: if collapsed, show right arrow (to expand), else show left arrow (to collapse)
            if (sidebar.classList.contains('collapsed')) {
                toggleIcon.className = 'bi bi-list';
            } else {
                toggleIcon.className = 'bi bi-x';
            }
        }
    }

    // Toggle sidebar based on screen size
    function toggleSidebar() {
        if (isMobile()) {
            // Mobile: slide sidebar in/out
            sidebar.classList.toggle('mobile-open');
            overlay.classList.toggle('active');
            // On mobile, icon stays as list
            toggleIcon.className = 'bi bi-list';
        } else {
            // Desktop: collapse/expand
            sidebar.classList.toggle('collapsed');
            body.classList.toggle('sidebar-collapsed');
            updateToggleIcon();
        }
    }

    // Event listener for toggle button
    toggleBtn.addEventListener('click', toggleSidebar);

    // Close sidebar when clicking overlay (mobile)
    overlay.addEventListener('click', function() {
        sidebar.classList.remove('mobile-open');
        overlay.classList.remove('active');
    });

    // Close sidebar on link click (mobile only)
    if (isMobile()) {
        links.forEach(link => {
            link.addEventListener('click', function() {
                sidebar.classList.remove('mobile-open');
                overlay.classList.remove('active');
            });
        });
    }

    // Handle window resize
    window.addEventListener('resize', function() {
        if (isMobile()) {
            // On mobile, remove collapsed class and adjust body margin
            sidebar.classList.remove('collapsed');
            body.classList.remove('sidebar-collapsed');
            // Close mobile menu if open (optional, but keeps UI clean)
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
            toggleIcon.className = 'bi bi-list';
        } else {
            // On desktop, remove mobile-open class if present
            sidebar.classList.remove('mobile-open');
            overlay.classList.remove('active');
            // Ensure icon matches current collapsed state
            updateToggleIcon();
        }
    });

    // Initial icon update
    updateToggleIcon();

    // Profile dropdown hover behavior
    const profileDropdown = document.getElementById('profileDropdown');
    profileDropdown.addEventListener('mouseenter', function() {
        this.classList.add('active');
    });
    profileDropdown.addEventListener('mouseleave', function() {
        this.classList.remove('active');
    });
});
</script>

</body>
</html>