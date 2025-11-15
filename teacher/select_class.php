<?php
session_start();
require_once '../config.php';

// 1) Ensure teacher is logged in
if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'teacher') {
    header('Location: ../login.php');
    exit();
}

// 2) Lookup TeacherID from session email
$teacherEmail = $_SESSION['email'];
$stmt = $conn->prepare("
    SELECT t.TeacherID
      FROM teacher t
      JOIN user u ON t.UserID = u.UserID
     WHERE u.Email = ?
");
$stmt->bind_param('s', $teacherEmail);
$stmt->execute();
$stmt->bind_result($teacherID);
$stmt->fetch();
$stmt->close();

if (!$teacherID) {
    die("Teacher record not found.");
}

// 3) Get date from GET (defaults to today)
$date = $_GET['date'] ?? date('Y-m-d');

// 4) Fetch distinct sections this teacher teaches
$sql = "
  SELECT DISTINCT
    sec.SectionID,
    sec.GradeLevel,
    sec.SectionName
FROM subject sub
JOIN section sec ON sub.secID = sec.SectionID
WHERE sub.TeacherID = ?
ORDER BY sec.GradeLevel, sec.SectionName
";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $teacherID);
$stmt->execute();
$result = $stmt->get_result();

$sections = [];
while ($row = $result->fetch_assoc()) {
    $sections[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Select Class for Attendance</title>
  <link rel="icon" type="image/png" href="../img/logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background: #f0f2f5; }
    .card-container {
      max-width: 900px;
      margin: 60px auto;
    }
  </style>
</head>
<body>
  <?php include '../navs/teacherNav.php'; ?>

  <div class="card card-container shadow-sm">
    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
      <h5 class="mb-0">Take Attendance</h5>
      <!-- Date selector form -->
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
                    href="attendance.php?sectionID=<?= urlencode($sec['SectionID']) ?>&date=<?= urlencode($date) ?>" 
                    class="btn btn-success btn-sm">
                    <i class="bi bi-check-circle"></i>
                    Take Attendance
                  </a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
