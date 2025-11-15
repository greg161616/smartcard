<style>
    .navbar {
        padding: 1.3rem 1rem;
    }
    .navbar-brand {
        font-size: 1.3rem;
    }
    .navbar-nav .nav-link {
        font-size: 1.2rem;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    body {
        background-color: #e8edf3ff;
        margin-top: 80px;
    }
    .navbar-brand img {
        width: 40px;
        height: auto;
        margin-right: 10px;
    }
    .header {
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
</style>

<nav class="navbar fixed-top navbar-expand-lg navbar-light bg-white shadow-sm header">
    <div class="container-fluid">
        <a class="navbar-brand" href="../student/studentPort">
            <img src="../img/logo.png" alt="Logo"> Balaytigue National High School
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav ms-auto" id="mainNav">
                <li class="nav-item">
                    <a class="nav-link" href="../student/studentPort.php">
                        <i class="fas fa-home"></i> Home
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../student/grades.php">
                        <i class="fas fa-graduation-cap"></i> Grades
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../student/profile.php">
                        <i class="fas fa-user"></i> Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<script>
    // Add active class to current page link
    document.addEventListener('DOMContentLoaded', function() {
        const currentLocation = window.location.pathname;
        const navLinks = document.querySelectorAll('#mainNav .nav-link');
        
        navLinks.forEach(link => {
            if (link.getAttribute('href') === currentLocation || 
                (currentLocation.includes('studentPort') && link.getAttribute('href').includes('studentPort'))) {
                link.classList.add('active');
            }
            
            // Add hover effect
            link.addEventListener('mouseenter', function() {
                this.style.opacity = '0.8';
                this.style.transform = 'translateY(-2px)';
            });
            
            link.addEventListener('mouseleave', function() {
                this.style.opacity = '1';
                this.style.transform = 'translateY(0)';
            });
        });
    });
</script>