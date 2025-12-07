<?php
session_start();
include '../config.php';

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] == 'get_teacher_subjects') {
        echo getTeacherSubjects($conn);
        exit;
    }
    
    if ($_GET['action'] == 'get_teacher_subject_details') {
        $teacherId = isset($_GET['teacher_id']) ? intval($_GET['teacher_id']) : 0;
        echo getTeacherSubjectDetails($conn, $teacherId);
        exit;
    }
}

function getTeacherSubjects($conn) {
    $sql = "SELECT 
                t.TeacherID,
                t.fName, t.mName, t.lName, t.surfix, t.gender, t.contact,
                u.Email
             FROM teacher t
             JOIN user u ON t.UserID = u.UserID
             WHERE t.status = 'Active'
             ORDER BY t.lName, t.fName";

    $res = $conn->query($sql);
    $html = '';

    if ($res && $res->num_rows > 0) {
        $html .= '<table id="teacherSubjectsTable" class="table table-bordered table-hover align-middle">
                    <thead class="table-info">
                        <tr>
                            <th>Teacher Name</th>
                            <th>Email</th>
                            <th>Contact</th>
                            <th>Assigned Subjects</th>
                            <th>Sections</th>
                            <th>School Year</th>
                            <th>Student Grades Status</th>
                        </tr>
                    </thead>
                    <tbody>';
        
        $i = 1;
        while ($r = $res->fetch_assoc()) {
            $fullName = htmlspecialchars($r['fName'].' '.$r['mName'].' '.$r['lName'].($r['surfix'] ? ' '.$r['surfix'] : ''));
            $email = htmlspecialchars($r['Email']);
            $contact = htmlspecialchars($r['contact'] ?: 'N/A');
            
            // Get assigned subjects and sections for this teacher
            $assignedData = getTeacherAssignedData($conn, $r['TeacherID']);
            $subjects = $assignedData['subjects'];
            $sections = $assignedData['sections'];
            $schoolYear = $assignedData['school_year'];
            
            // Get student grades status for this teacher
            $gradesStatus = getStudentGradesStatus($conn, $r['TeacherID'], $schoolYear);
            
            $html .= "<tr class='clickable-row' 
                        data-teacher-id='{$r['TeacherID']}'
                        data-teacher-name='{$fullName}'
                        data-email='{$email}'
                        data-contact='{$contact}'
                        data-subjects='{$subjects}'
                        data-sections='{$sections}'
                        data-school-year='{$schoolYear}'>
                        <td>{$fullName}</td>
                        <td>{$email}</td>
                        <td>{$contact}</td>
                        <td>{$subjects}</td>
                        <td>{$sections}</td>
                        <td>{$schoolYear}</td>
                        <td>{$gradesStatus}</td>
                      </tr>";
            $i++;
        }
        
        $html .= '</tbody></table>';
    } else {
        $html = '<div class="alert alert-info">No active teachers found.</div>';
    }
    
    return $html;
}

function getTeacherAssignedData($conn, $teacherId) {
    $sql = "SELECT 
                sub.SubjectName,
                s.SectionName,
                s.GradeLevel,
                a.school_year
             FROM assigned_subject a
             JOIN subject sub ON a.subject_id = sub.SubjectID
             JOIN section s ON a.section_id = s.SectionID
             WHERE a.teacher_id = ?
             ORDER BY sub.SubjectName";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacherId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $subjects = [];
    $sections = [];
    $schoolYears = [];
    
    while ($row = $result->fetch_assoc()) {
        $subjects[] = $row['SubjectName'];
        $sections[] = "Grade " . $row['GradeLevel'] . " - " . $row['SectionName'];
        $schoolYears[] = $row['school_year'];
    }
    
    // If no subjects assigned
    if (empty($subjects)) {
        return [
            'subjects' => '<span class="text-muted">No subjects assigned</span>',
            'sections' => '<span class="text-muted">No sections assigned</span>',
            'school_year' => '<span class="text-muted">N/A</span>'
        ];
    }
    
    // Remove duplicates and format
    $subjects = array_unique($subjects);
    $sections = array_unique($sections);
    $schoolYears = array_unique($schoolYears);
    
    return [
        'subjects' => implode(', ', $subjects),
        'sections' => implode(', ', $sections),
        'school_year' => implode(', ', $schoolYears)
    ];
}

function getStudentGradesStatus($conn, $teacherId, $schoolYear) {
    // Get all subjects assigned to this teacher
    $sql = "SELECT sub.SubjectID, sub.SubjectName 
            FROM assigned_subject a 
            JOIN subject sub ON a.subject_id = sub.SubjectID 
            WHERE a.teacher_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacherId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // If no subjects assigned
    if ($result->num_rows === 0) {
        return "<span class='badge bg-light text-dark'>No subjects assigned</span>";
    }
    
    $statusSummary = [];
    
    while ($subject = $result->fetch_assoc()) {
        $subjectStatus = getSubjectGradeStatus($conn, $teacherId, $subject['SubjectID'], $schoolYear);
        $statusSummary[] = [
            'subject' => $subject['SubjectName'],
            'status' => $subjectStatus
        ];
    }
    
    // Count status types
    $uploadCount = 0;
    $systemCount = 0;
    $pendingCount = 0;
    
    foreach ($statusSummary as $item) {
        if ($item['status'] === 'upload') $uploadCount++;
        elseif ($item['status'] === 'system') $systemCount++;
        else $pendingCount++;
    }
    
    // Create status summary
    $totalSubjects = count($statusSummary);
    
    if ($uploadCount > 0 && $systemCount > 0) {
        return "<span class='badge bg-info'>Mixed: {$uploadCount} uploaded, {$systemCount} system</span>";
    } elseif ($uploadCount > 0) {
        return "<span class='badge bg-success'>{$uploadCount}/{$totalSubjects} uploaded</span>";
    } elseif ($systemCount > 0) {
        return "<span class='badge bg-warning'>{$systemCount}/{$totalSubjects} system input</span>";
    } else {
        return "<span class='badge bg-secondary'>Pending: {$pendingCount}/{$totalSubjects}</span>";
    }
}

function getSubjectGradeStatus($conn, $teacherId, $subjectId, $schoolYear) {
    // Check if teacher has any grade upload logs for this subject
    $uploadSql = "SELECT COUNT(*) as count FROM system_logs 
                  WHERE (details LIKE ? OR details LIKE ?)
                  AND action LIKE '%Grade Upload%'
                  AND log_level = 'success'";
    $stmt = $conn->prepare($uploadSql);
    $teacherPattern = "%\"teacherID\":{$teacherId}%";
    $subjectPattern = "%\"subject_id\":{$subjectId}%";
    $stmt->bind_param("ss", $teacherPattern, $subjectPattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $uploadData = $result->fetch_assoc();
    
    if ($uploadData['count'] > 0) {
        return 'upload';
    }
    
    // Check if teacher has manual grade entries for this subject
    $manualSql = "SELECT COUNT(*) as count FROM grades_details 
                  WHERE teacherID = ? AND subjectID = ? AND school_year = ?";
    $stmt = $conn->prepare($manualSql);
    $stmt->bind_param("iis", $teacherId, $subjectId, $schoolYear);
    $stmt->execute();
    $result = $stmt->get_result();
    $manualData = $result->fetch_assoc();
    
    if ($manualData['count'] > 0) {
        return 'system';
    }
    
    return 'pending';
}

function getTeacherSubjectDetails($conn, $teacherId) {
    if ($teacherId <= 0) {
        return json_encode(['error' => 'Invalid parameters']);
    }

    // Get teacher details
    $sql = "SELECT 
                t.TeacherID,
                CONCAT(t.fName, ' ', t.mName, ' ', t.lName, 
                       CASE WHEN t.surfix IS NOT NULL AND t.surfix != '' THEN CONCAT(' ', t.surfix) ELSE '' END) as fullName,
                t.gender, t.contact, t.status,
                u.Email
             FROM teacher t
             JOIN user u ON t.UserID = u.UserID
             WHERE t.TeacherID = ?
             LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $teacherId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        return json_encode(['error' => 'Teacher not found']);
    }

    $teacherData = $result->fetch_assoc();

    // Get assigned subjects with grade status
    $subjectsSql = "SELECT 
                    sub.SubjectID,
                    sub.SubjectName,
                    s.SectionName,
                    s.GradeLevel,
                    a.school_year
                 FROM assigned_subject a
                 JOIN subject sub ON a.subject_id = sub.SubjectID
                 JOIN section s ON a.section_id = s.SectionID
                 WHERE a.teacher_id = ?";
    
    $stmt = $conn->prepare($subjectsSql);
    $stmt->bind_param("i", $teacherId);
    $stmt->execute();
    $subjectsResult = $stmt->get_result();
    
    $subjects = [];
    while ($subject = $subjectsResult->fetch_assoc()) {
        $gradeStatus = getDetailedSubjectStatus($conn, $teacherId, $subject['SubjectID'], $subject['school_year']);
        $subjects[] = [
            'subjectName' => $subject['SubjectName'],
            'section' => "Grade " . $subject['GradeLevel'] . " - " . $subject['SectionName'],
            'schoolYear' => $subject['school_year'],
            'gradeStatus' => $gradeStatus
        ];
    }

    $response = [
        'teacher' => [
            'fullName' => $teacherData['fullName'],
            'email' => $teacherData['Email'],
            'gender' => $teacherData['gender'],
            'contact' => $teacherData['contact'],
            'status' => $teacherData['status']
        ],
        'subjects' => $subjects
    ];

    return json_encode($response);
}

function getDetailedSubjectStatus($conn, $teacherId, $subjectId, $schoolYear) {
    $status = getSubjectGradeStatus($conn, $teacherId, $subjectId, $schoolYear);
    
    // Get additional details based on status
    switch ($status) {
        case 'upload':
            return [
                'status' => 'upload',
                'label' => 'Excel Upload',
                'badge' => 'bg-success',
                'details' => getUploadDetails($conn, $teacherId, $subjectId)
            ];
        case 'system':
            return [
                'status' => 'system',
                'label' => 'System Input',
                'badge' => 'bg-warning',
                'details' => getSystemInputDetails($conn, $teacherId, $subjectId, $schoolYear)
            ];
        default:
            return [
                'status' => 'pending',
                'label' => 'Pending',
                'badge' => 'bg-secondary',
                'details' => 'No grades entered yet'
            ];
    }
}

function getUploadDetails($conn, $teacherId, $subjectId) {
    $sql = "SELECT action, details, created_at 
            FROM system_logs 
            WHERE (details LIKE ? OR details LIKE ?)
            AND action LIKE '%Grade Upload%'
            AND log_level = 'success'
            ORDER BY created_at DESC 
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $teacherPattern = "%\"teacherID\":{$teacherId}%";
    $subjectPattern = "%\"subject_id\":{$subjectId}%";
    $stmt->bind_param("ss", $teacherPattern, $subjectPattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $log = $result->fetch_assoc();
        $details = json_decode($log['details'], true);
        $date = date('M j, Y g:i A', strtotime($log['created_at']));
        
        if (isset($details['file_name'])) {
            return "Uploaded via: " . $details['file_name'] . " on " . $date;
        }
        
        return "Last upload: " . $date;
    }
    
    return "Uploaded via Excel";
}

function getSystemInputDetails($conn, $teacherId, $subjectId, $schoolYear) {
    $sql = "SELECT COUNT(DISTINCT quarter) as quarters, 
                   MAX(uploaded) as last_updated
            FROM grades_details 
            WHERE teacherID = ? AND subjectID = ? AND school_year = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iis", $teacherId, $subjectId, $schoolYear);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    
    $quarters = $data['quarters'] ?? 0;
    $lastUpdated = $data['last_updated'] ? date('M j, Y g:i A', strtotime($data['last_updated'])) : 'Unknown';
    
    return "{$quarters}/4 quarters entered manually. Last update: {$lastUpdated}";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>BANAHIS | Teacher Subject List</title>
  <link rel="icon" href="../img/logo.png" type="image/png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <style>
    .clickable-row {
      cursor: pointer;
    }
    .clickable-row:hover {
      background-color: #f8f9fa;
    }
    .subject-status-card {
      border-left: 4px solid #6c757d;
    }
    .subject-status-card.upload {
      border-left-color: #198754;
    }
    .subject-status-card.system {
      border-left-color: #ffc107;
    }
    .subject-status-card.pending {
      border-left-color: #6c757d;
    }
    .no-subjects-card {
      border-left: 4px solid #6c757d;
      background-color: #f8f9fa;
    }
  </style>
</head>
<body>
  <?php include '../navs/adminNav.php'; ?>
  <div class="container-fluid mt-5">
        <!-- Welcome Header with School Year Badge -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow position-relative">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <h4 class="card-title mb-1">TEACHER LIST
                            </h4>
                            <p class="card-text mb-0">List of Teachers and Other Details</p>
                        </div>
                        <div class="col-auto">
                            <span class="badge bg-light text-secondary fs-6"><?php echo date('l, F j, Y'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php if (!empty($_SESSION['message'])): ?>
      <div class="alert alert-success"><?= $_SESSION['message']; unset($_SESSION['message']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
      <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
      <div>
        <span class="badge bg-primary">Total Active Teachers: 
          <?php 
          $count_sql = "SELECT COUNT(*) as total FROM teacher WHERE status = 'Active'";
          $count_result = $conn->query($count_sql);
          $count_data = $count_result->fetch_assoc();
          echo $count_data['total'];
          ?>
        </span>
      </div>
    </div>

    <div id="loadingSpinner" class="text-center d-none">
      <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Loading...</span>
      </div>
      <p class="mt-2">Loading data...</p>
    </div>

    <div id="teacherSubjectsContainer">
      <!-- Initial content will be loaded via AJAX on page load -->
    </div>
  </div>

  <!-- View Details Modal -->
  <div class="modal fade" id="viewModal" tabindex="-1" aria-labelledby="viewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="viewModalLabel">Teacher Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <h6>Teacher Information</h6>
              <p><strong>Name:</strong> <span id="detailFullName"></span></p>
              <p><strong>Email:</strong> <span id="detailEmail"></span></p>
              <p><strong>Gender:</strong> <span id="detailGender"></span></p>
              <p><strong>Status:</strong> <span id="detailStatus"></span></p>
              <p><strong>Contact:</strong> <span id="detailPhoneNumber"></span></p>
            </div>
            <div class="col-md-6">
              <h6>Assigned Subjects & Grade Status</h6>
              <div id="subjectsList">
                <!-- Subjects will be populated here -->
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

  <script>
    $(document).ready(() => {
      // Load initial data
      loadTeacherSubjects();

      function loadTeacherSubjects() {
        $('#loadingSpinner').removeClass('d-none');
        $('#teacherSubjectsContainer').html('');

        $.ajax({
          url: '<?php echo basename(__FILE__); ?>',
          type: 'GET',
          data: { 
            action: 'get_teacher_subjects'
          },
          dataType: 'html',
          success: function(data) {
            $('#teacherSubjectsContainer').html(data);
            initializeDataTable();
            attachRowClickEvents();
          },
          error: function() {
            $('#teacherSubjectsContainer').html('<div class="alert alert-danger">Error loading data. Please try again.</div>');
          },
          complete: function() {
            $('#loadingSpinner').addClass('d-none');
          }
        });
      }

      function initializeDataTable() {
        $('#teacherSubjectsTable').DataTable({
          "pageLength": 25,
          "responsive": true,
          "order": [[1, 'asc']] // Sort by teacher name
        });
      }

      function attachRowClickEvents() {
        $('.clickable-row').off('click').on('click', function() {
          const teacherId = $(this).data('teacher-id');
          
          // Show loading in modal
          $('#subjectsList').html('<div class="text-center"><div class="spinner-border spinner-border-sm"></div> Loading teacher details...</div>');
          
          $.ajax({
            url: '<?php echo basename(__FILE__); ?>',
            type: 'GET',
            data: { 
              action: 'get_teacher_subject_details',
              teacher_id: teacherId
            },
            dataType: 'json',
            success: function(data) {
              if (data.error) {
                $('#subjectsList').html('<div class="alert alert-danger">' + data.error + '</div>');
                return;
              }
              
              // Populate teacher information
              $('#detailFullName').text(data.teacher.fullName);
              $('#detailEmail').text(data.teacher.email);
              $('#detailGender').text(data.teacher.gender || 'N/A');
              $('#detailStatus').text(data.teacher.status);
              $('#detailPhoneNumber').text(data.teacher.contact || 'N/A');
              
              // Populate subjects list
              let subjectsHtml = '';
              if (data.subjects && data.subjects.length > 0) {
                data.subjects.forEach(subject => {
                  subjectsHtml += `
                    <div class="card subject-status-card ${subject.gradeStatus.status} mb-2">
                      <div class="card-body p-3">
                        <div class="d-flex justify-content-between align-items-start">
                          <div>
                            <h6 class="card-title mb-1">${subject.subjectName}</h6>
                            <p class="card-text mb-1"><small>${subject.section}</small></p>
                            <p class="card-text mb-0"><small class="text-muted">${subject.schoolYear}</small></p>
                          </div>
                          <span class="badge ${subject.gradeStatus.badge}">${subject.gradeStatus.label}</span>
                        </div>
                        <div class="mt-2">
                          <small class="text-muted">${subject.gradeStatus.details}</small>
                        </div>
                      </div>
                    </div>
                  `;
                });
              } else {
                subjectsHtml = `
                  <div class="card no-subjects-card">
                    <div class="card-body text-center p-4">
                      <h6 class="card-title text-muted">No Subjects Assigned</h6>
                      <p class="card-text text-muted mb-0">This teacher doesn't have any assigned subjects for the current school year.</p>
                    </div>
                  </div>
                `;
              }
              
              $('#subjectsList').html(subjectsHtml);
              $('#viewModal').modal('show');
            },
            error: function() {
              $('#subjectsList').html('<div class="alert alert-danger">Error loading teacher details.</div>');
            }
          });
        });
      }
    });
  </script>
</body>
</html>