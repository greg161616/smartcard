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
        width: 180px;
        min-height: 100vh;
        background: #b3e6f7;
        position: fixed;
        left: 0;
        top: 0;
        z-index: 1000;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
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
        background:rgb(236, 240, 243);
        margin-left: 180px !important;
    }
    a[style*="text-decoration:none"]:hover, a[style*="text-decoration:none"].active {
        background: #e0f7fa !important;
        color: #007b8a !important;
        border-radius: 8px;
    }
    /* Logo dropdown styles */
        .logo{
        border-radius: 50%;
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

  </style>
</head>
<body>

<nav class="header navbar navbar-expand navbar-light bg-light px-4">
  <div class="ms-auto">
    <img src="../img/default.jpg" alt="Profile Picture" class="profile-circle border border-secondary">
  </div>
</nav>

<div class="admin-sidebar">
  <div>
    <!-- Logo with dropdown on hover -->
    <div class="logo-dropdown">
      <img src="../img/logo.png" alt="BANAHIS Logo" class="logo">
      <div class="logo-dropdown-content">
        <a href="profile.php"><i class="bi bi-person" style="margin-right: 8px;"></i> Profile</a>
        <hr>
        <a href="../logout.php" class="text-danger"><i class="bi bi-box-arrow-right" style="margin-right: 8px;"></i> Logout</a>
      </div>
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
  
  <!-- Logout button at the bottom of the sidebar -->
  <div class="text-center">
    <a class="nav-link logout-link py-1" href="../logout.php">
      <i class="bi bi-box-arrow-right" style="font-size:20px;margin-right:12px;"></i> Logout
    </a>
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