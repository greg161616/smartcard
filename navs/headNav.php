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
    .header {
        height: 70px;
        background:rgb(143, 139, 139);
        display: flex;
        align-items: center;
    }
    .profile-circle {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        object-fit: cover;
        display: block;
    }
    .dropdown-item:hover {
        background: rgb(232, 234, 235);
        color: #007b8a;
    }
    .admin-sidebar {
        width: 200px;
        height: 100vh;
        background: #b3e6f7;
        position: fixed;
        left: 0;
        top: 0;
        z-index: 1000;
        display: flex;
        flex-direction: column;
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
        margin-left: 200px !important;
    }
    a[style*="text-decoration:none"]:hover, a[style*="text-decoration:none"].active {
        background: #e0f7fa !important;
        color: #007b8a !important;
        border-radius: 8px;
    }
    .logo-dropdown {
        position: relative;
        display: inline-block;
        width: 100%;
        text-align: center;
    }
    .logo-dropdown-content {
        display: none;
        position: absolute;
        background-color: white;
        min-width: 160px;
        box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
        z-index: 1;
        border-radius: 8px;
        left: 50%;
        transform: translateX(-50%);
        top: 100px;
    }
    .logo-dropdown-content a {
        color: black;
        padding: 12px 16px;
        text-decoration: none;
        display: block;
        text-align: left;
        font-size: 14px;
    }
    .logo-dropdown-content a:hover {
        background-color: #e0f7fa;
        color: #007b8a;
    }
    .logo-dropdown:hover .logo-dropdown-content {
        display: block;
    }
    .logo-dropdown-content hr {
        margin: 5px 0;
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
        width: 200px;
        background: #b3e6f7;
        padding: 10px 0;
        text-align: center;
        border-top: 1px solid #a0d8ef;
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
  </style>
</head>
<body>

<nav class="header navbar navbar-expand navbar-light bg-light px-4">
  <div class="ms-auto">
    <img src="../img/default.jpg" alt="Profile Picture" class="profile-circle border border-secondary">
  </div>
</nav>

<div class="admin-sidebar">
    <!-- Fixed header section -->
    <div class="logo-dropdown">
        <img src="../img/logo.png" alt="BANAHIS Logo" class="logo">
        <div class="logo-dropdown-content">
            <a href="../profile.php"><i class="bi bi-person" style="margin-right: 8px;"></i> Profile</a>
            <a href="../settings.php"><i class="bi bi-gear" style="margin-right: 8px;"></i> Settings</a>
            <hr>
            <a href="../logout.php" class="text-danger"><i class="bi bi-box-arrow-right" style="margin-right: 8px;"></i> Logout</a>
        </div>
    </div>
    
    <div class="school-name">SMARTCARD</div>
    <div style="font-size:13px;color:#555;text-align:center;">Student Academic Performance Management System</div>
    
    <!-- Scrollable content section -->
    <div class="sidebar-content">
        <nav class="nav flex-column px-2">
            <a class="nav-link mt-4" data-bs-toggle="collapse" href="#masterListCollapse" role="button" aria-expanded="false" aria-controls="masterListCollapse">
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
        <a class="nav-link logout-link py-1" href="../logout.php">
            <i class="bi bi-box-arrow-right" style="font-size:20px;margin-right:12px;"></i> Logout
        </a>
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
          linkPath.endsWith('/head/studentlist.php') ||
          linkPath.endsWith('/head/teacher.php') ||
          linkPath.endsWith('/head/section.php') ||
          linkPath.endsWith('/head/subject.php')
        ) {
          masterListActive = true;
        }
      }
    });
    
    // Expand master list if one of its items is active
    if (masterListActive) {
      const masterListCollapse = document.getElementById('masterListCollapse');
      const masterListToggle = document.querySelector('[href="#masterListCollapse"]');
      
      // Simply add the show class and update attributes
      masterListCollapse.classList.add('show');
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
  });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>