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
        $profilePicture = $result->fetch_assoc();
        $profilePicturePath = $profilePicture['path'];
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
    
    .dropdown-item:hover {
        background: rgb(232, 234, 235);
        color: #007b8a;
    }
    .admin-sidebar {
        width: var(--sidebar-width);
        min-height: 100vh;
        background: #b3e6f7;
        position: fixed;
        left: 0;
        top: 0;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        transition: transform 0.3s ease;
    }
    .admin-sidebar .logo {
        width: 70px;
        margin: 24px auto 8px auto;
        display: block;
        cursor: pointer;
        transition: transform 0.3s ease;
    }
    .admin-sidebar .logo:hover {
        transform: scale(1.1);
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
    .admin-sidebar .logout-link {
        border: #007b8a solid 2px;
        margin-bottom: 20px;
        border-radius: 8px;
    }
    .admin-sidebar .logout-link:hover {
        background: #e0f7fa;
        color: #007b8a;
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
    
    /* Profile dropdown styles - FIXED VERSION */
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
        padding: 5px 0;
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
    
    /* Hover functionality for desktop */
    @media (min-width: 769px) {
        .profile-dropdown:hover .profile-dropdown-content {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }
    }
    
    /* Click functionality for mobile */
    @media (max-width: 768px) {
        .profile-dropdown.active .profile-dropdown-content {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }
    }
    
    /* Logo styles */
    .logo {
        border-radius: 50%;
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
    
    /* Scrollable sidebar content */
    .sidebar-content {
        flex: 1;
        overflow-y: auto;
        padding: 10px 0;
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
    
    /* Mobile responsive styles */
    @media (max-width: 768px) {
        :root {
            --sidebar-width: 250px;
        }
        
        body {
            margin-left: 0 !important;
            padding-top: 70px; /* Add padding to account for fixed header */
        }
        
        .admin-sidebar {
            transform: translateX(-100%);
            top: 70px; /* Add 70px margin-top for mobile */
            height: calc(100vh - 70px); /* Adjust height to account for the margin */
            justify-content: flex-start; /* Change to flex-start for scrollable layout */
        }
        
        .admin-sidebar.mobile-open {
            transform: translateX(0);
        }
        
        .mobile-toggle {
            display: block;
        }
        
        .sidebar-overlay.active {
            display: block;
            top: 70px; /* Adjust overlay to start below header */
            height: calc(100% - 70px);
        }
        
        .header {
            padding-left: 15px;
            padding-right: 15px;
            position: fixed; /* Make header fixed */
            top: 0;
            left: 0;
            right: 0;
            z-index: 1001;
        }
        
        .profile-dropdown-content {
            right: 10px;
            top: 100%;
        }
        
        /* Make sidebar content scrollable on mobile */
        .sidebar-content {
            overflow-y: auto;
            max-height: calc(100vh - 140px); /* Account for header and logout button */
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
  <!-- Scrollable content area -->
  <div class="sidebar-content">
    <!-- Logo without dropdown (dropdown is now on profile) -->
    <div class="text-center">
      <img src="../img/logo.png" alt="BANAHIS Logo" class="logo">
    </div>
    
    <div class="school-name">SMARTCARD</div>
    <div style="font-size:13px;color:#555;text-align:center; padding: 0 10px;">Student Academic Performance Management System</div>
    
    <nav class="nav flex-column mt-3">
      <a class="nav-link" href="../admin/principalDash.php">
        <i class="bi bi-speedometer2" style="font-size:20px;margin-right:12px;"></i> Dashboard
      </a>
        <a class="nav-link" href="../admin/teacher.php" style="font-size:15px;">
          <i class="bi bi-person-badge" style="font-size:20px;margin-right:8px;"></i> Teacher
        </a>
      <a class="nav-link" href="../admin/select_sec.php">
        <i class="bi bi-folder2-open" style="font-size:20px;margin-right:12px;"></i> Grades
      </a>
      <a class="nav-link" href="../admin/achievement.php">
        <i class="bi bi-award" style="font-size:20px;margin-right:12px;"></i> Top Students
      </a>
      <a class="nav-link" href="../admin/calendar.php">
        <i class="bi bi-calendar3" style="font-size:20px;margin-right:12px;"></i> Calendar
      </a>
      <a class="nav-link" href="../admin/view_logs.php">
        <i class="bi bi-list-check" style="font-size:20px;margin-right:12px;"></i> Logs
      </a>
    </nav>
  </div>
  
  <!-- Fixed logout button at the bottom -->
  <div class="text-center">
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
    
    // Profile dropdown functionality
    const profileDropdown = document.getElementById('profileDropdown');
    
    // Only add click functionality for mobile devices
    if (window.innerWidth <= 768) {
        profileDropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            this.classList.toggle('active');
        });
        
        // Close dropdown when clicking outside (for mobile)
        document.addEventListener('click', function() {
            profileDropdown.classList.remove('active');
        });
    }
    
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