<?php
// File: upload.php

session_start();
require '../config.php';
date_default_timezone_set('Asia/Manila');

// 1) Ensure teacher is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

// Fetch teacher's name and ID
if (!isset($_SESSION['teacher_id'])) {
    // Try to get teacher_id from database based on user_id
    $user_id = $_SESSION['user_id'];
    $teacher_query = $conn->prepare("SELECT TeacherID FROM teacher WHERE userID = ?");
    $teacher_query->bind_param('i', $user_id);
    $teacher_query->execute();
    $teacher_result = $teacher_query->get_result();
    
    if ($teacher_result->num_rows) {
        $_SESSION['teacher_id'] = $teacher_result->fetch_assoc()['TeacherID'];
        $teacher_id = $_SESSION['teacher_id'];
    } else {
        // Handle error - user is not a teacher
        echo "Error: User is not registered as a teacher.";
        exit;
    }
} else {
    $teacher_id = $_SESSION['teacher_id'];
}

// Get current school year
$current_sy_query = $conn->query("SELECT school_year FROM school_year WHERE status = 'active' LIMIT 1");
$current_sy = $current_sy_query->fetch_assoc()['school_year'] ?? date('Y') . '-' . (date('Y') + 1);

$teacherName = '';
$stmt = $conn->prepare("SELECT CONCAT(fName, ' ', lName) AS FullName FROM teacher WHERE TeacherID = ?");
$stmt->bind_param('i', $teacher_id);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
     $teacherName = $row['FullName'];
}
$stmt->close();

// 2) Grab & validate subject_id and section_id from query string
$subjectId = isset($_GET['subject_id']) ? (int)$_GET['subject_id'] : 0;
$sectionId = isset($_GET['section_id']) ? (int)$_GET['section_id'] : 0;

// 3) Validate that teacher is assigned to teach this subject and section
$stmt = $conn->prepare("
    SELECT s.SubjectName 
    FROM subject s 
    INNER JOIN assigned_subject a ON s.SubjectID = a.subject_id 
    WHERE s.SubjectID = ? AND a.teacher_id = ? AND a.section_id = ?
");
$stmt->bind_param('iii', $subjectId, $teacher_id, $sectionId);
$stmt->execute();
$res = $stmt->get_result();
if (!$res->num_rows) {
    echo "You are not assigned to teach this subject for this section or it does not exist."; 
    exit;
}
$subjectName = $res->fetch_assoc()['SubjectName'];
$stmt->close();

// 4) Fetch section info
$stmt = $conn->prepare("SELECT GradeLevel, SectionName FROM section WHERE SectionID = ?");
$stmt->bind_param('i', $sectionId);
$stmt->execute();
$res = $stmt->get_result();
if (!$res->num_rows) {
    echo "Invalid section."; exit;
}
$section = $res->fetch_assoc();
$stmt->close();

// Determine default quarter based on existing uploaded quarters
$defaultQuarter = 1;
$qstmt = $conn->prepare("SELECT DISTINCT quarter FROM grades_details WHERE subjectID = ? AND teacherID = ? ORDER BY quarter ASC");
if ($qstmt) {
    $qstmt->bind_param('ii', $subjectId, $teacher_id);
    $qstmt->execute();
    $qres = $qstmt->get_result();
    $maxQ = 0;
    while ($r = $qres->fetch_assoc()) {
        $qnum = (int)$r['quarter'];
        if ($qnum > $maxQ) $maxQ = $qnum;
    }
    if ($maxQ == 0) {
        $defaultQuarter = 1;
    } elseif ($maxQ < 4) {
        $defaultQuarter = $maxQ + 1;
    } else {
        $defaultQuarter = 4;
    }
    $qstmt->close();
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Grades</title>
    <link rel="icon" href="../img/logo.png" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4e73df;
            --secondary: #6f42c1;
            --success: #1cc88a;
            --info: #36b9cc;
            --warning: #f6c23e;
            --danger: #e74a3b;
            --light: #f8f9fc;
            --dark: #5a5c69;
        }
        
        .upload-area {
            border: 2px dashed #0cb929ff;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s;
            background-color: #f8f9fc;
            max-height: 200px;
        }
        
        .upload-area:hover {
            border-color: var(--success);
            background-color: #eaecf4;
        }
        
        .upload-icon {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        
        .instructions {
            background-color: #f8f9fc;
            border-left: 4px solid var(--info);
            padding: 1rem;
            border-radius: 4px;
        }
        
        .alert {
            border: none;
            border-radius: 8px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .alert-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .student-list {
            max-height: 200px;
            overflow-y: auto;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            position: relative;
        }

        .form-select:focus, .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }
        
        .instructions {
            background: linear-gradient(to right, #f8f9fc 0%, #eaecf4 100%);
        }
        
        .modal-success .modal-header {
            background-color: var(--success);
            color: white;
        }
        
        .modal-error .modal-header {
            background-color: var(--danger);
            color: white;
        }
        
        .result-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        /* Close/back button in header */
        .card-header .close-card-btn {
            position: absolute;
            right: 12px;
            top: 8px;
            color: white;
            background: transparent;
            border: none;
            font-size: 1.25rem;
            opacity: 0.95;
        }
        .card-header .close-card-btn:hover { opacity: 1; color: #fff; }
    </style>
</head>
<body>
    <?php include '../navs/teacherNav.php'; ?>
    <div class="container mb-3">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card shadow-lg">
                    <div class="card-header py-3 bg-info text-white position-relative">
                        <h3 class="text-center mb-0"><i class="fas fa-file-upload me-2"></i>Upload Grade File</h3>
                        <a href="grading_sheet.php" class="close-card-btn" title="Back to grading sheet">
                            <i class="fas fa-times" style="font-size: 28px;"></i>
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="instructions">
                            <h5><i class="fas fa-info-circle me-2"></i>Instructions</h5>
                            <p class="mb-0">
                                - Ensure your Excel file follows the correct format.<br>
                                - Select the appropriate quarter for the grades you're uploading.<br>
                                - Only .xlsx and .xls file formats are supported.<br>
                                - The system will automatically validate and process the grades after upload.
                            </p>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mt-3">
                        <form id="uploadForm" enctype="multipart/form-data">
                         <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
                         <input type="hidden" name="section_id" value="<?= $sectionId ?>">
                         <input type="hidden" name="school_year" value="<?= $current_sy ?>">
                            <div class="row">
                                <div class="col-md-6 mt-4">
                                    <div class="mb-4">
                                        <h4 class="text-secondary">Teacher: </h4>
                                        <h3><strong><?= htmlspecialchars($teacherName) ?></strong></h3>
                                    </div>
                                    <select name="quarter" class="form-control" required>
                                        <option value="1" <?= isset($defaultQuarter) && $defaultQuarter == 1 ? 'selected' : '' ?>>Quarter 1</option>
                                        <option value="2" <?= isset($defaultQuarter) && $defaultQuarter == 2 ? 'selected' : '' ?>>Quarter 2</option>
                                        <option value="3" <?= isset($defaultQuarter) && $defaultQuarter == 3 ? 'selected' : '' ?>>Quarter 3</option>
                                        <option value="4" <?= isset($defaultQuarter) && $defaultQuarter == 4 ? 'selected' : '' ?>>Quarter 4</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <div class="mt-2 text-secondary">
                                        <h5>
                                            Subject: <strong><?= htmlspecialchars($subjectName) ?></strong>
                                        </h5>
                                        <h5>Section: <br>
                                        Grade <?= $section['GradeLevel'] ?>-<?= htmlspecialchars($section['SectionName']) ?></h5>
                                        <h6>School Year: <?= $current_sy ?></h6>
                                        <div class="py-4 text-center">
                                            <a href="view_grades.php?subject_id=<?= $subjectId ?>&section_id=<?= $sectionId ?>" class="btn btn-outline-success">View Grades</a>
                                        </div>
                                    </div>
                                </div>
                            <div class="text-center mt-3 card py-3">
    <?php
    if($subjectName == "Music" || $subjectName == "Arts" ||
     $subjectName == "PE" || $subjectName == "Health" || $subjectName == "MAPEH") {
        $templateFilename = "MAPEH.zip";
    } else {
        $templateFilename = str_replace(' ', '_', $subjectName) . '.xlsx';
    }
    $templatePath = 'ClassRecord/' . $templateFilename;
    
    // Check if specific subject template exists, otherwise use default
    if (file_exists($templatePath)) {
        $downloadLink = $templatePath;
        $linkText = "Download " . $subjectName . " Template";
    } else {
        // Fallback to default template
        $downloadLink = 'ClassRecord/Default_Template.xlsx';
        $linkText = "Download Template";
        
        // If default doesn't exist either, show message
        if (!file_exists($downloadLink)) {
            echo "<span class='text-warning'><i class='fas fa-exclamation-triangle me-1'></i>Template not available</span>";
            $downloadLink = "#";
            $linkText = "Template Not Available";
        }
    }
    ?>
    <a href="<?= $downloadLink ?>" download class="btn btn-outline-primary btn-sm">
        <i class="fas fa-download me-1"></i> <?= $linkText ?>
    </a>
</div>
                            </div>
                            </div>
                            <div class="col-md-6 mt-3">
                                <div class="mb-4">
                                <label for="grade_file" class="form-label">Upload Grade File:</label>
                                <div class="upload-area p-3 mb-3">
                                    <input type="file" class="form-control d-none" id="grade_file" name="grade_file" accept=".xlsx,.xls" required>
                                    <button type="button" class="btn btn-success btn-sm" onclick="document.getElementById('grade_file').click()">
                                        <i class="fas fa-file-excel me-1"></i> Browse Excel Files
                                    </button>
                                    <div class="mt-2" id="file-name">No file chosen</div>
                                </div>
                                <div class="form-text">Supported formats: .xlsx, .xls (Max size: 10MB)</div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-upload me-2"></i> Upload and Process
                                </button>
                            </div>
                            </div>

                        </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Modal -->
    <div class="modal fade" id="loadingModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="fas fa-sync-alt fa-spin me-2"></i>Processing Upload</h5>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mb-0">Please wait while we process your grade file...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal fade" id="successModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-success">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-check-circle me-2"></i>Upload Successful</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="fas fa-check-circle result-icon text-success"></i>
                    <h4 id="successMessage"></h4>
                    <div id="missingStudentsSuccess" class="mt-3" style="display: none;">
                        <button class="btn btn-sm btn-outline-success mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#missingStudentsSuccessList">
                            Show missing students
                        </button>
                        <div class="collapse" id="missingStudentsSuccessList">
                            <div class="card card-body student-list text-start">
                                <ul class="mb-0" id="missingStudentsSuccessItems"></ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-success" data-bs-dismiss="modal">Continue</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Error Modal -->
    <div class="modal fade" id="errorModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content modal-error">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i>Upload Failed</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="fas fa-times-circle result-icon text-danger"></i>
                    <h4 id="errorMessage"></h4>
                    <div id="missingStudentsError" class="mt-3" style="display: none;">
                        <button class="btn btn-sm btn-outline-danger mb-2" type="button" data-bs-toggle="collapse" data-bs-target="#missingStudentsErrorList">
                            Show missing students
                        </button>
                        <div class="collapse" id="missingStudentsErrorList">
                            <div class="card card-body student-list text-start">
                                <ul class="mb-0" id="missingStudentsErrorItems"></ul>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-danger" data-bs-dismiss="modal">Try Again</button>
                </div>
            </div>
        </div>
    </div>
    <script src="js/session_timer.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('grade_file').addEventListener('change', function(e) {
            var fileName = e.target.files[0].name;
            document.getElementById('file-name').textContent = fileName;
        });

        // Handle form submission with AJAX
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const loadingModal = new bootstrap.Modal(document.getElementById('loadingModal'));
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
            
            // Show loading modal
            loadingModal.show();
            
            const xhr = new XMLHttpRequest();
            
            // Load event (when request completes)
            xhr.addEventListener('load', function() {
                loadingModal.hide();
                
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        if (response.message_type === 'success') {
                            // Show success modal
                            const sectionInfo = "Grade " + <?= $section['GradeLevel'] ?> + " - " + "<?= $section['SectionName'] ?>";
                            document.getElementById('successMessage').textContent = 
                                "Grades successfully uploaded to " + sectionInfo;
                            
                            // Show missing students if any
                            if (response.students_not_found && response.students_not_found.length > 0) {
                                document.getElementById('missingStudentsSuccess').style.display = 'block';
                                const list = document.getElementById('missingStudentsSuccessItems');
                                list.innerHTML = '';
                                response.students_not_found.forEach(student => {
                                    const li = document.createElement('li');
                                    li.textContent = student;
                                    list.appendChild(li);
                                });
                            } else {
                                document.getElementById('missingStudentsSuccess').style.display = 'none';
                            }
                            
                            successModal.show();
                        } else {
                            // Show error modal
                            document.getElementById('errorMessage').textContent = response.message;
                            
                            // Show missing students if any
                            if (response.students_not_found && response.students_not_found.length > 0) {
                                document.getElementById('missingStudentsError').style.display = 'block';
                                const list = document.getElementById('missingStudentsErrorItems');
                                list.innerHTML = '';
                                response.students_not_found.forEach(student => {
                                    const li = document.createElement('li');
                                    li.textContent = student;
                                    list.appendChild(li);
                                });
                            } else {
                                document.getElementById('missingStudentsError').style.display = 'none';
                            }
                            
                            errorModal.show();
                        }
                    } catch (e) {
                        // Show error modal for parsing errors
                        document.getElementById('errorMessage').textContent = 
                            "An error occurred while processing the response: " + e.message;
                        document.getElementById('missingStudentsError').style.display = 'none';
                        errorModal.show();
                    }
                } else {
                    // Show error modal for HTTP errors
                    document.getElementById('errorMessage').textContent = 
                        "Upload failed with status: " + xhr.status;
                    document.getElementById('missingStudentsError').style.display = 'none';
                    errorModal.show();
                }
            });
            
            // Error event
            xhr.addEventListener('error', function() {
                loadingModal.hide();
                document.getElementById('errorMessage').textContent = 
                    "Upload failed due to a network error. Please check your connection and try again.";
                document.getElementById('missingStudentsError').style.display = 'none';
                errorModal.show();
            });
            
            // Open and send the request
            xhr.open('POST', 'upload_process.php', true);
            xhr.send(formData);
        });
    </script>
</body>
</html>