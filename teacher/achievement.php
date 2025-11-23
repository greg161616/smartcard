<?php
session_start();
include '../config.php'; 

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit;
}

// Get teacher's advisory section
$teacher_id = $_SESSION['user_id'];
$advisory_sql = "SELECT s.SectionID, s.SectionName, s.GradeLevel 
                 FROM section s 
                 WHERE s.AdviserID = (SELECT TeacherID FROM teacher WHERE UserID = ?)";
$stmt = $conn->prepare($advisory_sql);
$stmt->bind_param("i", $teacher_id);
$stmt->execute();
$advisory_result = $stmt->get_result();
$advisory = $advisory_result->fetch_assoc();

if (!$advisory) {
    die("You are not assigned as an adviser of any section.");
}

$section_id = $advisory['SectionID'];
$section_name = $advisory['SectionName'];
$grade_level = $advisory['GradeLevel'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_achievement'])) {
        $student_id = $_POST['student_id'];
        $achievement_type = $_POST['achievement_type'];
        $event_name = $_POST['event_name'];
        $description = $_POST['description'];
        $achievement_date = $_POST['achievement_date'];
        $level = $_POST['level'];
        $award = $_POST['award'];
        
        // Verify student is in teacher's advisory
        $verify_sql = "SELECT se.StudentID FROM section_enrollment se 
                      WHERE se.StudentID = ? AND se.SectionID = ? AND se.SchoolYear = '2025-2026'";
        $verify_stmt = $conn->prepare($verify_sql);
        $verify_stmt->bind_param("ii", $student_id, $section_id);
        $verify_stmt->execute();
        $verified = $verify_stmt->get_result()->fetch_assoc();
        
        if ($verified) {
            // Insert achievement into database
            $sql = "INSERT INTO achievements (student_id, achievement_type, event_name, description, achievement_date, level, award) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssss", $student_id, $achievement_type, $event_name, $description, $achievement_date, $level, $award);
            
            if ($stmt->execute()) {
                $success_message = "Achievement added successfully!";
            } else {
                $error_message = "Error adding achievement: " . $conn->error;
            }
        } else {
            $error_message = "Student is not in your advisory class.";
        }
    }
    
    // Handle AJAX delete request
    if (isset($_POST['delete_achievement'])) {
        $achievement_id = $_POST['achievement_id'];
        
        // Verify achievement belongs to teacher's advisory student
        $verify_sql = "SELECT a.AchievementID FROM achievements a 
                      JOIN section_enrollment se ON a.student_id = se.StudentID 
                      WHERE a.AchievementID = ? AND se.SectionID = ? AND se.SchoolYear = '2025-2026'";
        $verify_stmt = $conn->prepare($verify_sql);
        $verify_stmt->bind_param("ii", $achievement_id, $section_id);
        $verify_stmt->execute();
        $verified = $verify_stmt->get_result()->fetch_assoc();
        
        if ($verified) {
            // Delete achievement
            $sql = "DELETE FROM achievements WHERE AchievementID = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $achievement_id);
            
            if ($stmt->execute()) {
                echo 'success';
            } else {
                echo 'error';
            }
        } else {
            echo 'unauthorized';
        }
        exit;
    }
}

// Handle AJAX get achievement details
if (isset($_GET['get_achievement_details'])) {
    $achievement_id = $_GET['achievement_id'];
    
    // Verify achievement belongs to teacher's advisory
    $sql = "SELECT a.*, s.FirstName, s.LastName, s.Middlename, s.LRN 
            FROM achievements a 
            JOIN student s ON a.student_id = s.StudentID 
            JOIN section_enrollment se ON s.StudentID = se.StudentID 
            WHERE a.AchievementID = ? AND se.SectionID = ? AND se.SchoolYear = '2025-2026'";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $achievement_id, $section_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($achievement = $result->fetch_assoc()) {
        echo '<h6>Student Information</h6>';
        echo '<p><strong>Name:</strong> ' . $achievement['LastName'] . ', ' . $achievement['FirstName'] . ' ' . $achievement['Middlename'] . '</p>';
        echo '<p><strong>LRN:</strong> ' . $achievement['LRN'] . '</p>';
        
        echo '<h6 class="mt-3">Achievement Details</h6>';
        echo '<p><strong>Type:</strong> ' . $achievement['achievement_type'] . '</p>';
        echo '<p><strong>Event:</strong> ' . $achievement['event_name'] . '</p>';
        echo '<p><strong>Description:</strong> ' . ($achievement['description'] ?: 'No description provided') . '</p>';
        echo '<p><strong>Date:</strong> ' . date('F j, Y', strtotime($achievement['achievement_date'])) . '</p>';
        echo '<p><strong>Level:</strong> ' . $achievement['level'] . '</p>';
        echo '<p><strong>Award:</strong> ' . $achievement['award'] . '</p>';
        echo '<p><strong>Recorded on:</strong> ' . date('F j, Y g:i A', strtotime($achievement['created_at'])) . '</p>';
    } else {
        echo '<p class="text-danger">Achievement not found or you are not authorized to view it.</p>';
    }
    exit;
}

// Fetch achievements for teacher's advisory only
$achievements_sql = "
    SELECT a.*, s.FirstName, s.LastName, s.Middlename 
    FROM achievements a 
    JOIN student s ON a.student_id = s.StudentID 
    JOIN section_enrollment se ON s.StudentID = se.StudentID 
    WHERE se.SectionID = ? AND se.SchoolYear = '2025-2026'
    ORDER BY a.achievement_date DESC
";
$achievements_stmt = $conn->prepare($achievements_sql);
$achievements_stmt->bind_param("i", $section_id);
$achievements_stmt->execute();
$achievements_result = $achievements_stmt->get_result();

// Fetch students from teacher's advisory only
$students_sql = "SELECT s.StudentID, s.FirstName, s.LastName, s.Middlename 
                 FROM student s 
                 JOIN section_enrollment se ON s.StudentID = se.StudentID 
                 WHERE se.SectionID = ? AND se.SchoolYear = '2025-2026' 
                 ORDER BY s.LastName, s.FirstName";
$students_stmt = $conn->prepare($students_sql);
$students_stmt->bind_param("i", $section_id);
$students_stmt->execute();
$students_result = $students_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Achievements - <?php echo $section_name; ?> Advisory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .card-header {
            border-radius: 10px 10px 0 0 !important;
        }
        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
        }
        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2e59d9;
        }
        .achievement-badge {
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-weight: bold;
        }
        .table th {
            background-color: #f8f9fc;
        }
        .level-school { background-color: #e8f5e9; color: #2e7d32; }
        .level-district { background-color: #e3f2fd; color: #1565c0; }
        .level-regional { background-color: #fff3e0; color: #ef6c00; }
        .level-national { background-color: #fce4ec; color: #c2185b; }
        .level-international { background-color: #f3e5f5; color: #7b1fa2; }
        .stats-card {
            text-align: center;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .stats-card i {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        .stats-card.sports { background-color: #e8f5e9; }
        .stats-card.journalism { background-color: #e3f2fd; }
        .stats-card.academic { background-color: #fff3e0; }
        .stats-card.arts { background-color: #bff8ffff; }
        .advisory-header {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include '../navs/teacherNav.php'; ?>
    
    <div class="container mt-4">
        <!-- Advisory Class Header -->
        <div class="advisory-header">
            <div class="row">
                <div class="col-md-8">
                    <h2><i class="fas fa-users me-2"></i>My Advisory Class</h2>
                    <h4>Grade <?php echo $grade_level; ?> - <?php echo $section_name; ?></h4>
                    <p class="mb-0">Student Achievements Management</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="bg-light text-dark rounded p-3 d-inline-block">
                        <h5 class="mb-0"><?php echo $students_result->num_rows; ?> Students</h5>
                        <small>Total in advisory</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stats-card sports">
                    <i class="fas fa-running text-success"></i>
                    <h4><?php 
                        $sports_count = $conn->query("SELECT COUNT(*) FROM achievements a JOIN section_enrollment se ON a.student_id = se.StudentID WHERE a.achievement_type = 'Sports' AND se.SectionID = $section_id AND se.SchoolYear = '2025-2026'")->fetch_row()[0];
                        echo $sports_count;
                    ?></h4>
                    <p>Sports Achievements</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card journalism">
                    <i class="fas fa-newspaper text-primary"></i>
                    <h4><?php 
                        $journalism_count = $conn->query("SELECT COUNT(*) FROM achievements a JOIN section_enrollment se ON a.student_id = se.StudentID WHERE a.achievement_type = 'Journalism' AND se.SectionID = $section_id AND se.SchoolYear = '2025-2026'")->fetch_row()[0];
                        echo $journalism_count;
                    ?></h4>
                    <p>Journalism Awards</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card academic">
                    <i class="fas fa-graduation-cap text-warning"></i>
                    <h4><?php 
                        $academic_count = $conn->query("SELECT COUNT(*) FROM achievements a JOIN section_enrollment se ON a.student_id = se.StudentID WHERE a.achievement_type = 'Academic Contest' AND se.SectionID = $section_id AND se.SchoolYear = '2025-2026'")->fetch_row()[0];
                        echo $academic_count;
                    ?></h4>
                    <p>Academic Contests</p>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card arts">
                    <i class="fas fa-palette text-info"></i>
                    <h4><?php 
                        $arts_count = $conn->query("SELECT COUNT(*) FROM achievements a JOIN section_enrollment se ON a.student_id = se.StudentID WHERE a.achievement_type = 'Arts' AND se.SectionID = $section_id AND se.SchoolYear = '2025-2026'")->fetch_row()[0];
                        echo $arts_count;
                    ?></h4>
                    <p>Arts Achievements</p>
                </div>
            </div>
        </div>
        
        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <!-- Add Achievement Form -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-trophy me-2"></i>Add Student Achievement</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="">
                            <div class="mb-3">
                                <label for="student_id" class="form-label">Student</label>
                                <select class="form-select" id="student_id" name="student_id" required>
                                    <option value="">Select Student</option>
                                    <?php 
                                    if ($students_result && $students_result->num_rows > 0):
                                        while ($student = $students_result->fetch_assoc()): 
                                    ?>
                                        <option value="<?php echo $student['StudentID']; ?>">
                                            <?php echo $student['LastName'] . ', ' . $student['FirstName'] . ' ' . $student['Middlename']; ?>
                                        </option>
                                    <?php 
                                        endwhile;
                                    else:
                                    ?>
                                        <option value="">No students found in your advisory</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="achievement_type" class="form-label">Achievement Type</label>
                                <select class="form-select" id="achievement_type" name="achievement_type" required>
                                    <option value="">Select Type</option>
                                    <option value="Sports">Sports</option>
                                    <option value="Journalism">Journalism</option>
                                    <option value="Academic Contest">Academic Contest</option>
                                    <option value="Arts">Arts</option>
                                    <option value="Leadership">Leadership</option>
                                    <option value="Community Service">Community Service</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="event_name" class="form-label">Event Name</label>
                                <input type="text" class="form-control" id="event_name" name="event_name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="achievement_date" class="form-label">Date of Achievement</label>
                                <input type="date" class="form-control" id="achievement_date" name="achievement_date" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="level" class="form-label">Level</label>
                                <select class="form-select" id="level" name="level" required>
                                    <option value="">Select Level</option>
                                    <option value="School">School</option>
                                    <option value="District">District</option>
                                    <option value="Regional">Regional</option>
                                    <option value="National">National</option>
                                    <option value="International">International</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="award" class="form-label">Award/Recognition</label>
                                <input type="text" class="form-control" id="award" name="award" required>
                            </div>
                            
                            <button type="submit" name="add_achievement" class="btn btn-primary w-100">
                                <i class="fas fa-plus-circle me-2"></i>Add Achievement
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Achievements Table -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><i class="fas fa-list me-2"></i>Student Achievements</h5>
                        <span class="badge bg-light text-dark"><?php echo ($achievements_result ? $achievements_result->num_rows : 0); ?> records</span>
                    </div>
                    <div class="card-body">
                        <?php if ($achievements_result && $achievements_result->num_rows > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th>Student Name</th>
                                            <th>Achievement Type</th>
                                            <th>Event</th>
                                            <th>Date</th>
                                            <th>Level</th>
                                            <th>Award</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($achievement = $achievements_result->fetch_assoc()): ?>
                                            <tr>
                                                <td><?php echo $achievement['LastName'] . ', ' . $achievement['FirstName'] . ' ' . $achievement['Middlename']; ?></td>
                                                <td><?php echo $achievement['achievement_type']; ?></td>
                                                <td><?php echo $achievement['event_name']; ?></td>
                                                <td><?php echo date('M j, Y', strtotime($achievement['achievement_date'])); ?></td>
                                                <td>
                                                    <span class="achievement-badge level-<?php echo strtolower($achievement['level']); ?>">
                                                        <?php echo $achievement['level']; ?>
                                                    </span>
                                                </td>
                                                <td><?php echo $achievement['award']; ?></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary view-achievement" 
                                                            data-id="<?php echo $achievement['AchievementID']; ?>" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#achievementModal">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger delete-achievement" 
                                                            data-id="<?php echo $achievement['AchievementID']; ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="fas fa-trophy fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No achievements found for your advisory class. Add some student achievements to get started.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Achievement Details Modal -->
    <div class="modal fade" id="achievementModal" tabindex="-1" aria-labelledby="achievementModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="achievementModalLabel">Achievement Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="achievementDetails">
                    <!-- Details will be loaded here via AJAX -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // View achievement details
            $('.view-achievement').click(function() {
                var achievementId = $(this).data('id');
                
                $.ajax({
                    url: '?get_achievement_details=true&achievement_id=' + achievementId,
                    type: 'GET',
                    success: function(response) {
                        $('#achievementDetails').html(response);
                    },
                    error: function() {
                        $('#achievementDetails').html('<p class="text-danger">Error loading achievement details.</p>');
                    }
                });
            });
            
            // Delete achievement
            $('.delete-achievement').click(function() {
                var achievementId = $(this).data('id');
                var row = $(this).closest('tr');
                
                if (confirm('Are you sure you want to delete this achievement?')) {
                    $.ajax({
                        url: '',
                        type: 'POST',
                        data: {
                            delete_achievement: true,
                            achievement_id: achievementId
                        },
                        success: function(response) {
                            if (response === 'success') {
                                row.fadeOut(300, function() {
                                    $(this).remove();
                                    // Update record count
                                    var recordCount = $('tbody tr').length;
                                    $('.card-header .badge').text(recordCount + ' records');
                                });
                            } else if (response === 'unauthorized') {
                                alert('You are not authorized to delete this achievement.');
                            } else {
                                alert('Error deleting achievement.');
                            }
                        },
                        error: function() {
                            alert('Error deleting achievement.');
                        }
                    });
                }
            });
            
            // Set today's date as default for achievement date
            var today = new Date().toISOString().split('T')[0];
            $('#achievement_date').val(today);
        });
    </script>
</body>
</html>