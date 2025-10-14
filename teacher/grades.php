<?php
// File: upload.php

session_start();
require '../config.php';

// 1) Ensure teacher is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

                                    // Fetch teacher's name


// Get teacher_id from session or database
if (!isset($_SESSION['teacher_id'])) {
    // Try to get teacher_id from database based on user_id
    $user_id = $_SESSION['user_id'];
    $teacher_query = $conn->prepare("SELECT teacherID FROM teacher WHERE userID = ?");
    $teacher_query->bind_param('i', $user_id);
    $teacher_query->execute();
    $teacher_result = $teacher_query->get_result();
    
    if ($teacher_result->num_rows) {
        $_SESSION['teacher_id'] = $teacher_result->fetch_assoc()['teacherID'];
        $teacher_id = $_SESSION['teacher_id'];
    } else {
        // Handle error - user is not a teacher
        echo "Error: User is not registered as a teacher.";
        exit;
    }
} else {
    $teacher_id = $_SESSION['teacher_id'];
}

$teacherName = '';
$stmt = $conn->prepare("SELECT CONCAT(fName, ' ', lName) AS FullName FROM teacher WHERE teacherID = ?");
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

// 3) Fetch subject name and validate teacher is assigned to this subject
$stmt = $conn->prepare("SELECT SubjectName FROM subject WHERE SubjectID = ? AND teacherID = ?");
$stmt->bind_param('ii', $subjectId, $teacher_id);
$stmt->execute();
$res = $stmt->get_result();
if (!$res->num_rows) {
    echo "You are not assigned to teach this subject or the subject does not exist."; 
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

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Grades</title>
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
            border: 2px dashed #d1d3e2;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
            transition: all 0.3s;
            background-color: #f8f9fc;
            max-height: 200px;
        }
        
        .upload-area:hover {
            border-color: var(--primary);
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
        
        .progress {
            height: 20px;
            margin-bottom: 20px;
            overflow: hidden;
            background-color: #f5f5f5;
            border-radius: 4px;
            box-shadow: inset 0 1px 2px rgba(0,0,0,.1);
        }
        
        .progress-bar {
            float: left;
            width: 0%;
            height: 100%;
            font-size: 12px;
            line-height: 20px;
            color: #fff;
            text-align: center;
            background-color: var(--primary);
            transition: width .6s ease;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            max-width: 700px;
            margin: auto;
        }

        .form-select:focus, .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
        }
        
        .instructions {
            background: linear-gradient(to right, #f8f9fc 0%, #eaecf4 100%);
        }
    </style>
</head>
<body>
    <?php include '../navs/teacherNav.php'; ?>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                <div class="card shadow-lg">
                    <div class="card-header py-3 bg-info text-white">
                        <h3 class="text-center mb-0"><i class="fas fa-file-upload me-2"></i>Upload Grade File</h3>
                    </div>
                    <div class="card-body">
                        
                        <div class="instructions mb-4">
                            <h5><i class="fas fa-info-circle me-2"></i>Instructions</h5>
                            <p class="mb-0">
                                - Ensure your Excel file follows the correct format.<br>
                                - Select the appropriate quarter for the grades you're uploading.<br>
                                - Only .xlsx and .xls file formats are supported.<br>
                                - The system will automatically validate and process the grades after upload.
                            </p>
                        </div>
                        

                        <form id="uploadForm" enctype="multipart/form-data">
                         <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
                         <input type="hidden" name="section_id" value="<?= $sectionId ?>">
                            <div class="row mb-4">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <h4 class="text-secondary">Welcome, Teacher: </h4>
                                        <h3 class="text-secondary"><strong><?= htmlspecialchars($teacherName) ?></strong></h3>
                                    </div>
                                    <select name="quarter" class="form-control" required>
                                        <option value="1">Quarter 1</option>
                                        <option value="2">Quarter 2</option>
                                        <option value="3">Quarter 3</option>
                                        <option value="4">Quarter 4</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <div class="mt-2">
                                        <h5 class="text-secondary">
                                            Subject: <strong><?= htmlspecialchars($subjectName) ?></strong>
                                            | Section: Grade <?= $section['GradeLevel'] ?>-<?= htmlspecialchars($section['SectionName']) ?>
                                        </h5>
                                        <div class="py-4 text-center">
                                            <a href="view_grades.php?subject_id=<?= $subjectId ?>&section_id=<?= $sectionId ?>" class="btn btn-outline-info">View Grades</a>
                                            <a href="grading_sheet.php" class="btn btn-outline-secondary">Back</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                        
                        <div id="message-container"></div>
                            <div class="mb-4">
                                <label for="grade_file" class="form-label">Upload Grade File:</label>
                                <div class="upload-area p-3 mb-3">
                                    <input type="file" class="form-control d-none" id="grade_file" name="grade_file" accept=".xlsx,.xls" required>
                                    <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('grade_file').click()">
                                        <i class="fas fa-file-excel me-1"></i> Browse Excel Files
                                    </button>
                                    <div class="mt-2" id="file-name">No file chosen</div>
                                </div>
                                <div class="form-text">Supported formats: .xlsx, .xls (Max size: 10MB)</div>
                            </div>
                            
                            <div class="progress d-none" id="progress-container">
                                <div class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-upload me-2"></i> Upload and Process
                                </button>

                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

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
            const progressContainer = document.getElementById('progress-container');
            const progressBar = progressContainer.querySelector('.progress-bar');
            const messageContainer = document.getElementById('message-container');
            
            // Show progress bar
            progressContainer.classList.remove('d-none');
            progressBar.style.width = '0%';
            progressBar.textContent = '0%';
            
            // Clear previous messages
            messageContainer.innerHTML = '';
            
            const xhr = new XMLHttpRequest();
            
            // Progress event
            xhr.upload.addEventListener('progress', function(e) {
                if (e.lengthComputable) {
                    const percentComplete = (e.loaded / e.total) * 100;
                    progressBar.style.width = percentComplete + '%';
                    progressBar.textContent = Math.round(percentComplete) + '%';
                }
            });
            
            // Load event (when request completes)
            xhr.addEventListener('load', function() {
                if (xhr.status === 200) {
                    try {
                        const response = JSON.parse(xhr.responseText);
                        
                        // Create alert message
                        const alertDiv = document.createElement('div');
                        alertDiv.className = `alert alert-${response.message_type} alert-dismissible fade show`;
                        alertDiv.innerHTML = ` 
                            <strong>${response.message_type.charAt(0).toUpperCase() + response.message_type.slice(1)}!</strong> 
                            ${response.message}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        `;
                        
                        // Add missing students list if any
                        if (response.students_not_found && response.students_not_found.length > 0) {
                            const missingStudentsDiv = document.createElement('div');
                            missingStudentsDiv.className = 'mt-2';
                            missingStudentsDiv.innerHTML = `
                                <button class="btn btn-sm btn-outline-${response.message_type}" type="button" data-bs-toggle="collapse" data-bs-target="#missingStudents">
                                    Show missing students (${response.students_not_found.length})
                                </button>
                                <div class="collapse mt-2" id="missingStudents">
                                    <div class="card card-body student-list">
                                        <ul class="mb-0">
                                            ${response.students_not_found.map(student => `<li>${student}</li>`).join('')}
                                        </ul>
                                    </div>
                                </div>
                            `;
                            alertDiv.appendChild(missingStudentsDiv);
                        }
                        
                        messageContainer.appendChild(alertDiv);
                    } catch (e) {
                        messageContainer.innerHTML = `
                            <div class="alert alert-danger alert-dismissible fade show">
                                <strong>Error!</strong> ${xhr.responseText}
                                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                            </div>
                        `;
                    }
                } else {
                    messageContainer.innerHTML = `
                        <div class="alert alert-danger alert-dismissible fade show">
                            <strong>Error!</strong> Upload failed with status: ${xhr.status}.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    `;
                }
                
                // Hide progress bar
                setTimeout(() => {
                    progressContainer.classList.add('d-none');
                }, 1000);
            });
            
            // Error event
            xhr.addEventListener('error', function() {
                messageContainer.innerHTML = `
                    <div class="alert alert-danger alert-dismissible fade show">
                        <strong>Error!</strong> Upload failed due to a network error.
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                `;
                progressContainer.classList.add('d-none');
            });
            
            // Open and send the request
            xhr.open('POST', 'upload_process.php', true);
            xhr.send(formData);
        });
    </script>
</body>
</html>