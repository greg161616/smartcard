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
        background: rgb(143, 139, 139);
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
        width: 180px;
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
        margin-left: 180px !important;
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
        color: #dc3545 !important;
        font-weight: 500;
    }
    .logout-link:hover {
        background: #f8d7da !important;
        color: #721c24 !important;
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
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <div class="logo-dropdown">
            <img src="../img/logo.png" alt="BANAHIS Logo" class="logo">
            <div class="logo-dropdown-content">
                <a href="../profile.php"><i class="bi bi-person" style="margin-right: 8px;"></i> Profile</a>
                <hr>
                <a href="../logout.php" class="text-danger"><i class="bi bi-box-arrow-right" style="margin-right: 8px;"></i> Logout</a>
            </div>
        </div>
        <div>
            <div class="school-name">BANAHIS</div>
            <div style="font-size:13px;color:#555;text-align:center;">Balaytigue National High School</div>
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
            <a class="nav-link logout-link py-2" href="../logout.php">
                <i class="bi bi-box-arrow-right" style="font-size:20px;margin-right:12px;"></i> Logout
            </a>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const links = document.querySelectorAll('.admin-sidebar .nav-link');
    const currentUrl = window.location.pathname.replace(/\\/g, '/');
    links.forEach(link => {
        const linkPath = link.pathname.replace(/\\/g, '/');
        link.classList.toggle('active', currentUrl.endsWith(linkPath));
    });
    // Profile dropdown toggle
    const btn = document.getElementById('profileDropdownBtn');
    const menu = document.getElementById('profileDropdownMenu');
    btn.addEventListener('click', function (e) {
        e.stopPropagation();
        menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
    });
    document.addEventListener('click', () => menu.style.display = 'none');
    menu.addEventListener('click', e => e.stopPropagation());
});
</script>