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
  --mobile-breakpoint: 768px;
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

.profile-tooltip {
    visibility: hidden;
    width: 180px;
    background-color: #007b8a;
    color: white;
    text-align: center;
    border-radius: 6px;
    padding: 8px;
    position: absolute;
    z-index: 1102;
    bottom: -45px;
    right: 0;
    font-size: 13px;
    font-weight: 500;
    white-space: nowrap;
    opacity: 0;
    transition: opacity 0.3s ease;
    box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.2);
}

.profile-tooltip::after {
    content: "";
    position: absolute;
    top: -5px;
    right: 10px;
    border-width: 0 5px 5px 5px;
    border-style: solid;
    border-color: transparent transparent #007b8a transparent;
}

.profile-dropdown:hover .profile-tooltip {
    visibility: visible;
    opacity: 1;
}
.dropdown-item:hover {
    background: rgb(232, 234, 235);
    color: #007b8a;
}
.admin-sidebar {
    width: var(--sidebar-width);
    height: 100vh;
    background: #b3e6f7;
    position: fixed;
    left: 0;
    top: 0;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    transition: transform 0.3s ease;
}
.admin-sidebar .logo {
    width: 70px;
    margin: 24px auto 8px auto;
    display: block;
    cursor: pointer;
    transition: transform 0.3s ease;
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
    display: block;
    margin-bottom: 5px;
    padding: 10px 15px;
    transition: all 0.3s ease;
}
.admin-sidebar .nav-link:hover,
.admin-sidebar .nav-link.active {
    background: #e0f7fa;
    color: #007b8a;
    border-radius: 8px;
}
body {
    background: rgb(236, 240, 243);
    margin-left: var(--sidebar-width) !important;
    transition: margin-left 0.3s ease;
}
a[style*="text-decoration:none"]:hover, a[style*="text-decoration:none"].active {
    background: #e0f7fa !important;
    color: #007b8a !important;
    border-radius: 8px;
}
.logo{
    border-radius: 50%;
    width: 70px;
}

/* Profile dropdown styles - FIXED */
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
    top: 100%; /* Changed from 70px to 100% */
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

/* Bridge element to prevent gap between profile and dropdown */
.profile-dropdown::after {
    content: '';
    position: absolute;
    top: 100%;
    left: 0;
    width: 100%;
    height: 10px; /* Creates a bridge between profile and dropdown */
    background: transparent;
}

/* Sidebar layout */
.sidebar-header {
    flex-shrink: 0;
    padding: 10px 0;
}

.sidebar-content {
    flex: 1;
    overflow-y: auto;
    padding: 10px 0;
}

.sidebar-footer {
    flex-shrink: 0;
    padding: 10px 0;
    border-top: 1px solid #a0d8ef;
    background: #b3e6f7;
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
    background: #007b8a;
    border-radius: 10px;
}

.sidebar-content::-webkit-scrollbar-thumb:hover {
    background: #005f6b;
}

/* Firefox scrollbar */
.sidebar-content {
    scrollbar-width: thin;
    scrollbar-color: #007b8a #a0d8ef;
}

/* Rotate caret icon when expanded */
.nav-link[aria-expanded="true"] .bi-caret-down-fill {
    transform: rotate(180deg);
}
.bi-caret-down-fill {
    transition: transform 0.3s ease;
}

/* Ensure sidebar takes full height */
html, body {
    height: 100%;
    margin: 0;
    padding: 0;
}

/* Logout link styling */
.logout-link {
    color: #000000ff !important;
    font-weight: 500;
}
.logout-link:hover {
    color: #615b5cff !important;
}

/* Mobile toggle button */
.mobile-toggle {
    display: none;
    background: none;
    border: none;
    font-size: 24px;
    color: #333;
    cursor: pointer;
    margin-right: 15px;
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
    
    .admin-sidebar {
        transform: translateX(-100%);
        /* Add 70px margin-top for mobile */
        top: 70px;
        height: calc(100% - 70px);
    }
    
    .admin-sidebar.mobile-open {
        transform: translateX(0);
    }
    
    .mobile-toggle {
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
    
    /* Adjust the main content to account for fixed header */
    body {
        padding-top: 70px;
    }
}
  </style>
</head>
<body>

<nav class="header navbar navbar-expand navbar-light bg-light px-4">
  <button class="mobile-toggle" id="sidebarToggle">
    <i class="bi bi-list"></i>
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
            <div style="font-size:13px;color:#555;text-align:center; padding: 0 10px;">Student Academic Performance Management System</div>
        </div>
    </div>

    <!-- Scrollable Sidebar Content -->
    <div class="sidebar-content">
        <nav class="nav flex-column">
            <a class="nav-link" href="../teacher/tdashboard.php" style="text-decoration:none; font-size: 14px;">
                <i class="bi bi-speedometer2" style="font-size:20px;margin-right:12px;"></i> Dashboard
            </a>
            <a class="nav-link" href="../teacher/list.php" style="font-size: 14px; text-decoration:none;">
                <i class="bi bi-clipboard" style="font-size:20px;margin-right:12px;"></i> Student list
            </a>
            <a class="nav-link" href="../teacher/select_class.php" style="font-size: 14px; text-decoration:none;">
                <i class="bi bi-calendar-check" style="font-size:20px;margin-right:12px;"></i> Attendance
            </a>
            <a class="nav-link" href="../teacher/grading_sheet.php" style="font-size: 14px; text-decoration:none;">
                <i class="bi bi-book" style="font-size:20px;margin-right:12px;"></i> Grades
            </a>
            <a class="nav-link" href="../teacher/achievement.php" style="font-size: 14px; text-decoration:none;">
                <i class="bi bi-award" style="font-size:20px;margin-right:12px;"></i> Achievements
            </a>
            <a class="nav-link" href="../teacher/record.php" style="font-size: 14px; text-decoration:none;">
                <i class="bi bi-folder" style="font-size:20px;margin-right:12px;"></i> Card
            </a>
            <a class="nav-link" href="../teacher/announcement.php" style="font-size: 14px; text-decoration:none;">
                <i class="bi bi-bell" style="font-size:20px;margin-right:12px;"></i> Announcements
            </a>
        </nav>
    </div>

    <!-- Sidebar Footer with Logout -->
    <div class="sidebar-footer">
        <div class="text-center">
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Active link highlighting
    const links = document.querySelectorAll('.admin-sidebar .nav-link');
    const currentUrl = window.location.pathname.replace(/\\/g, '/');
    links.forEach(link => {
        const linkPath = link.pathname.replace(/\\/g, '/');
        link.classList.toggle('active', currentUrl.endsWith(linkPath));
    });
    
    // Mobile sidebar toggle functionality
    const sidebarToggle = document.getElementById('sidebarToggle');
    const adminSidebar = document.getElementById('adminSidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    
    function toggleSidebar() {
      adminSidebar.classList.toggle('mobile-open');
      sidebarOverlay.classList.toggle('active');
    }
    
    sidebarToggle.addEventListener('click', toggleSidebar);
    sidebarOverlay.addEventListener('click', toggleSidebar);
    
    // Profile dropdown functionality - hover behavior
    const profileDropdown = document.getElementById('profileDropdown');

    profileDropdown.addEventListener('mouseenter', function() {
      this.classList.add('active');
    });
    
    profileDropdown.addEventListener('mouseleave', function() {
      this.classList.remove('active');
    });
    
    // Close sidebar when clicking on a link (for mobile)
    if (window.innerWidth <= 768) {
      const sidebarLinks = document.querySelectorAll('.admin-sidebar .nav-link');
      sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
          adminSidebar.classList.remove('mobile-open');
          sidebarOverlay.classList.remove('active');
        });
      });
    }
    
    // Handle window resize
    window.addEventListener('resize', function() {
      if (window.innerWidth > 768) {
        adminSidebar.classList.remove('mobile-open');
        sidebarOverlay.classList.remove('active');
      }
    });
});
</script>

</body>
</html>