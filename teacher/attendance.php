<?php
session_start();
require_once '../config.php';
date_default_timezone_set('Asia/Manila');

// 1) Ensure teacher is logged in
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// 2) Lookup TeacherID
$teacherEmail = $_SESSION['email'];
$stmt = $conn->prepare("
    SELECT t.TeacherID
      FROM teacher t
      JOIN user u ON t.UserID = u.UserID
     WHERE u.Email = ?
");
$stmt->bind_param("s", $teacherEmail);
$stmt->execute();
$stmt->bind_result($teacherID);
$stmt->fetch();
$stmt->close();

if (!$teacherID) {
    die("Teacher not found.");
}

// 3) Read date & optional sectionID
$date      = $_REQUEST['date']      ?? date('Y-m-d');
$sectionID = isset($_REQUEST['sectionID']) ? intval($_REQUEST['sectionID']) : null;
$schoolYear = $_REQUEST['school_year'] ?? '';

// 4) Fetch all sections this teacher teaches
$stmt = $conn->prepare("
    SELECT DISTINCT
        sec.SectionID,
        sec.GradeLevel,
        sec.SectionName
      FROM assigned_subject a
      JOIN section sec ON a.section_id = sec.SectionID
     WHERE a.teacher_id = ?
     ORDER BY sec.GradeLevel, sec.SectionName
");
$stmt->bind_param("i", $teacherID);
$stmt->execute();
$result = $stmt->get_result();
$sections = [];
while ($r = $result->fetch_assoc()) {
    $sections[] = $r;
}
$stmt->close();

// 5) If a section is selected, verify it belongs to this teacher
$selected = null;
if ($sectionID) {
    foreach ($sections as $sec) {
        if ($sec['SectionID'] == $sectionID) {
            $selected = $sec;
            break;
        }
    }
    if (!$selected) {
        die("Invalid section.");
    }
}

// 6) Handle AJAX attendance save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['attendance'])) {
    $saveDate   = $_POST['date'];
    $saveSectID = intval($_POST['sectionID']);
    
    // Validate section
    $validSection = false;
    foreach ($sections as $sec) {
        if ($sec['SectionID'] == $saveSectID) {
            $validSection = true;
            break;
        }
    }
    
    if (!$validSection) {
        echo json_encode([
            'status'  => 'error',
            'message' => "Invalid section selected."
        ]);
        exit;
    }
    
    foreach ($_POST['attendance'] as $lrn => $status) {
        // lookup StudentID
        $p1 = $conn->prepare("SELECT StudentID FROM student WHERE LRN = ?");
        $p1->bind_param("s", $lrn);
        $p1->execute();
        $p1->bind_result($studentID);
        $p1->fetch();
        $p1->close();

        if ($studentID) {
            // Check enrollment
            $pCheck = $conn->prepare("
                SELECT COUNT(*) 
                FROM section_enrollment 
                WHERE StudentID = ? AND SectionID = ? AND status = 'active'
            ");
            $pCheck->bind_param("ii", $studentID, $saveSectID);
            $pCheck->execute();
            $pCheck->bind_result($enrolled);
            $pCheck->fetch();
            $pCheck->close();
            
            if ($enrolled) {
                $p2 = $conn->prepare("
                    REPLACE INTO attendance
                      (StudentID, Date, Status, TeacherID, SectionID)
                    VALUES 
                      (?,       ?,    ?,      ?,         ?)
                ");
                $p2->bind_param("issii", $studentID, $saveDate, $status, $teacherID, $saveSectID);
                $p2->execute();
                $p2->close();
            }
        }
    }
    echo json_encode([
        'status'  => 'success',
        'message' => "Attendance saved for $saveDate."
    ]);
    exit;
}

// 7) Prepare data for both views
$attendanceStatus = [];
$students = [];
$attendanceRecords = []; // for main display

if ($selected) {
    // Fetch existing statuses for this section/date
    $a1 = $conn->prepare("
        SELECT s.LRN, a.Status
          FROM attendance a
          JOIN student s ON a.StudentID = s.StudentID
         WHERE a.Date      = ?
           AND a.TeacherID = ?
           AND a.SectionID = ?
    ");
    $a1->bind_param("sii", $date, $teacherID, $sectionID);
    $a1->execute();
    $res = $a1->get_result();
    while ($r = $res->fetch_assoc()) {
        $attendanceStatus[$r['LRN']] = $r['Status'];
    }
    $a1->close();

    // Fetch students in this section (for the modal form)
    $s1 = $conn->prepare("
        SELECT st.LRN, st.FirstName, st.MiddleName, st.LastName
          FROM section_enrollment se
          JOIN student st ON se.StudentID = st.StudentID
         WHERE se.SectionID = ? AND se.status = 'active'
         ORDER BY st.LastName, st.FirstName
    ");
    $s1->bind_param("i", $sectionID);
    $s1->execute();
    $res2 = $s1->get_result();
    $students = $res2->fetch_all(MYSQLI_ASSOC);
    $s1->close();

    // Fetch attendance records for main display (view mode)
    $viewStmt = $conn->prepare("
        SELECT 
            s.LRN, 
            s.FirstName, 
            s.MiddleName, 
            s.LastName,
            a.Status,
            a.Date
        FROM student s
        LEFT JOIN attendance a ON s.StudentID = a.StudentID 
            AND a.Date = ? 
            AND a.SectionID = ?
            AND a.TeacherID = ?
        JOIN section_enrollment se ON s.StudentID = se.StudentID 
            AND se.SectionID = ?
        WHERE se.status = 'active'
        ORDER BY s.LastName, s.FirstName
    ");
    $viewStmt->bind_param("siii", $date, $sectionID, $teacherID, $sectionID);
    $viewStmt->execute();
    $viewResult = $viewStmt->get_result();
    $attendanceRecords = $viewResult->fetch_all(MYSQLI_ASSOC);
    $viewStmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Attendance Tracker</title>
  <link rel="icon" href="../img/logo.png" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <!-- DataTables CSS -->
  <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.4/css/dataTables.bootstrap5.min.css">
  <!-- SweetAlert2 CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <style>
    body { background: #f0f2f5; }
    .card-container { max-width: 900px; margin: 60px auto; }
    .attendance-badge {
      font-size: 0.85rem;
      padding: 0.35em 0.65em;
    }
    .badge-present { background-color: #198754; }
    .badge-absent { background-color: #dc3545; }
    .badge-excused { background-color: #6c757d; }
    /* Attendance icon styles (for modal) */
    .attendance-icon {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all 0.3s ease;
      font-size: 1.2rem;
    }
    .attendance-icon:hover {
      transform: scale(1.1);
      box-shadow: 0 0 10px rgba(0,0,0,0.2);
    }
    .status-present {
      background-color: #d1fae5;
      color: #065f46;
      border: 2px solid #10b981;
    }
    .status-absent {
      background-color: #fee2e2;
      color: #991b1b;
      border: 2px solid #ef4444;
    }
    .status-excused {
      background-color: #e5e7eb;
      color: #374151;
      border: 2px solid #9ca3af;
    }
    .status-indicator {
      display: inline-block;
      width: 10px;
      height: 10px;
      border-radius: 50%;
      margin-right: 8px;
    }
    .indicator-present { background-color: #10b981; }
    .indicator-absent { background-color: #ef4444; }
    .indicator-excused { background-color: #9ca3af; }
    .summary-card {
      border-radius: 10px;
      border: none;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    }
    /* DataTable scrollable card */
    .dataTables_wrapper {
      margin-bottom: 1rem;
    }
    /* Remove default DataTable controls except search */
    .dataTables_paginate,
    .dataTables_info,
    .dataTables_length {
      display: none !important;
    }
    .dataTables_filter {
      margin-bottom: 1rem;
      text-align: left;
    }
    .dataTables_filter label {
      font-weight: 500;
    }
    .dataTables_filter input {
      margin-left: 0.5rem;
      border: 1px solid #ced4da;
      border-radius: 0.375rem;
      padding: 0.375rem 0.75rem;
      width: 250px;
      max-width: 100%;
    }
    .dataTables_scrollBody {
      max-height: 400px !important;
      overflow-y: auto !important;
      overflow-x: auto !important;
    }
    /* Modal body scroll */
    .modal-body {
      max-height: 70vh;
      overflow-y: auto;
    }
    /* Date picker styling */
    .date-picker-container {
      display: flex;
      align-items: center;
      gap: 10px;
    }
  </style>
</head>
<body>
  <?php include '../navs/teacherNav.php'; ?>
  <div class="container-fluid mt-4">
    <?php if (!$sectionID): ?>
      <!-- == STEP 1: Section Selection == -->
      <div class="card shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Select Class to View/Take Attendance</h5>
          <form method="get" class="d-flex">
            <input type="date" name="date" value="<?= htmlspecialchars($date) ?>" class="form-control form-control-sm me-2" required>
            <button type="submit" class="btn btn-light btn-sm">
              <i class="bi bi-arrow-clockwise"></i>
            </button>
          </form>
        </div>
        <div class="card-body">
          <?php if (empty($sections)): ?>
            <div class="alert alert-warning mb-0">You don't have any classes assigned.</div>
          <?php else: ?>
            <div class="row g-4">
              <?php foreach ($sections as $sec): ?>
                <div class="col-sm-6 col-lg-4">
                  <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                      <h5 class="card-title">
                        Grade <?= htmlspecialchars($sec['GradeLevel']) ?> -
                        <?= htmlspecialchars($sec['SectionName']) ?>
                      </h5>
                      <p class="card-text mt-auto">Date: <?= htmlspecialchars($date) ?></p>
                    </div>
                    <div class="card-footer bg-transparent d-flex justify-content-between">
                      <a href="?sectionID=<?= urlencode($sec['SectionID']) ?>&date=<?= urlencode($date) ?>&school_year=<?= urlencode($schoolYear) ?>"
                         class="btn btn-success btn-sm">
                        <i class="bi bi-check-circle"></i> View / Take Attendance
                      </a>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

    <?php else: ?>
      <!-- Back Button -->
      <div class="mb-3 d-flex justify-content-between align-items-center">
        <a href="select_class.php?date=<?= urlencode($date) ?>&school_year=<?= urlencode($schoolYear) ?>" class="btn btn-outline-secondary">
          <i class="fas fa-arrow-left"></i> Back to Classes
        </a>
        <div class="date-picker-container">
          <form method="get" class="d-inline-block">
            <input type="hidden" name="sectionID" value="<?= $sectionID ?>">
            <input type="hidden" name="school_year" value="<?= htmlspecialchars($schoolYear) ?>">
            <input type="date" name="date" value="<?= htmlspecialchars($date) ?>" class="form-control form-control-sm" onchange="this.form.submit()">
          </form>
          <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#takeAttendanceModal">
            <i class="bi bi-pencil-square"></i> Take Attendance
          </button>
        </div>
      </div>

      <!-- Status Legend -->
      <div class="row mb-3">
        <div class="col-md-12">
          <div class="card summary-card">
            <div class="card-body">
              <h5 class="card-title">
                Attendance for Grade <?= htmlspecialchars($selected['GradeLevel']) ?> - <?= htmlspecialchars($selected['SectionName']) ?>
                <small class="text-muted">(<?= htmlspecialchars($date) ?>)</small>
              </h5>
              <div class="row text-center mt-3">
                <div class="col-md-4">
                  <span class="status-indicator indicator-present"></span> <strong>Present</strong>
                </div>
                <div class="col-md-4">
                  <span class="status-indicator indicator-absent"></span> <strong>Absent</strong>
                </div>
                <div class="col-md-4">
                  <span class="status-indicator indicator-excused"></span> <strong>Excused</strong>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Main View: Attendance Records Table (Read‑Only) -->
      <div class="card">
        <div class="card-body p-0">
          <?php if (empty($attendanceRecords)): ?>
            <div class="alert alert-info m-3">No attendance records found for this date.</div>
          <?php else: ?>
            <table id="attendance-view-table" class="table table-hover align-middle mb-0">
              <thead class="table-light">
                <tr>
                  <th>LRN</th>
                  <th>Student Name</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($attendanceRecords as $record):
                  $status = $record['Status'];
                  $name = trim("{$record['LastName']}, {$record['FirstName']} " . ($record['MiddleName'] ? "{$record['MiddleName']}" : ""));
                  if ($status === 'present') {
                    $badgeClass = 'badge-present';
                    $statusText = '<i class="fas fa-check-circle me-1"></i> Present';
                  } elseif ($status === 'absent') {
                    $badgeClass = 'badge-absent';
                    $statusText = '<i class="fas fa-times-circle me-1"></i> Absent';
                  } elseif ($status === 'excused') {
                    $badgeClass = 'badge-excused';
                    $statusText = '<i class="fas fa-minus-circle me-1"></i> Excused';
                  } else {
                    $badgeClass = 'bg-secondary';
                    $statusText = '<i class="fas fa-question-circle me-1"></i> No Record';
                  }
                ?>
                  <tr>
                    <td><?= htmlspecialchars($record['LRN']) ?></td>
                    <td><?= htmlspecialchars($name) ?></td>
                    <td><span class="badge attendance-badge <?= $badgeClass ?>"><?= $statusText ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>
      </div>

      <!-- Modal: Take Attendance (Interactive Form) -->
      <div class="modal fade" id="takeAttendanceModal" tabindex="-1" aria-labelledby="takeAttendanceModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title" id="takeAttendanceModalLabel">
                Take Attendance – Grade <?= htmlspecialchars($selected['GradeLevel']) ?> - <?= htmlspecialchars($selected['SectionName']) ?>
                (<?= htmlspecialchars($date) ?>)
              </h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <form id="attendance-form">
                <input type="hidden" name="date" value="<?= htmlspecialchars($date) ?>">
                <input type="hidden" name="sectionID" value="<?= htmlspecialchars($sectionID) ?>">
                <?php if (empty($students)): ?>
                  <div class="alert alert-warning">No students enrolled in this section.</div>
                <?php else: ?>
                  <table id="attendance-modal-table" class="table table-hover align-middle">
                    <thead class="table-light">
                      <tr>
                        <th>LRN</th>
                        <th>Student Name</th>
                        <th>Attendance Status</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($students as $st):
                        $lrn    = $st['LRN'];
                        $name   = trim("{$st['LastName']}, {$st['FirstName']} " . ($st['MiddleName'] ? "{$st['MiddleName']}" : ""));
                        $status = $attendanceStatus[$lrn] ?? 'present';
                        
                        $statusClasses = [
                          'present' => 'status-present',
                          'absent'  => 'status-absent',
                          'excused' => 'status-excused'
                        ];
                        $statusIcons = [
                          'present' => '<i class="fas fa-check"></i>',
                          'absent'  => '<i class="fas fa-times"></i>',
                          'excused' => '<i class="fas fa-minus"></i>'
                        ];
                      ?>
                        <tr>
                          <td><?= htmlspecialchars($lrn) ?></td>
                          <td><?= htmlspecialchars($name) ?></td>
                          <td>
                            <div class="d-flex align-items-center">
                              <input type="hidden" name="attendance[<?= htmlspecialchars($lrn) ?>]" value="<?= htmlspecialchars($status) ?>" id="status-input-<?= $lrn ?>">
                              <div class="attendance-icon <?= $statusClasses[$status] ?>"
                                   data-lrn="<?= htmlspecialchars($lrn) ?>"
                                   data-status="<?= htmlspecialchars($status) ?>"
                                   onclick="cycleStatus(this)">
                                <?= $statusIcons[$status] ?>
                              </div>
                              <div class="ms-3 status-text" id="status-text-<?= $lrn ?>">
                                <span class="badge <?= ($status == 'present') ? 'bg-success' : (($status == 'absent') ? 'bg-danger' : 'bg-secondary') ?>">
                                  <?= ucfirst($status) ?>
                                </span>
                              </div>
                            </div>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                <?php endif; ?>
              </form>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
              <button type="button" class="btn btn-success" id="save-attendance-modal">
                <i class="bi bi-save"></i> Save Attendance
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Scripts -->
      <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
      <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
      <script src="https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js"></script>
      <script src="https://cdn.datatables.net/1.13.4/js/dataTables.bootstrap5.min.js"></script>
      <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
      <script>
        // Status cycling (used in modal)
        const statusCycle = ['present', 'absent', 'excused'];
        function cycleStatus(element) {
          const lrn = element.getAttribute('data-lrn');
          const currentStatus = element.getAttribute('data-status');
          const currentIndex = statusCycle.indexOf(currentStatus);
          const nextIndex = (currentIndex + 1) % statusCycle.length;
          const nextStatus = statusCycle[nextIndex];
          
          element.setAttribute('data-status', nextStatus);
          element.setAttribute('class', `attendance-icon status-${nextStatus}`);
          const icons = {
            'present': '<i class="fas fa-check"></i>',
            'absent': '<i class="fas fa-times"></i>',
            'excused': '<i class="fas fa-minus"></i>'
          };
          element.innerHTML = icons[nextStatus];
          document.getElementById(`status-input-${lrn}`).value = nextStatus;
          
          const badgeClass = {
            'present': 'bg-success',
            'absent': 'bg-danger',
            'excused': 'bg-secondary'
          };
          const statusText = document.getElementById(`status-text-${lrn}`);
          statusText.innerHTML = `<span class="badge ${badgeClass[nextStatus]}">${nextStatus.charAt(0).toUpperCase() + nextStatus.slice(1)}</span>`;
        }

        // Initialize DataTables
        document.addEventListener('DOMContentLoaded', function() {
          if ($('#attendance-view-table').length) {
            $('#attendance-view-table').DataTable({
              paging: false,
              scrollY: '400px',
              scrollCollapse: true,
              searching: true,
              info: false,
              ordering: true,
              language: { emptyTable: "No records found", search: "Search students:" }
            });
          }
          // Initialize or adjust DataTable for the modal when opened so search works
          $('#takeAttendanceModal').on('shown.bs.modal', function() {
            if (!$.fn.DataTable.isDataTable('#attendance-modal-table')) {
              $('#attendance-modal-table').DataTable({
                paging: false,
                searching: true,
                info: false,
                ordering: false,
                scrollY: '50vh',
                scrollCollapse: true,
                language: { search: "Search students:" }
              });
            } else {
              $('#attendance-modal-table').DataTable().columns.adjust().draw();
            }
          });
        });

        // Save attendance from modal
        $(function(){
          $('#save-attendance-modal').click(function(){
            Swal.fire({
              title: 'Are you sure?',
              text: "Do you want to save the attendance for this class?",
              icon: 'question',
              showCancelButton: true,
              confirmButtonColor: '#28a745',
              cancelButtonColor: '#6c757d',
              confirmButtonText: 'Yes, save it!'
            }).then((result) => {
              if (result.isConfirmed) {
                Swal.fire({ title: 'Saving...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
                $.post('', $('#attendance-form').serialize())
                  .done(function(res){
                    const j = JSON.parse(res);
                    if (j.status === 'success') {
                      Swal.fire({ icon: 'success', title: 'Success!', text: j.message, timer: 2000, showConfirmButton: false })
                        .then(() => {
                          // Close modal and reload page to reflect changes
                          $('#takeAttendanceModal').modal('hide');
                          location.reload();
                        });
                    } else {
                      Swal.fire({ icon: 'error', title: 'Error', text: j.message });
                    }
                  })
                  .fail(() => Swal.fire({ icon: 'error', title: 'Error', text: 'Error submitting attendance.' }));
              }
            });
          });

          // Keyboard shortcut Ctrl+S to open modal (if on the view page)
          $(document).keydown(function(e) {
            if (e.key === 's' && (e.ctrlKey || e.metaKey)) {
              e.preventDefault();
              $('#takeAttendanceModal').modal('show');
            }
          });
        });
      </script>
    <?php endif; ?>
  </div>
</body>
</html>