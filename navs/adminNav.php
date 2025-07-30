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
    body {
        background:rgb(236, 240, 243);
        margin-left: 180px !important;
    }
    a[style*="text-decoration:none"]:hover, a[style*="text-decoration:none"].active {
        background: #e0f7fa !important;
        color: #007b8a !important;
        border-radius: 8px;
    }
    /* Announcement styles */
    .announcement-badge {
      position: absolute;
      top: -5px;
      right: -5px;
      font-size: 10px;
      background-color: #dc3545;
      color: white;
      border-radius: 50%;
      width: 18px;
      height: 18px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .announcement-container {
      position: relative;
    }
  </style>
</head>
<body>

<nav class="header navbar navbar-expand navbar-light bg-light px-4">
  <div class="ms-auto dropdown">
    <button class="btn p-0 border-0 bg-transparent d-flex align-items-center" id="profileDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false">
      <img src="../img/default.jpg" alt="Profile Picture" class="profile-circle border border-secondary">
    </button>
    <ul class="dropdown-menu dropdown-menu-end mt-2 shadow" aria-labelledby="profileDropdownBtn">
      <li><a class="dropdown-item" href="../teacher/profile.php">Profile</a></li>
      <li><a class="dropdown-item" href="../teacher/settings.php">Settings</a></li>
      <li><hr class="dropdown-divider"></li>
      <li><a class="dropdown-item text-danger" href="../logout.php">Logout</a></li>
    </ul>
  </div>
</nav>

<div class="admin-sidebar">
  <div>
    <img src="../img/logo.png" alt="BANAHIS Logo" class="logo">
    <div class="school-name">BANAHIS</div>
    <div style="font-size:13px;color:#555;text-align:center;">Balaytigue National High School</div>
    <nav class="nav flex-column">
      <a class="nav-link" href="../admin/principalDash.php">
        <i class="bi bi-speedometer2" style="font-size:20px;margin-right:12px;"></i> Dashboard
      </a>

      <a class="nav-link" data-bs-toggle="collapse" href="#masterListCollapse" role="button" aria-expanded="false" aria-controls="masterListCollapse">
        <i class="bi bi-people" style="font-size:20px;margin-right:12px;"></i> Master List
        <i class="bi bi-caret-down-fill float-end" style="font-size:14px;"></i>
      </a>
      <div class="collapse ps-4" id="masterListCollapse">
        <a class="nav-link" href="../admin/studentlist.php" style="font-size:15px;">
          <i class="bi bi-person" style="font-size:16px;margin-right:8px;"></i> Student
        </a>
        <a class="nav-link" href="../admin/teacher.php" style="font-size:15px;">
          <i class="bi bi-person-badge" style="font-size:16px;margin-right:8px;"></i> Teacher
        </a>
        <a class="nav-link" href="../admin/section.php" style="font-size:15px;">
          <i class="bi bi-diagram-3" style="font-size:16px;margin-right:8px;"></i> Section
        </a>
      </div>

      <a class="nav-link" href="../admin/subject.php">
        <i class="bi bi-journal-bookmark" style="font-size:20px;margin-right:12px;"></i> Subjects
      </a>
      <a class="nav-link" href="../admin/files.php">
        <i class="bi bi-folder2-open" style="font-size:20px;margin-right:12px;"></i> Files
      </a>
      <a class="nav-link" href="../admin/achievement.php">
        <i class="bi bi-award" style="font-size:20px;margin-right:12px;"></i> Achievements
      </a>
      <a class="nav-link" href="../admin/calendar.php">
        <i class="bi bi-calendar3" style="font-size:20px;margin-right:12px;"></i> Calendar
      </a>
    </nav>
  </div>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    const links = document.querySelectorAll('.admin-sidebar .nav-link');
    const currentUrl = window.location.pathname.replace(/\\/g, '/');
    let masterListActive = false;
    
    links.forEach(link => {
      if (link.getAttribute('href') === '#masterListCollapse') return;
      const linkPath = new URL(link.href, window.location.origin).pathname.replace(/\\/g, '/');
      if (currentUrl.endsWith(linkPath)) {
        link.classList.add('active');
        if (
          linkPath.endsWith('/admin/studentlist.php') ||
          linkPath.endsWith('/admin/teacher.php') ||
          linkPath.endsWith('/admin/section.php')
        ) {
          masterListActive = true;
        }
      }
    });
    
    if (masterListActive) {
      document.getElementById('masterListCollapse').classList.add('show');
    }

  });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>