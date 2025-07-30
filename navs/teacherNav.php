<link rel="icon" type="image/png" href="../img/logo.png" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Merriweather:wght@700&display=swap" rel="stylesheet" />
<link href="https://fonts.googleapis.com/css2?family=Inter&display=swap" rel="stylesheet" />

<style>
body {
    margin-left: 150px;
    min-height: 100vh;
}
.header {
    height: 70px;
    background: #ffffffff;
    display: flex;
    align-items: center;
    padding: 0 32px;
}
.teacher-sidebar {
    background: #aee9ef;
    min-height: 100vh;
    width: 150px;
    padding: 0;
    position: fixed;
    left: 0;
    top: 0;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    z-index: 1000;
}
/* make it obvious it's clickable */
.teacher-sidebar .logo-link {
    display: block;
    text-align: center;
    margin: 32px auto 24px auto;
}
.teacher-sidebar .logo {
    width: 70px;
    cursor: pointer;
}
.teacher-sidebar .nav {
    flex: 1;
}
.teacher-sidebar .nav-link {
    color: #222;
    display: flex;
    align-items: center;
    gap: 18px;
    border-radius: 8px;
    margin-bottom: 8px;
    transition: background 0.2s;
    text-decoration: none;
}
.teacher-sidebar .nav-link:hover,
.teacher-sidebar .nav-link.active {
    background: #e0f7fa;
    color: #007b8a;
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
</style>

<nav class="header navbar navbar-expand navbar-light bg-light px-4">
    <div class="ms-auto dropdown">
        <button class="btn p-0 border-0 bg-transparent d-flex align-items-center" id="profileDropdownBtn" data-bs-toggle="dropdown" aria-expanded="false">
            <img src="../img/profiles.jpg" alt="Profile Picture" class="profile-circle border border-secondary">
        </button>
        <ul class="dropdown-menu dropdown-menu-end mt-2 shadow" id="profileDropdownMenu" aria-labelledby="profileDropdownBtn">
            <li><a class="dropdown-item" href="../teacher/profile.php">Profile</a></li>
            <li><a class="dropdown-item" href="../teacher/settings.php">Settings</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="../logout.php">Logout</a></li>
        </ul>
    </div>
</nav>

<div class="teacher-sidebar d-flex flex-column">
    <div>
        <!-- Logo wrapped in a link -->
        <a href="../teacher/teacherdash.php" class="logo-link">
            <img src="../img/logo.png" alt="BANAHIS Logo" class="logo">
        </a>
        <nav class="nav flex-column">
            <a class="nav-link" href="../teacher/list.php"><i class="bi bi-clipboard" style="font-size:25px;"></i> Student list</a>
            <a class="nav-link" href="../teacher/select_class.php"><i class="bi bi-person" style="font-size:25px;"></i> Attendance</a>
            <a class="nav-link" href="../teacher/grading_sheet.php"><i class="bi bi-book" style="font-size:25px;"></i> Grading</a>
            <a class="nav-link" href="../teacher/record.php"><i class="bi bi-folder" style="font-size:25px;"></i> Files</a>
        </nav>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const links = document.querySelectorAll('.teacher-sidebar .nav-link');
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
