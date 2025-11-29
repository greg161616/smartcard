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
  --sidebar-width: 200px;
  --mobile-breakpoint: 768px;
}

.header {
    height: 70px;
    background:rgb(143, 139, 139);
    display: flex;
    align-items: center;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000; /* Reduced from 1001 to 1000 */
}
.profile-circle {
    width: 60px;
    height: 60px;
    border-radius: 50%;
    object-fit: cover;
    display: block;
    cursor: pointer;
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
    z-index: 1001; /* Increased from 1000 to 1001 */
    display: flex;
    flex-direction: column;
    transition: transform 0.3s ease;
}
.admin-sidebar .logo {
    width: 70px;
    margin: 24px auto 8px auto;
    display: block;
}
.admin-sidebar .school-name {
    font-weight: bold;
    font-size: 20px;
    margin-top: 8px;
    color:rgb(0, 0, 0);
    font-family: 'Merriweather', serif;
    text-align: center;
}
.admin-sidebar .nav-link {
    color: #222;
    text-decoration: none;
    font-size: 17px;
    display: block;
    margin-bottom: 5px;
    transition: all 0.3s ease;
}
.admin-sidebar .nav-link:hover,
.admin-sidebar .nav-link.active {
    background: #e0f7fa;
    color: #007b8a;
    border-radius: 8px;
}
body {
    background:rgb(236, 240, 243);
    margin-left: var(--sidebar-width) !important;
    transition: margin-left 0.3s ease;
    padding-top: 70px; /* Add padding to account for fixed header */
}
a[style*="text-decoration:none"]:hover, a[style*="text-decoration:none"].active {
    background: #e0f7fa !important;
    color: #007b8a !important;
    border-radius: 8px;
}

/* Profile dropdown styles */
.profile-dropdown {
    position: relative;
    display: inline-block;
}
.profile-dropdown-content {
    display: none;
    position: absolute;
    background-color: white;
    min-width: 160px;
    box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
    z-index: 1100;
    border-radius: 8px;
    right: 0;
    top: 70px;
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
.profile-dropdown.active .profile-dropdown-content {
    display: block;
}

/* Scrollable sidebar styles */
.sidebar-content {
    flex: 1;
    overflow-y: auto;
    padding-bottom: 60px; /* Space for the fixed logout button */
}

.sidebar-footer {
    position: fixed;
    bottom: 0;
    left: 0;
    width: var(--sidebar-width);
    background: #b3e6f7;
    padding: 10px 0;
    text-align: center;
    border-top: 1px solid #a0d8ef;
    transition: width 0.3s ease;
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

/* Rotate caret icon when expanded */
.nav-link[aria-expanded="true"] .bi-caret-down-fill {
    transform: rotate(180deg);
}
.bi-caret-down-fill {
    transition: transform 0.3s ease;
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
        padding-top: 70px; /* Maintain padding for fixed header */
    }
    
    .admin-sidebar {
        transform: translateX(-100%);
        top: 70px; /* Add 70px margin-top for mobile */
        height: calc(100vh - 70px); /* Adjust height to account for header */
        z-index: 1002; /* Higher z-index for mobile */
    }
    
    .admin-sidebar.mobile-open {
        transform: translateX(0);
    }
    
    .mobile-toggle {
        display: block;
    }
    
    .sidebar-overlay.active {
        display: block;
        z-index: 998; /* Lower than sidebar but higher than other content */
    }
    
    .sidebar-footer {
        width: var(--sidebar-width);
        bottom: 0;
        left: 0;
    }
    
    .header {
        padding-left: 15px;
        padding-right: 15px;
        position: fixed; /* Ensure header stays fixed */
        top: 0;
        left: 0;
        right: 0;
        z-index: 1001; /* Higher than overlay but lower than sidebar */
    }
    
    .profile-dropdown-content {
        right: 10px;
        top: 65px;
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
      <img src="../img/default.jpg" alt="Profile Picture" class="profile-circle border border-secondary">
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
    <!-- Fixed header section -->
    <div>
        <img src="../img/logo.png" alt="BANAHIS Logo" class="logo">
    </div>
    
    <div class="school-name">SMARTCARD</div>
    <div style="font-size:13px;color:#555;text-align:center;">Student Academic Performance Management System</div>
    
    <!-- Scrollable content section -->
    <div class="sidebar-content">
        <nav class="nav flex-column px-2">
            <!-- Dashboard Link -->
            <a class="nav-link mt-4" href="../administration/dashboard.php">
                <i class="bi bi-speedometer2" style="font-size:20px;margin-right:12px;"></i> Dashboard
            </a>
            
            <!-- Master List with Collapse -->
            <a class="nav-link mt-3" data-bs-toggle="collapse" href="#masterListCollapse" role="button" aria-expanded="false" aria-controls="masterListCollapse">
                <i class="bi bi-people" style="font-size:20px;margin-right:12px;"></i> Master List
                <i class="bi bi-caret-down-fill float-end" style="font-size:14px;"></i>
            </a>
            <div class="collapse ps-4" id="masterListCollapse">
                <a class="nav-link" href="../administration/studentlist.php" style="font-size:15px;">
                    <i class="bi bi-person" style="font-size:16px;margin-right:8px;"></i> Student
                </a>
                <a class="nav-link" href="../administration/teacher.php" style="font-size:15px;">
                    <i class="bi bi-person-badge" style="font-size:16px;margin-right:8px;"></i> Teacher
                </a>      
            </div>
            
            <a class="nav-link mt-3" href="../administration/section.php">
                <i class="bi bi-building" style="font-size:20px;margin-right:12px;"></i> Sections
            </a>
            <a class="nav-link mt-3" href="../administration/subject.php">
                <i class="bi bi-book" style="font-size:20px;margin-right:12px;"></i> Subjects
            </a>
            <a href="../administration/view_logs.php" class="nav-link mt-3">
                <i class="bi bi-journal-text" style="font-size:20px;margin-right:12px;"></i> Logs
            </a>
            <a href="../administration/backup.php" class="nav-link mt-3">
                <i class="bi bi-cloud-arrow-up" style="font-size:20px;margin-right:12px;"></i> Backup & Restore
            </a>
        </nav>
    </div>
    
    <!-- Fixed footer section -->
    <div class="sidebar-footer">
    </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const links = document.querySelectorAll('.admin-sidebar .nav-link');
    const currentUrl = window.location.pathname.replace(/\\/g, '/');
    let masterListActive = false;
    
    // Check if current page matches any of the master list items
    links.forEach(link => {
      if (link.getAttribute('href') === '#masterListCollapse') return;
      
      const linkPath = new URL(link.href, window.location.origin).pathname.replace(/\\/g, '/');
      
      if (currentUrl.endsWith(linkPath)) {
        link.classList.add('active');
        
        // If this is a master list item, expand the master list
        if (
          linkPath.endsWith('/administration/studentlist.php') ||
          linkPath.endsWith('/administration/teacher.php')
        ) {
          masterListActive = true;
        }
      }
    });
    
    // Expand master list if one of its items is active
    if (masterListActive) {
      const masterListCollapse = document.getElementById('masterListCollapse');
      const masterListToggle = document.querySelector('[href="#masterListCollapse"]');
      
      // Use Bootstrap's Collapse API to properly expand it
      const collapse = new bootstrap.Collapse(masterListCollapse, {
        toggle: true
      });
      
      // Update aria-expanded attribute
      masterListToggle.setAttribute('aria-expanded', 'true');
    }
    
    // Add event listener for collapse events to handle caret rotation
    const masterListCollapse = document.getElementById('masterListCollapse');
    if (masterListCollapse) {
      masterListCollapse.addEventListener('show.bs.collapse', function () {
        const caret = document.querySelector('[href="#masterListCollapse"] .bi-caret-down-fill');
        if (caret) {
          caret.style.transform = 'rotate(180deg)';
        }
      });
      
      masterListCollapse.addEventListener('hide.bs.collapse', function () {
        const caret = document.querySelector('[href="#masterListCollapse"] .bi-caret-down-fill');
        if (caret) {
          caret.style.transform = 'rotate(0deg)';
        }
      });
    }
    
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
    
    profileDropdown.addEventListener('click', function(e) {
      e.stopPropagation();
      this.classList.toggle('active');
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function() {
      profileDropdown.classList.remove('active');
    });
    
    // Close sidebar when clicking on a link (for mobile)
    if (window.innerWidth <= 768) {
      const sidebarLinks = document.querySelectorAll('.admin-sidebar .nav-link');
      sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
          if (!this.getAttribute('data-bs-toggle') || this.getAttribute('data-bs-toggle') !== 'collapse') {
            adminSidebar.classList.remove('mobile-open');
            sidebarOverlay.classList.remove('active');
          }
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>