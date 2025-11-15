<?php
session_start();
include '../config.php';

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'student') {
    header('Location: ../login.php');
    exit();
}

// Initialize data arrays
$studentData = [];
$adviserData = [];
$announcements = [];

// Get logged-in student email
$email = $_SESSION['email'];

try {
    // Get student information with section enrollment
    $userQuery = $conn->prepare("
        SELECT u.UserID, u.Email, s.*, se.SectionID, se.SchoolYear
        FROM user u 
        JOIN student s ON u.UserID = s.userID 
        LEFT JOIN section_enrollment se ON s.StudentID = se.StudentID 
        WHERE u.Email = ? 
        ORDER BY se.SchoolYear DESC 
        LIMIT 1
    ");
    $userQuery->bind_param("s", $email);
    $userQuery->execute();
    $result = $userQuery->get_result();
    $studentData = $result->fetch_assoc();

    if ($studentData && isset($studentData['SectionID'])) {
        // Get adviser information with complete name and contact details
        $sectionID = $studentData['SectionID'];
        $adviserQuery = $conn->prepare("
            SELECT 
                t.TeacherID,
                t.fName, 
                t.lName, 
                t.mName,
                t.contact,
                sec.SectionName,
                sec.GradeLevel
            FROM section sec 
            JOIN teacher t ON sec.AdviserID = t.TeacherID 
            WHERE sec.SectionID = ?
        ");
        $adviserQuery->bind_param("i", $sectionID);
        $adviserQuery->execute();
        $adviserResult = $adviserQuery->get_result();
        $adviserData = $adviserResult->fetch_assoc();
        
        // Format adviser name if we got data
        if ($adviserData) {
            $adviserData['FullName'] = trim(
                $adviserData['fName'] . ' ' . 
                ($adviserData['mName'] ? $adviserData['mName'] . ' ' : '') . 
                $adviserData['lName']
            );
        }

        // Get announcements for students - only show relevant ones
        $announceQuery = $conn->prepare("
            SELECT a.*, CONCAT(u.fName, ' ', u.mName, ' ', u.lName) as author_name
            FROM announcements a 
            LEFT JOIN teacher u ON a.author_id = u.UserID 
            WHERE a.target_audience IN ('all', 'students') 
            OR (a.target_audience = ?)
            ORDER BY a.date DESC 
            LIMIT 5
        ");
        
        // Check if student has an advisory class for targeted announcements
        $advisory_target =  $adviserData['SectionName'];
        $announceQuery->bind_param("s", $advisory_target);
        $announceQuery->execute();
        $announceResult = $announceQuery->get_result();
        $announcements = $announceResult->fetch_all(MYSQLI_ASSOC);
    } else {
        // If no section, get general announcements only
        $announceQuery = $conn->query("
            SELECT a.*, u.Email as author_email 
            FROM announcements a 
            LEFT JOIN user u ON a.author_id = u.UserID 
            WHERE a.target_audience IN ('all', 'students')
            ORDER BY a.date DESC 
            LIMIT 5
        ");
        
        if ($announceQuery) {
            $announcements = $announceQuery->fetch_all(MYSQLI_ASSOC);
        }
    }

} catch (Exception $e) {
    // Log error (in a real application, you'd want to log this properly)
    error_log("Database error: " . $e->getMessage());
}

$conn->close();
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="../img/logo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Student Portal | Balaytigue National High School</title>
    <style>
        .card {
            margin-top: 20px;
            border-radius: 30px;
        }
        .student-name {
            font-size: 1.25rem;
            font-weight: 500;
        }
        .card-body {
            margin-left: 20px;
            margin-right: 30px;
        }
        .header-cover {
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            position: relative;
            min-height: 180px;
            display: flex;
            align-items: center;
        }
        
        .header-cover::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, #3498db, #2c3e50);
            opacity: 0.7;
            z-index: 0;
        }
        
        .header-cover > * {
            position: relative;
            z-index: 1;
            width: 100%;
        }

        .announcement-card {
            border-left: 4px solid #007bff;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .announcement-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important;
        }
        
        .announcement-badge {
            font-size: 0.75rem;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #dee2e6;
        }
    </style>
</head>
<body>

    <!-- Navbar -->
    <?php include '../navs/studentNav.php'; ?>
    
    <div class="header-cover" style="background-image: url(../img/bg.png);">
        <div class="py-4 bg-dark bg-opacity-50">
            <div class="container">
                <p class="text-secondary mb-1">dashboard</p>
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="text-light">Announcements</h2>
                    <div>
                        <div class="text-light">
                            <div><i class="fas fa-calendar-alt me-2"></i><span id="current-date"></span></div>
                            <div><i class="fas fa-clock me-2"></i><span id="current-time"></span></div>
                        </div>
                    </div>
                </div>
            </div>  
        </div>
    </div>
    
    <div class="container">
        <!-- Student Info Section -->
        <div class="row">
            <div class="col-md-7">
                <div class="card shadow-sm">
                    <div class="card-body p-3">
                        <?php if ($studentData): ?>
                            <h5 class="card-title student-name">
                                <i class="fas fa-user"></i>
                                <?= htmlspecialchars($studentData['FirstName'] . ' ' . 
                                    ($studentData['Middlename'] ? $studentData['Middlename'] . ' ' : '') . 
                                    $studentData['LastName']) ?>
                            </h5>
                            <p class="card-text mb-1"><strong>LRN:</strong> <?= htmlspecialchars($studentData['LRN']) ?></p>
                            <p class="card-text mb-1"><strong>Gender:</strong> <?= htmlspecialchars($studentData['Sex']) ?></p>
                            <p class="card-text mb-1"><strong>Birthdate:</strong> <?= 
                                $studentData['Birthdate'] ? htmlspecialchars(date('F j, Y', strtotime($studentData['Birthdate']))) : 'N/A' ?></p>
                            <p class="card-text"><strong>Contact:</strong> <?= 
                                $studentData['contactNumber'] ? htmlspecialchars($studentData['contactNumber']) : 'N/A' ?></p>
                        <?php else: ?>
                            <p>Student information not found</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Adviser Info Section -->
            <div class="col-md-5">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-user"></i> ADVISER</h5>
                        <?php if (!empty($adviserData)): ?>
                            <p class="card-text mb-1"><strong>Name:</strong> <?= htmlspecialchars($adviserData['FullName']) ?></p>
                            <p class="card-text mb-1"><strong>Grade Level:</strong> <?= htmlspecialchars($adviserData['GradeLevel']) ?></p>
                            <p class="card-text mb-1"><strong>Section:</strong> <?= htmlspecialchars($adviserData['SectionName']) ?></p>
                            <?php if ($adviserData['contact']): ?>
                                <p class="card-text"><strong>Contact:</strong> <?= htmlspecialchars($adviserData['contact']) ?></p>
                            <?php endif; ?>
                        <?php else: ?>
                            <p class="card-text">Adviser information not available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Announcements Section -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h4 class="card-title text-primary mb-0">
                    <i class="fas fa-bullhorn me-2"></i>Latest Announcements
                </h4>
            </div>
            <div class="card-body">
                <?php if (!empty($announcements)): ?>
                    <div class="row">
                        <?php foreach ($announcements as $announcement): 
                            $is_advisory = strpos($announcement['target_audience'], 'advisory_') === 0;
                        ?>
                            <div class="col-12 mb-3">
                                <div class="card announcement-card border-0 shadow-sm">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="card-title text-primary mb-0">
                                                <?= htmlspecialchars($announcement['title']) ?>
                                                <?php if ($is_advisory && !empty($adviserData)): ?>
                                                    <span class="badge bg-success announcement-badge ms-2">
                                                        <i class="fas fa-chalkboard-teacher me-1"></i>Advisory
                                                    </span>
                                                <?php endif; ?>
                                            </h6>
                                            <div class="d-flex gap-2">
                                                <span class="badge bg-info announcement-badge">
                                                    <?php 
                                                    if ($is_advisory && !empty($adviserData)) {
                                                        echo 'Advisory: ' . htmlspecialchars($adviserData['SectionName']);
                                                    } else {
                                                        echo ucfirst(str_replace('_', ' ', $announcement['target_audience']));
                                                    }
                                                    ?>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <p class="card-text text-muted small mb-2">
                                            <i class="fas fa-user me-1"></i>By: <?= htmlspecialchars($announcement['author_name']) ?>
                                            <i class="fas fa-clock ms-3 me-1"></i><?= date('M j, Y g:i A', strtotime($announcement['date'])) ?>
                                        </p>
                                        
                                        <p class="card-text mb-0">
                                            <?= nl2br(htmlspecialchars($announcement['content'])) ?>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-bullhorn"></i>
                        <h5>No Announcements</h5>
                        <p>There are no announcements at the moment.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Function to update date and time separately
        function updateDateTime() {
            const now = new Date();
            
            // Format date (Monday, July 28, 2025)
            const dateOptions = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric'
            };
            document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', dateOptions);
            
            // Format time with AM/PM (10:56:32 PM)
            let hours = now.getHours();
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12; // Convert 0 to 12
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            
            document.getElementById('current-time').textContent = `${hours}:${minutes}:${seconds} ${ampm}`;
        }
        
        // Update immediately and then every second
        updateDateTime();
        setInterval(updateDateTime, 1000);
    </script>

</body>
</html>