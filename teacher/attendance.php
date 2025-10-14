<?php
session_start();
require_once '../config.php';

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
$viewMode  = isset($_REQUEST['view']) && $_REQUEST['view'] === 'true';

// 4) Fetch all sections this teacher teaches
$stmt = $conn->prepare("
    SELECT DISTINCT
        sec.SectionID,
        sec.GradeLevel,
        sec.SectionName
      FROM sched sch
      JOIN section sec ON sch.SectionID = sec.SectionID
     WHERE sch.TeacherID = ?
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
        if ($sec['SectionID'] === $sectionID) {
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
    foreach ($_POST['attendance'] as $lrn => $status) {
        // lookup StudentID
        $p1 = $conn->prepare("SELECT StudentID FROM student WHERE LRN = ?");
        $p1->bind_param("s", $lrn);
        $p1->execute();
        $p1->bind_result($studentID);
        $p1->fetch();
        $p1->close();

        if ($studentID) {
            // now include sectionID
            $p2 = $conn->prepare("
                REPLACE INTO attendance
                  (StudentID, Date, Status, TeacherID, SectionID)
                VALUES 
                  (?,       ?,    ?,      ?,         ?)
            ");
            $p2->bind_param(
              "issii",
              $studentID,
              $saveDate,
              $status,
              $teacherID,
              $saveSectID
            );
            $p2->execute();
            $p2->close();
        }
    }
    echo json_encode([
        'status'  => 'success',
        'message' => "Attendance saved for $saveDate."
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Attendance Tracker</title>
  <link rel="icon" href="../img/logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
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
    .view-mode-table th, .view-mode-table td {
      padding: 0.5rem;
      font-size: 0.9rem;
    }
    .nav-tabs .nav-link.active {
      font-weight: 600;
    }
    .back-button {
      margin-right: 15px;
    }
  </style>
</head>
<body>
  <?php include '../navs/teacherNav.php'; ?>
  <div class="container mt-4">

    <?php if (!$sectionID): ?>
      <!-- == STEP 1: Section Selection == -->
      <div class="card card-container shadow-sm">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
          <h5 class="mb-0">Select Class to Take Attendance</h5>
          <form method="get" class="d-flex">
            <input 
              type="date" 
              name="date" 
              value="<?= htmlspecialchars($date) ?>" 
              class="form-control form-control-sm me-2" 
              required>
            <button type="submit" class="btn btn-light btn-sm">
              <i class="bi bi-arrow-clockwise"></i>
            </button>
          </form>
        </div>
        <div class="card-body">
          <?php if (empty($sections)): ?>
            <div class="alert alert-warning mb-0">
              You don't have any scheduled classes.
            </div>
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
                      <p class="card-text mt-auto">
                        Date: <?= htmlspecialchars($date) ?>
                      </p>
                    </div>
                    <div class="card-footer bg-transparent d-flex justify-content-between">
                      <a 
                        href="?sectionID=<?= urlencode($sec['SectionID']) ?>&date=<?= urlencode($date) ?>"
                        class="btn btn-success btn-sm">
                        <i class="bi bi-check-circle"></i> Take Attendance
                      </a>
                      <a 
                        href="?sectionID=<?= urlencode($sec['SectionID']) ?>&date=<?= urlencode($date) ?>&view=true"
                        class="btn btn-info btn-sm">
                        <i class="bi bi-eye"></i> View
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
      <div class="mb-3">
        <a href="?date=<?= urlencode($date) ?>" class="btn btn-secondary back-button">
          <i class="bi bi-arrow-left"></i> Back to Class Selection
        </a>
      </div>

      <!-- Navigation tabs -->
      <ul class="nav nav-tabs mb-3">
        <li class="nav-item">
          <a class="nav-link <?= !$viewMode ? 'active' : '' ?>" 
             href="?sectionID=<?= $sectionID ?>&date=<?= $date ?>">
            <i class="bi bi-pencil-square"></i> Take Attendance
          </a>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= $viewMode ? 'active' : '' ?>" 
             href="?sectionID=<?= $sectionID ?>&date=<?= $date ?>&view=true">
            <i class="bi bi-eye"></i> View Attendance
          </a>
        </li>
      </ul>

      <h2>
        <?= $viewMode ? 'Viewing' : 'Taking' ?> Attendance for 
        Grade <?= htmlspecialchars($selected['GradeLevel']) ?> -
        <?= htmlspecialchars($selected['SectionName']) ?>
        <small class="text-muted">(<?= htmlspecialchars($date) ?>)</small>
      </h2>

      <?php if (!$viewMode): ?>
        <!-- == STEP 2: Attendance Form for Selected Section == -->
        <?php
          // Fetch existing statuses, now filtering by sectionID
          $attendanceStatus = [];
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

          // Fetch students in this section
          $s1 = $conn->prepare("
            SELECT st.LRN, st.FirstName, st.MiddleName, st.LastName
              FROM section_enrollment se
              JOIN student st ON se.StudentID = st.StudentID
             WHERE se.SectionID = ?
             ORDER BY st.LastName, st.FirstName
          ");
          $s1->bind_param("i", $sectionID);
          $s1->execute();
          $res2 = $s1->get_result();
          $students = $res2->fetch_all(MYSQLI_ASSOC);
          $s1->close();
        ?>

        <form id="attendance-form" class="mt-3">
          <!-- include both date & sectionID -->
          <input type="hidden" name="date"      value="<?= htmlspecialchars($date) ?>">
          <input type="hidden" name="sectionID" value="<?= htmlspecialchars($sectionID) ?>">
          <?php if (empty($students)): ?>
            <div class="alert alert-warning">No students enrolled in this section.</div>
          <?php else: ?>
            <table class="table table-bordered">
              <thead class="table-secondary">
                <tr>
                  <th>LRN</th>
                  <th>Name</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($students as $st): 
                  $lrn    = $st['LRN'];
                  $name   = "{$st['LastName']}, {$st['FirstName']} {$st['MiddleName']}";
                  $status = $attendanceStatus[$lrn] ?? '';
                ?>
                  <tr>
                    <td><?= htmlspecialchars($lrn) ?></td>
                    <td><?= htmlspecialchars($name) ?></td>
                    <td>
                      <select
                        name="attendance[<?= htmlspecialchars($lrn) ?>]"
                        class="form-select form-select-sm"
                        style="max-width:140px;"
                      >
                        <option value="present" <?= $status==='present'  ? 'selected':'' ?>>Present</option>
                        <option value="absent"  <?= $status==='absent'   ? 'selected':'' ?>>Absent</option>
                        <option value="excused" <?= $status==='excused'  ? 'selected':'' ?>>Excused</option>
                      </select>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <div class="d-flex justify-content-end py-2 mt-3">
              <a href="?date=<?= urlencode($date) ?>" class="btn btn-secondary me-2 mb-5">
                <i class="bi bi-arrow-left"></i> Back
              </a>
              <button type="button" id="save-attendance" class="btn btn-success mb-5">
                <i class="bi bi-save"></i> Save Attendance
              </button>
            </div>
          <?php endif; ?>
        </form>

        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script>
          $(function(){
            $('#save-attendance').click(function(){
              $.post('', $('#attendance-form').serialize())
                .done(function(res){
                  const j = JSON.parse(res);
                  alert(j.message);
                  if (j.status === 'success') {
                    // Redirect back to class selection
                    window.location.href = '?date=' + encodeURIComponent($('input[name="date"]').val());
                  }
                })
                .fail(function(){
                  alert('Error submitting attendance.');
                });
            });
          });
        </script>

      <?php else: ?>
        <!-- == VIEW ATTENDANCE MODE == -->
        <?php
          // Fetch attendance records for the selected date and section
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
            ORDER BY s.LastName, s.FirstName
          ");
          $viewStmt->bind_param("siii", $date, $sectionID, $teacherID, $sectionID);
          $viewStmt->execute();
          $viewResult = $viewStmt->get_result();
          $attendanceRecords = $viewResult->fetch_all(MYSQLI_ASSOC);
          $viewStmt->close();
        ?>

        <div class="card mt-3">
          <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Attendance Records for <?= htmlspecialchars($date) ?></h5>
            <span class="badge bg-primary"><?= count($attendanceRecords) ?> Students</span>
          </div>
          <div class="card-body p-0">
            <?php if (empty($attendanceRecords)): ?>
              <div class="p-4 text-center text-muted">
                <i class="bi bi-calendar-x" style="font-size: 2rem;"></i>
                <p class="mt-2">No attendance records found for this date.</p>
              </div>
            <?php else: ?>
              <div class="table-responsive">
                <table class="table table-hover view-mode-table mb-0">
                  <thead class="table-light">
                    <tr>
                      <th>LRN</th>
                      <th>Name</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php 
                    $presentCount = 0;
                    $absentCount = 0;
                    $excusedCount = 0;
                    $noRecordCount = 0;
                    
                    foreach ($attendanceRecords as $record): 
                      $status = $record['Status'];
                      $name = "{$record['LastName']}, {$record['FirstName']} {$record['MiddleName']}";
                      
                      // Count statuses
                      if ($status === 'present') {
                        $presentCount++;
                        $badgeClass = 'badge-present';
                        $statusText = 'Present';
                      } elseif ($status === 'absent') {
                        $absentCount++;
                        $badgeClass = 'badge-absent';
                        $statusText = 'Absent';
                      } elseif ($status === 'excused') {
                        $excusedCount++;
                        $badgeClass = 'badge-excused';
                        $statusText = 'Excused';
                      } else {
                        $noRecordCount++;
                        $badgeClass = 'bg-secondary';
                        $statusText = 'No Record';
                      }
                    ?>
                      <tr>
                        <td><?= htmlspecialchars($record['LRN']) ?></td>
                        <td><?= htmlspecialchars($name) ?></td>
                        <td>
                          <span class="badge attendance-badge <?= $badgeClass ?>">
                            <?= $statusText ?>
                          </span>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
          <?php if (!empty($attendanceRecords)): ?>
            <div class="card-footer">
              <div class="row">
                <div class="col-md-3">
                  <span class="badge bg-success"><?= $presentCount ?> Present</span>
                </div>
                <div class="col-md-3">
                  <span class="badge bg-danger"><?= $absentCount ?> Absent</span>
                </div>
                <div class="col-md-3">
                  <span class="badge bg-secondary"><?= $excusedCount ?> Excused</span>
                </div>
                <div class="col-md-3">
                  <span class="badge bg-warning text-dark"><?= $noRecordCount ?> No Record</span>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <!-- Date navigation -->
        <div class="card mt-3">
          <div class="card-header bg-light">
            <h6 class="mb-0">View Attendance for Different Date</h6>
          </div>
          <div class="card-body">
            <form method="get" class="row g-2">
              <input type="hidden" name="sectionID" value="<?= $sectionID ?>">
              <input type="hidden" name="view" value="true">
              <div class="col-md-8">
                <input type="date" name="date" value="<?= htmlspecialchars($date) ?>" 
                       class="form-control" required>
              </div>
              <div class="col-md-4">
                <button type="submit" class="btn btn-primary w-100">
                  <i class="bi bi-search"></i> View
                </button>
              </div>
            </form>
          </div>
        </div>

        <!-- Back button at the bottom -->
        <div class="mt-3">
          <a href="?date=<?= urlencode($date) ?>" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back to Class Selection
          </a>
        </div>
      <?php endif; ?>
    <?php endif; ?>

  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>