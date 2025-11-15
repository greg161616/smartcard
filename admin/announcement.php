<?php
session_start();
include '../config.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'principal') {
    header('Location: ../login.php');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_announcement'])) {
        // Delete announcement
        $announcement_id = mysqli_real_escape_string($conn, $_POST['announcement_id']);
        $delete_query = "DELETE FROM announcements WHERE id = '$announcement_id'";
        if (mysqli_query($conn, $delete_query)) {
            $_SESSION['success'] = "Announcement deleted successfully!";
        } else {
            $_SESSION['error'] = "Error deleting announcement: " . mysqli_error($conn);
        }
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    } else {
        // Create new announcement
        $title = mysqli_real_escape_string($conn, $_POST['title']);
        $content = mysqli_real_escape_string($conn, $_POST['content']);
        $target_audience = mysqli_real_escape_string($conn, $_POST['target_audience']);
        
        // Insert announcement into database
        $query = "INSERT INTO announcements (title, content, date, target_audience, author_id) 
                  VALUES ('$title', '$content', NOW(), '$target_audience', '{$_SESSION['user_id']}')";
        
        if (mysqli_query($conn, $query)) {
            $_SESSION['success'] = "Announcement sent successfully!";
        } else {
            $_SESSION['error'] = "Error: " . mysqli_error($conn);
        }
        header("Location: ".$_SERVER['PHP_SELF']);
        exit;
    }
}

// Handle search and filter
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$audience_filter = isset($_GET['audience_filter']) ? mysqli_real_escape_string($conn, $_GET['audience_filter']) : '';

// Build query with filters
$announcements_query = "SELECT a.*, u.Email as author_email 
                        FROM announcements a 
                        LEFT JOIN user u ON a.author_id = u.UserID 
                        WHERE 1=1";

if (!empty($search)) {
    $announcements_query .= " AND (a.title LIKE '%$search%' OR a.content LIKE '%$search%')";
}

if (!empty($audience_filter)) {
    $announcements_query .= " AND a.target_audience = '$audience_filter'";
}

$announcements_query .= " ORDER BY a.date DESC";

$announcements_result = mysqli_query($conn, $announcements_query);
$total_announcements = mysqli_num_rows($announcements_result);

// Get counts for stats
$today = date('Y-m-d');
$today_query = "SELECT COUNT(*) as count FROM announcements WHERE DATE(date) = '$today'";
$today_result = mysqli_query($conn, $today_query);
$today_count = mysqli_fetch_assoc($today_result)['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcement Management - Principal Dashboard</title>
    <link rel="icon" type="image/png" href="../img/logo.png" />
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .announcement-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .announcement-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important;
        }
        .bg-gradient-primary {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        }
    </style>
</head>
<body class="bg-light">
    <?php include '../navs/adminNav.php'; ?>

    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h1 class="h3 mb-1"><i class="fas fa-bullhorn me-2"></i>Announcement Management</h1>
                                <p class="mb-0">Principal Dashboard - Manage school announcements</p>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <p class="mb-1">Welcome, <?php echo $_SESSION['name'] ?? 'Principal'; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card shadow">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h3 class="card-title "><?php echo $total_announcements; ?></h3>
                                <p class="card-text mb-0">Total Announcements</p>
                            </div>
                            <div class="col-4 text-end">
                                <i class="fas fa-bullhorn fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card shadow">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h3 class="card-title "><?php echo $today_count; ?></h3>
                                <p class="card-text mb-0">Today's Announcements</p>
                            </div>
                            <div class="col-4 text-end">
                                <i class="fas fa-calendar-day fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card shadow">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-8">
                                <h3 class="card-title ">0</h3>
                                <p class="card-text mb-0">Active Announcements</p>
                            </div>
                            <div class="col-4 text-end">
                                <i class="fas fa-bell fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alerts -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="row">
            <!-- Create Announcement Form -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow">
                    <div class="card-header bg-primary ">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-plus-circle me-2"></i>Create New Announcement
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="title" class="form-label">Announcement Title</label>
                                <input type="text" class="form-control" id="title" name="title" required 
                                       placeholder="Enter announcement title">
                            </div>
                            
                            <div class="mb-3">
                                <label for="content" class="form-label">Announcement Content</label>
                                <textarea class="form-control" id="content" name="content" rows="5" required 
                                          placeholder="Enter your announcement details..."></textarea>
                                <div class="form-text">
                                    <span id="charCount">0</span> characters
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="target_audience" class="form-label">Target Audience</label>
                                <select class="form-select" id="target_audience" name="target_audience" required>
                                    <option value="all">All Users</option>
                                    <option value="teachers">Teachers Only</option>
                                    <option value="students">Students Only</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-paper-plane me-2"></i>Send Announcement
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Announcements List -->
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2"></i>Announcements
                        </h5>
                        <span class="badge bg-primary"><?php echo $total_announcements; ?> total</span>
                    </div>
                    <div class="card-body">
                        <!-- Filters -->
                        <form method="GET" class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Search announcements..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="audience_filter" class="form-label">Audience</label>
                                <select class="form-select" id="audience_filter" name="audience_filter">
                                    <option value="">All Audiences</option>
                                    <option value="all" <?php echo $audience_filter === 'all' ? 'selected' : ''; ?>>All Users</option>
                                    <option value="teachers" <?php echo $audience_filter === 'teachers' ? 'selected' : ''; ?>>Teachers</option>
                                    <option value="students" <?php echo $audience_filter === 'students' ? 'selected' : ''; ?>>Students</option>
                                    <option value="head_teachers" <?php echo $audience_filter === 'head_teachers' ? 'selected' : ''; ?>>Head Teachers</option>
                                </select>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="d-grid gap-2 w-100">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter me-1"></i>Filter
                                    </button>
                                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i>Clear
                                    </a>
                                </div>
                            </div>
                        </form>

                        <!-- Announcements List -->
                        <?php if (mysqli_num_rows($announcements_result) > 0): ?>
                            <div class="row">
                                <?php while ($announcement = mysqli_fetch_assoc($announcements_result)): ?>
                                    <div class="col-12 mb-3">
                                        <div class="card announcement-card">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="card-title text-primary mb-0">
                                                        <?php echo htmlspecialchars($announcement['title']); ?>
                                                    </h6>
                                                    <div class="d-flex gap-2">
                                                        <span class="badge bg-info">
                                                            <?php echo ucfirst(str_replace('_', ' ', $announcement['target_audience'])); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                
                                                <p class="card-text text-muted small mb-2">
                                                    <i class="fas fa-user me-1"></i>By: <?php echo htmlspecialchars($announcement['author_email']); ?>
                                                    <i class="fas fa-clock ms-3 me-1"></i><?php echo date('M j, Y g:i A', strtotime($announcement['date'])); ?>
                                                </p>
                                                
                                                <p class="card-text">
                                                    <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                                </p>
                                                
                                                <div class="d-flex justify-content-end">
                                                    <form method="POST" action="" 
                                                          onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                                        <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                                        <button type="submit" name="delete_announcement" 
                                                                class="btn btn-danger btn-sm">
                                                            <i class="fas fa-trash me-1"></i>Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Announcements Found</h5>
                                <p class="text-muted">There are no announcements matching your criteria.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Character counter for announcement content
        const contentTextarea = document.getElementById('content');
        const charCount = document.getElementById('charCount');
        
        function updateCharCounter() {
            const length = contentTextarea.value.length;
            charCount.textContent = length;
            
            if (length > 500) {
                charCount.className = 'text-danger';
            } else if (length > 300) {
                charCount.className = 'text-warning';
            } else {
                charCount.className = 'text-muted';
            }
        }
        
        contentTextarea.addEventListener('input', updateCharCounter);
        updateCharCounter(); // Initialize counter
        
        // Auto-resize textarea
        contentTextarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
    </script>
</body>
</html>