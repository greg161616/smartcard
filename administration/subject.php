<?php
session_start();
require __DIR__ . '/../config.php';
date_default_timezone_set('Asia/Manila');

/**
 * Add a new subject.
 */
function addSubjectByGrade(mysqli $conn, string $name, int $gradeLevel, int $sectionId, float $ww, float $pt, float $qa) {
  $stmt = $conn->prepare("
    INSERT INTO subject (SubjectName, GradeLevel, secID, written_work_percentage, performance_task_percentage, quarterly_assessment_percentage)
    VALUES (?, ?, ?, ?, ?, ?)
  ");
  if (!$stmt) return false;
  $stmt->bind_param("siiddd", $name, $gradeLevel, $sectionId, $ww, $pt, $qa);
  $ok = $stmt->execute();
  $stmt->close();
  return $ok;
}

/**
 * Update an existing subject.
 */
function updateSubject(mysqli $conn, int $subjectId, string $name, int $gradeLevel, int $sectionId, float $ww, float $pt, float $qa) {
  $stmt = $conn->prepare("
    UPDATE subject
    SET SubjectName = ?, GradeLevel = ?, secID = ?, written_work_percentage = ?, performance_task_percentage = ?, quarterly_assessment_percentage = ?
    WHERE SubjectID = ?
  ");
  if (!$stmt) return false;
  $stmt->bind_param("siidddi", $name, $gradeLevel, $sectionId, $ww, $pt, $qa, $subjectId);
  $ok = $stmt->execute();
  $stmt->close();
  return $ok;
}

/**
 * Assign a teacher to a subject.
 */
function assignTeacher(mysqli $conn, int $subjectId, int $teacherId) {
  $stmt = $conn->prepare("
    UPDATE subject
    SET TeacherID = ?
    WHERE SubjectID = ?
  ");
  if (!$stmt) return false;
  $stmt->bind_param("ii", $teacherId, $subjectId);
  $ok = $stmt->execute();
  $stmt->close();
  return $ok;
}

/**
 * Add a new section.
 */
function addSection(mysqli $conn, int $gradeLevel, string $sectionName, int $adviserId) {
  $stmt = $conn->prepare("
    INSERT INTO section (GradeLevel, SectionName, AdviserID)
    VALUES (?, ?, ?)
  ");
  if (!$stmt) return false;
  $stmt->bind_param("isi", $gradeLevel, $sectionName, $adviserId);
  $ok = $stmt->execute();
  $stmt->close();
  return $ok;
}

/**
 * Update an existing section.
 */
function updateSection(mysqli $conn, int $sectionId, int $gradeLevel, string $sectionName, int $adviserId) {
  $stmt = $conn->prepare("
    UPDATE section
    SET GradeLevel = ?, SectionName = ?, AdviserID = ?
    WHERE SectionID = ?
  ");
  if (!$stmt) return false;
  $stmt->bind_param("isii", $gradeLevel, $sectionName, $adviserId, $sectionId);
  $ok = $stmt->execute();
  $stmt->close();
  return $ok;
}

// Handle all form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Add Subject
  if (isset($_POST['add_subject'])) {
    $name  = trim($_POST['subject_name'] ?? '');
    $level = intval($_POST['grade_level'] ?? 0);
    $sectionId = intval($_POST['section_id'] ?? 0);
    $ww    = floatval($_POST['written_work_percentage'] ?? 0);
    $pt    = floatval($_POST['performance_task_percentage'] ?? 0);
    $qa    = floatval($_POST['quarterly_assessment_percentage'] ?? 0);
    $total = $ww + $pt + $qa;
    
    if ($name === '' || !$level || !$sectionId) {
      $_SESSION['error'] = "Subject name, grade level, and section are required.";
    } elseif ($total !== 100.0) {
      $_SESSION['error'] = "Percentages must total 100%. Current total: $total%.";
    } elseif (!addSubjectByGrade($conn, $name, $level, $sectionId, $ww/100, $pt/100, $qa/100)) {
      $_SESSION['error'] = "Failed to add subject.";
    } else {
      $_SESSION['message'] = "Subject added successfully.";
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
  }

  // Edit Subject
  if (isset($_POST['edit_subject'])) {
    $subjectId = intval($_POST['edit_subject_id'] ?? 0);
    $name      = trim($_POST['edit_subject_name'] ?? '');
    $level     = intval($_POST['edit_grade_level'] ?? 0);
    $sectionId = intval($_POST['edit_section_id'] ?? 0);
    $ww        = floatval($_POST['edit_written_work_percentage'] ?? 0);
    $pt        = floatval($_POST['edit_performance_task_percentage'] ?? 0);
    $qa        = floatval($_POST['edit_quarterly_assessment_percentage'] ?? 0);
    $total     = $ww + $pt + $qa;
    
    if (!$subjectId || $name === '' || !$level || !$sectionId) {
      $_SESSION['error'] = "All fields are required to edit.";
    } elseif ($total !== 100.0) {
      $_SESSION['error'] = "Percentages must total 100%. Current total: $total%.";
    } elseif (!updateSubject($conn, $subjectId, $name, $level, $sectionId, $ww/100, $pt/100, $qa/100)) {
      $_SESSION['error'] = "Failed to update subject.";
    } else {
      $_SESSION['message'] = "Subject updated successfully.";
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
  }

  // Assign/Reassign Teacher
  if (isset($_POST['assign_teacher'])) {
    $subjectId = intval($_POST['assign_subject_id'] ?? 0);
    $teacherId = intval($_POST['teacher_id'] ?? 0);
    if (!$subjectId || !$teacherId || !assignTeacher($conn, $subjectId, $teacherId)) {
      $_SESSION['error'] = "Failed to assign teacher.";
    } else {
      $_SESSION['message'] = "Teacher assigned successfully.";
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
  }

  // Add Section
  if (isset($_POST['add_section'])) {
    $gradeLevel = intval($_POST['section_grade_level'] ?? 0);
    $sectionName = trim($_POST['section_name'] ?? '');
    $adviserId = intval($_POST['section_adviser_id'] ?? 0);
    
    if (!$gradeLevel || $sectionName === '' || !$adviserId) {
      $_SESSION['error'] = "All fields are required to add a section.";
    } elseif (!addSection($conn, $gradeLevel, $sectionName, $adviserId)) {
      $_SESSION['error'] = "Failed to add section.";
    } else {
      $_SESSION['message'] = "Section added successfully.";
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
  }

  // Edit Section
  if (isset($_POST['edit_section'])) {
    $sectionId = intval($_POST['edit_section_id'] ?? 0);
    $gradeLevel = intval($_POST['edit_section_grade_level'] ?? 0);
    $sectionName = trim($_POST['edit_section_name'] ?? '');
    $adviserId = intval($_POST['edit_section_adviser_id'] ?? 0);
    
    if (!$sectionId || !$gradeLevel || $sectionName === '' || !$adviserId) {
      $_SESSION['error'] = "All fields are required to edit a section.";
    } elseif (!updateSection($conn, $sectionId, $gradeLevel, $sectionName, $adviserId)) {
      $_SESSION['error'] = "Failed to update section.";
    } else {
      $_SESSION['message'] = "Section updated successfully.";
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Manage Subjects & Sections</title>
  <link rel="icon" type="image/png" href="../img/logo.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
</head>
<body>
  <?php include '../navs/headNav.php'; ?>

  <div class="container mt-5">
    <?php if (!empty($_SESSION['error'])): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['message'])): ?>
      <div class="alert alert-success"><?= htmlspecialchars($_SESSION['message']) ?></div>
      <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <!-- Tab Navigation -->
    <ul class="nav nav-tabs mb-4" id="managementTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" id="subjects-tab" data-bs-toggle="tab" data-bs-target="#subjects" type="button" role="tab">
          Subjects
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" id="sections-tab" data-bs-toggle="tab" data-bs-target="#sections" type="button" role="tab">
          Sections
        </button>
      </li>
    </ul>

    <div class="tab-content" id="managementTabsContent">
      <!-- Subjects Tab -->
      <div class="tab-pane fade show active" id="subjects" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2>Manage Subjects</h2>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
            Add Subject
          </button>
        </div>

        <table class="table table-bordered table-hover">
          <thead class="table-light">
            <tr>
              <th>Subject</th>
              <th>Grade Level</th>
              <th>Section</th>
              <th>Written Work %</th>
              <th>Performance Task %</th>
              <th>Quarterly Assessment %</th>
              <th>Teacher</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $rs = $conn->query("
              SELECT 
                s.SubjectID,
                s.SubjectName,
                s.GradeLevel,
                s.secID,
                s.written_work_percentage,
                s.performance_task_percentage,
                s.quarterly_assessment_percentage,
                CONCAT(t.fName,' ',t.mName,' ',t.lName) AS TeacherName,
                sec.SectionName
              FROM subject s
              LEFT JOIN teacher t ON s.TeacherID = t.TeacherID
              LEFT JOIN section sec ON s.secID = sec.SectionID
              ORDER BY s.GradeLevel, sec.SectionName, s.SubjectName
            ");
            while ($r = $rs->fetch_assoc()):
              $wwPercent = floatval($r['written_work_percentage']) * 100;
              $ptPercent = floatval($r['performance_task_percentage']) * 100;
              $qaPercent = floatval($r['quarterly_assessment_percentage']) * 100;
            ?>
            <tr>
              <td><?= htmlspecialchars($r['SubjectName']) ?></td>
              <td>Grade <?= (int)$r['GradeLevel'] ?></td>
              <td><?= htmlspecialchars($r['SectionName'] ?: '—') ?></td>
              <td><?= $wwPercent ?>%</td>
              <td><?= $ptPercent ?>%</td>
              <td><?= $qaPercent ?>%</td>
              <td><?= htmlspecialchars($r['TeacherName'] ?: '—') ?></td>
              <td class="d-flex gap-1">
                <button 
                  class="btn btn-light border-dark btn-sm"
                  data-bs-toggle="modal" 
                  data-bs-target="#editSubjectModal"
                  data-subject-id="<?= (int)$r['SubjectID'] ?>"
                  data-subject-name="<?= htmlspecialchars($r['SubjectName']) ?>"
                  data-grade-level="<?= (int)$r['GradeLevel'] ?>"
                  data-section-id="<?= (int)$r['secID'] ?>"
                  data-written-work="<?= $wwPercent ?>"
                  data-performance-task="<?= $ptPercent ?>"
                  data-quarterly-assessment="<?= $qaPercent ?>"
                >Edit</button>

                <button 
                  class="btn btn-sm <?= empty($r['TeacherName']) ? 'btn-primary' : 'btn-secondary' ?>"
                  data-bs-toggle="modal" 
                  data-bs-target="#assignTeacherModal"
                  data-subject-id="<?= (int)$r['SubjectID'] ?>"
                >
                  <?= empty($r['TeacherName']) ? 'Assign' : 'Reassign' ?>
                </button>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>

      <!-- Sections Tab -->
      <div class="tab-pane fade" id="sections" role="tabpanel">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2>Manage Sections</h2>
          <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSectionModal">
            Add Section
          </button>
        </div>

        <table class="table table-bordered table-hover">
          <thead class="table-light">
            <tr>
              <th>Section Name</th>
              <th>Grade Level</th>
              <th>Adviser</th>
              <th>Student Count</th>
              <th>Subject Count</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
            $sections_rs = $conn->query("
              SELECT 
                s.SectionID,
                s.SectionName,
                s.GradeLevel,
                s.AdviserID,
                CONCAT(t.fName,' ',t.mName,' ',t.lName) AS AdviserName,
                COUNT(DISTINCT se.StudentID) as student_count,
                COUNT(DISTINCT sub.SubjectID) as subject_count
              FROM section s
              LEFT JOIN teacher t ON s.AdviserID = t.TeacherID
              LEFT JOIN section_enrollment se ON s.SectionID = se.SectionID AND se.status = 'active'
              LEFT JOIN subject sub ON s.SectionID = sub.secID
              GROUP BY s.SectionID, s.SectionName, s.GradeLevel, t.fName, t.mName, t.lName
              ORDER BY s.GradeLevel, s.SectionName
            ");
            while ($section = $sections_rs->fetch_assoc()):
            ?>
            <tr>
              <td><?= htmlspecialchars($section['SectionName']) ?></td>
              <td>Grade <?= (int)$section['GradeLevel'] ?></td>
              <td><?= htmlspecialchars($section['AdviserName'] ?: '—') ?></td>
              <td class="text-center"><?= (int)$section['student_count'] ?></td>
              <td class="text-center"><?= (int)$section['subject_count'] ?></td>
              <td>
                <button 
                  class="btn btn-light border-dark btn-sm"
                  data-bs-toggle="modal" 
                  data-bs-target="#editSectionModal"
                  data-section-id="<?= (int)$section['SectionID'] ?>"
                  data-section-name="<?= htmlspecialchars($section['SectionName']) ?>"
                  data-grade-level="<?= (int)$section['GradeLevel'] ?>"
                  data-adviser-id="<?= (int)$section['AdviserID'] ?>"
                >Edit</button>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- Add Subject Modal -->
  <div class="modal fade" id="addSubjectModal" tabindex="-1">
    <div class="modal-dialog">
      <form method="post" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Subject</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Subject Name</label>
            <input type="text" name="subject_name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Grade Level</label>
            <select name="grade_level" class="form-select" required>
              <option value="" disabled selected>Select grade level</option>
              <option value="7">Grade 7</option>
              <option value="8">Grade 8</option>
              <option value="9">Grade 9</option>
              <option value="10">Grade 10</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Section</label>
            <select name="section_id" class="form-select" required>
              <option value="" disabled selected>Select section</option>
              <?php
              $sections = $conn->query("SELECT SectionID, SectionName, GradeLevel FROM section ORDER BY GradeLevel, SectionName");
              while ($section = $sections->fetch_assoc()):
              ?>
                <option value="<?= (int)$section['SectionID'] ?>">
                  Grade <?= (int)$section['GradeLevel'] ?> - <?= htmlspecialchars($section['SectionName']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Written Work %</label>
            <input type="number" name="written_work_percentage" class="form-control" min="0" max="100" step="1" placeholder="%" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Performance Task %</label>
            <input type="number" name="performance_task_percentage" class="form-control" min="0" max="100" step="1" placeholder="%" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Quarterly Assessment %</label>
            <input type="number" name="quarterly_assessment_percentage" class="form-control" min="0" max="100" step="1" placeholder="%" required>
          </div>
          <div class="mb-3">
            <small class="text-muted">Percentages must total 100%.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="add_subject" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Subject Modal -->
  <div class="modal fade" id="editSubjectModal" tabindex="-1">
    <div class="modal-dialog">
      <form method="post" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit Subject</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="edit_subject_id" id="edit-subject-id">
          <div class="mb-3">
            <label class="form-label">Subject Name</label>
            <input type="text" name="edit_subject_name" id="edit-subject-name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Grade Level</label>
            <select name="edit_grade_level" id="edit-grade-level" class="form-select" required>
              <option value="" disabled>Select grade level</option>
              <option value="7">Grade 7</option>
              <option value="8">Grade 8</option>
              <option value="9">Grade 9</option>
              <option value="10">Grade 10</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Section</label>
            <select name="edit_section_id" id="edit-section-id" class="form-select" required>
              <option value="" disabled>Select section</option>
              <?php
              $sections = $conn->query("SELECT SectionID, SectionName, GradeLevel FROM section ORDER BY GradeLevel, SectionName");
              while ($section = $sections->fetch_assoc()):
              ?>
                <option value="<?= (int)$section['SectionID'] ?>">
                  Grade <?= (int)$section['GradeLevel'] ?> - <?= htmlspecialchars($section['SectionName']) ?>
                </option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Written Work %</label>
            <input type="number" name="edit_written_work_percentage" id="edit-written-work-percentage" class="form-control" min="0" max="100" step="1" placeholder="%" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Performance Task %</label>
            <input type="number" name="edit_performance_task_percentage" id="edit-performance-task-percentage" class="form-control" min="0" max="100" step="1" placeholder="%" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Quarterly Assessment %</label>
            <input type="number" name="edit_quarterly_assessment_percentage" id="edit-quarterly-assessment-percentage" class="form-control" min="0" max="100" step="1" placeholder="%" required>
          </div>
          <div class="mb-3">
            <small class="text-muted">Percentages must total 100%.</small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_subject" class="btn btn-primary">Update</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Assign Teacher Modal -->
  <div class="modal fade" id="assignTeacherModal" tabindex="-1">
    <div class="modal-dialog">
      <form method="post" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Assign Teacher</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="assign_subject_id" id="assign-subject-id">
          <div class="mb-3">
            <label class="form-label">Teacher</label>
            <select name="teacher_id" class="form-select" required>
              <option value="" disabled selected>Select teacher</option>
              <?php
              $tr = $conn->query("SELECT TeacherID, fName, mName, lName FROM teacher");
              while ($t = $tr->fetch_assoc()):
                $name = htmlspecialchars("{$t['fName']} {$t['mName']} {$t['lName']}");
              ?>
                <option value="<?= (int)$t['TeacherID'] ?>"><?= $name ?></option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="assign_teacher" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Add Section Modal -->
  <div class="modal fade" id="addSectionModal" tabindex="-1">
    <div class="modal-dialog">
      <form method="post" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Add Section</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Grade Level</label>
            <select name="section_grade_level" class="form-select" required>
              <option value="" disabled selected>Select grade level</option>
              <option value="7">Grade 7</option>
              <option value="8">Grade 8</option>
              <option value="9">Grade 9</option>
              <option value="10">Grade 10</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Section Name</label>
            <input type="text" name="section_name" class="form-control" placeholder="e.g., Section A, STEM, ABM" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Adviser</label>
            <select name="section_adviser_id" class="form-select" required>
              <option value="" disabled selected>Select adviser</option>
              <?php
              $teachers_rs = $conn->query("SELECT TeacherID, fName, mName, lName FROM teacher");
              while ($teacher = $teachers_rs->fetch_assoc()):
                $name = htmlspecialchars("{$teacher['fName']} {$teacher['mName']} {$teacher['lName']}");
              ?>
                <option value="<?= (int)$teacher['TeacherID'] ?>"><?= $name ?></option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="add_section" class="btn btn-primary">Save</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Section Modal -->
  <div class="modal fade" id="editSectionModal" tabindex="-1">
    <div class="modal-dialog">
      <form method="post" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit Section</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="edit_section_id" id="edit-section-id">
          <div class="mb-3">
            <label class="form-label">Grade Level</label>
            <select name="edit_section_grade_level" id="edit-section-grade-level" class="form-select" required>
              <option value="" disabled>Select grade level</option>
              <option value="7">Grade 7</option>
              <option value="8">Grade 8</option>
              <option value="9">Grade 9</option>
              <option value="10">Grade 10</option>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Section Name</label>
            <input type="text" name="edit_section_name" id="edit-section-name" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Adviser</label>
            <select name="edit_section_adviser_id" id="edit-section-adviser-id" class="form-select" required>
              <option value="" disabled>Select adviser</option>
              <?php
              $teachers_rs = $conn->query("SELECT TeacherID, fName, mName, lName FROM teacher");
              while ($teacher = $teachers_rs->fetch_assoc()):
                $name = htmlspecialchars("{$teacher['fName']} {$teacher['mName']} {$teacher['lName']}");
              ?>
                <option value="<?= (int)$teacher['TeacherID'] ?>"><?= $name ?></option>
              <?php endwhile; ?>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_section" class="btn btn-primary">Update</button>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script>
    // Populate Edit Subject modal
    $('#editSubjectModal').on('show.bs.modal', function(e) {
      const btn = $(e.relatedTarget);
      $('#edit-subject-id').val(btn.data('subject-id'));
      $('#edit-subject-name').val(btn.data('subject-name'));
      $('#edit-grade-level').val(btn.data('grade-level'));
      $('#edit-section-id').val(btn.data('section-id'));
      $('#edit-written-work-percentage').val(btn.data('written-work'));
      $('#edit-performance-task-percentage').val(btn.data('performance-task'));
      $('#edit-quarterly-assessment-percentage').val(btn.data('quarterly-assessment'));
    });
    
    // Populate Assign Teacher modal
    $('#assignTeacherModal').on('show.bs.modal', function(e) {
      $(this).find('#assign-subject-id').val($(e.relatedTarget).data('subject-id'));
    });

    // Populate Edit Section modal
    $('#editSectionModal').on('show.bs.modal', function(e) {
      const btn = $(e.relatedTarget);
      $('#edit-section-id').val(btn.data('section-id'));
      $('#edit-section-name').val(btn.data('section-name'));
      $('#edit-section-grade-level').val(btn.data('grade-level'));
      $('#edit-section-adviser-id').val(btn.data('adviser-id'));
    });

    // Real-time percentage total validation
    function validatePercentageTotal() {
      const ww = parseFloat($('#edit-written-work-percentage').val()) || 0;
      const pt = parseFloat($('#edit-performance-task-percentage').val()) || 0;
      const qa = parseFloat($('#edit-quarterly-assessment-percentage').val()) || 0;
      const total = ww + pt + qa;
      
      if (total !== 100) {
        $('.percentage-warning').remove();
        $('#editSubjectModal .modal-body').append('<div class="alert alert-warning percentage-warning mt-2">Percentages total: ' + total + '%. Must equal 100%.</div>');
      } else {
        $('.percentage-warning').remove();
      }
    }

    // Attach validation to percentage inputs
    $('#editSubjectModal').on('input', 'input[type="number"]', validatePercentageTotal);
    $('#addSubjectModal').on('input', 'input[type="number"]', function() {
      const ww = parseFloat($('#addSubjectModal input[name="written_work_percentage"]').val()) || 0;
      const pt = parseFloat($('#addSubjectModal input[name="performance_task_percentage"]').val()) || 0;
      const qa = parseFloat($('#addSubjectModal input[name="quarterly_assessment_percentage"]').val()) || 0;
      const total = ww + pt + qa;
      
      if (total !== 100) {
        $('.percentage-warning').remove();
        $('#addSubjectModal .modal-body').append('<div class="alert alert-warning percentage-warning mt-2">Percentages total: ' + total + '%. Must equal 100%.</div>');
      } else {
        $('.percentage-warning').remove();
      }
    });
  </script>
</body>
</html>