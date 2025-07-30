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
              You don’t have any scheduled classes.
            </div>
          <?php else: ?>
            <div class="row g-4">
              <?php foreach ($sections as $sec): ?>
                <div class="col-sm-6 col-lg-4">
                  <div class="card h-100">
                    <div class="card-body d-flex flex-column">
                      <h5 class="card-title">
                        Grade <?= htmlspecialchars($sec['GradeLevel']) ?> –
                        <?= htmlspecialchars($sec['SectionName']) ?>
                      </h5>
                      <p class="card-text mt-auto">
                        Date: <?= htmlspecialchars($date) ?>
                      </p>
                    </div>
                    <div class="card-footer bg-transparent text-end">
                      <a 
                        href="?sectionID=<?= urlencode($sec['SectionID']) ?>&date=<?= urlencode($date) ?>"
                        class="btn btn-success btn-sm">
                        <i class="bi bi-check-circle"></i> Take Attendance
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
      <!-- == STEP 2: Attendance Form for Selected Section == -->
      <h2>
        Attendance for 
        Grade <?= htmlspecialchars($selected['GradeLevel']) ?> –
        <?= htmlspecialchars($selected['SectionName']) ?>
        <small class="text-muted">(<?= htmlspecialchars($date) ?>)</small>
      </h2>
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
          <button type="button" id="save-attendance" class="btn btn-success">
            <i class="bi bi-save"></i> Save Attendance
          </button>
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
                  window.location.href = 'select_class.php?date=' + encodeURIComponent($('input[name="date"]').val());
                }
              })
              .fail(function(){
                alert('Error submitting attendance.');
              });
          });
        });
      </script>
    <?php endif; ?>

  </div>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
