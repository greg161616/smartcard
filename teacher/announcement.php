<?php
session_start();
include '../config.php'; 
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit;
}

// Get teacher information
$teacher_id = $_SESSION['user_id'];
$teacher_query = "SELECT * FROM teacher WHERE userID = '$teacher_id'";
$teacher_result = mysqli_query($conn, $teacher_query);
$teacher = mysqli_fetch_assoc($teacher_result);
$teacher_name = $teacher['fName'] . ' ' . ($teacher['mName'] ? $teacher['mName'] . ' ' : '') . $teacher['lName'];

// Get current school year
$schoolyear_query = "SELECT * FROM school_year WHERE status = 'active' LIMIT 1";
$schoolyear_result = mysqli_query($conn, $schoolyear_query);
$schoolyear = mysqli_fetch_assoc($schoolyear_result);
$selectedSchoolYear = $schoolyear['school_year'];

// Get teacher's advisory class
$advisory_query = "SELECT s.SectionID, s.SectionName, s.GradeLevel 
                   FROM section s 
                   WHERE s.AdviserID = '$teacher_id'";
$advisory_result = mysqli_query($conn, $advisory_query);
$advisory_class = mysqli_fetch_assoc($advisory_result);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_announcement'])) {
        // Delete announcement - only if teacher is the author
        $announcement_id = mysqli_real_escape_string($conn, $_POST['announcement_id']);
        
        // Check if teacher owns this announcement
        $check_query = "SELECT author_id FROM announcements WHERE id = '$announcement_id'";
        $check_result = mysqli_query($conn, $check_query);
        $announcement = mysqli_fetch_assoc($check_result);
        
        if ($announcement && $announcement['author_id'] == $_SESSION['user_id']) {
            $delete_query = "DELETE FROM announcements WHERE id = '$announcement_id'";
            if (mysqli_query($conn, $delete_query)) {
                $_SESSION['success'] = "Announcement deleted successfully!";
            } else {
                $_SESSION['error'] = "Error deleting announcement: " . mysqli_error($conn);
            }
        } else {
            $_SESSION['error'] = "You can only delete your own announcements!";
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
$my_announcements_filter = isset($_GET['my_announcements']) ? true : false;

// Build query with filters
$announcements_query = "SELECT a.*, 
       COALESCE(ad.FullName, CONCAT(t.fName, ' ', t.lName)) AS author_name
FROM announcements a
LEFT JOIN admin ad ON a.author_id = ad.UserID
LEFT JOIN teacher t ON a.author_id = t.userID
WHERE 1=1";

// Add search filter
if (!empty($search)) {
    $announcements_query .= " AND (a.title LIKE '%$search%' OR a.content LIKE '%$search%')";
}

// Filter for teacher's own announcements only
if ($my_announcements_filter) {
    $announcements_query .= " AND a.author_id = '{$_SESSION['user_id']}'";
}

// Add advisory class filter - teachers can see announcements for their advisory class
if ($advisory_class) {
    $advisory_condition = " OR a.target_audience = 'advisory_{$advisory_class['SectionID']}'";
} else {
    $advisory_condition = "";
}

// Teachers can see: all announcements, teacher-only announcements, and their advisory class announcements
$announcements_query .= " AND (a.target_audience = 'all' 
                          OR a.target_audience = 'teachers' 
                          $advisory_condition
                          OR a.author_id = '{$_SESSION['user_id']}')";

// Sorting the results by date in descending order
$announcements_query .= " ORDER BY a.date DESC";

// Execute the query
$announcements_result = mysqli_query($conn, $announcements_query);

// Get the total number of announcements
$total_announcements = mysqli_num_rows($announcements_result);

// Get counts for stats
$my_announcements_query = "SELECT COUNT(*) as count FROM announcements WHERE author_id = '{$_SESSION['user_id']}'";
$my_result = mysqli_query($conn, $my_announcements_query);
$my_count = $my_result ? mysqli_fetch_assoc($my_result)['count'] : 0;

$today = date('Y-m-d');
$today_query = "SELECT COUNT(*) as count FROM announcements WHERE DATE(date) = '$today'";
$today_result = mysqli_query($conn, $today_query);
$today_count = $today_result ? mysqli_fetch_assoc($today_result)['count'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcement Management - Teacher Dashboard</title>
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
        .my-announcement {
            background-color: #f8f9fa;
            border-left: 4px solid #007bff;
        }
        .advisory-info {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
        }
        .header_c {
            background: #2c3e50;
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }
        
        /* Responsive table styles */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        /* Mobile-specific styles */
        @media (max-width: 768px) {
            .header_c {
                padding: 1rem 0;
                margin-bottom: 1rem;
            }
            
            .header_c h1 {
                font-size: 1.5rem;
            }
            
            .card-body {
                padding: 1rem;
            }
            
            .btn-group {
                width: 100%;
            }
            
            .btn-group .btn {
                width: 100%;
                margin-bottom: 0.5rem;
            }
            
            .d-flex.gap-2 {
                flex-direction: column;
            }
            
            .announcement-card .card-body {
                padding: 1rem;
            }
            
            .announcement-card h6 {
                font-size: 0.9rem;
            }
            
            .announcement-card .card-text {
                font-size: 0.8rem;
            }
        }
        
        /* Very small screens */
        @media (max-width: 576px) {
            .container-fluid {
                padding-left: 10px;
                padding-right: 10px;
            }
            
            .col-lg-4, .col-lg-8 {
                padding-left: 5px;
                padding-right: 5px;
            }
            
            .card {
                margin-bottom: 1rem;
            }
            
            .form-control, .form-select {
                font-size: 0.875rem;
            }
        }
    </style>
</head>
<body class="bg-light">
    <?php include '../navs/teacherNav.php'; ?>
    
    <div class="header_c">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-5 fw-bold">Welcome, <?php echo htmlspecialchars($teacher_name); ?>!</h1>
                    <p class="lead mb-0">Announcement Management</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="bg-white rounded-pill px-3 py-2 d-inline-block">
                        <small class="text-muted">School Year: <?php echo htmlspecialchars($selectedSchoolYear); ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container-fluid py-4">

        <!-- Advisory Class Info -->
        <?php if ($advisory_class): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5 class="card-title mb-1">
                                    <i class="fas fa-chalkboard-teacher me-2"></i>Advisory Class
                                </h5>
                                <p class="mb-0">
                                    Grade <?php echo $advisory_class['GradeLevel']; ?> - 
                                    <?php echo htmlspecialchars($advisory_class['SectionName']); ?>
                                </p>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-info-circle me-1"></i>
                                    You will see announcements for this class
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

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
                    <div class="card-header">
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
                                    <?php if ($advisory_class): ?>
                                    <option value="<?php echo $advisory_class['SectionName']; ?>"><?php echo htmlspecialchars($advisory_class['SectionName']); ?>
                                    </option>
                                    <?php endif; ?>
                                    <option value="teachers">Teachers</option>
                                    <option value="students">Students</option>
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
                        <span class="badge bg-secondary"><?php echo $total_announcements; ?> total</span>
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
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" id="my_announcements" name="my_announcements" 
                                           <?php echo $my_announcements_filter ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="my_announcements">
                                        Show only my announcements
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <div class="d-grid w-100">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter me-1"></i>Filter
                                    </button>
                                </div>
                            </div>
                        </form>

                        <!-- Announcements List -->
                        <?php if (mysqli_num_rows($announcements_result) > 0): ?>
                            <div class="table-responsive">
                                <div class="row">
                                    <?php while ($announcement = mysqli_fetch_assoc($announcements_result)): 
                                        $is_my_announcement = ($announcement['author_id'] == $_SESSION['user_id']);
                                        $is_advisory_announcement = ($advisory_class && $announcement['target_audience'] == 'advisory_' . $advisory_class['SectionID']);
                                    ?>
                                        <div class="col-12 mb-3">
                                            <div class="card announcement-card <?php echo $is_my_announcement ? 'my-announcement' : ''; ?>">
                                                <div class="card-body">
                                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                                        <h6 class="card-title text-primary mb-0">
                                                            <?php echo htmlspecialchars($announcement['title']); ?>
                                                            <?php if ($is_my_announcement): ?>
                                                                <span class="badge bg-primary ms-2">My Announcement</span>
                                                            <?php endif; ?>
                                                        </h6>
                                                        <div class="d-flex gap-2">
                                                            <span class="badge bg-light text-dark">
                                                                <?php 
                                                                if (strpos($announcement['target_audience'], 'advisory_') === 0 && $advisory_class) {
                                                                    echo 'Advisory: ' . htmlspecialchars($advisory_class['SectionName']);
                                                                } else {
                                                                    echo ucfirst($announcement['target_audience']);
                                                                }
                                                                ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    
                                                    <p class="card-text text-muted small mb-2">
                                                        <i class="fas fa-user me-1"></i>By: <?php echo htmlspecialchars($announcement['author_name']); ?>
                                                        <i class="fas fa-clock ms-3 me-1"></i><?php echo date('M j, Y g:i A', strtotime($announcement['date'])); ?>
                                                    </p>
                                                    
                                                    <p class="card-text">
                                                        <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                                    </p>
                                                    
                                                    <?php if ($is_my_announcement): ?>
                                                        <div class="d-flex justify-content-end mt-3">
                                                            <form method="POST" action="" 
                                                                  onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                                                <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                                                <button type="submit" name="delete_announcement" 
                                                                        class="btn btn-danger btn-sm">
                                                                    <i class="fas fa-trash me-1"></i>Delete
                                                                </button>
                                                            </form>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
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