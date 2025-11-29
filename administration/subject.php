<?php
session_start();
require __DIR__ . '/../config.php';
date_default_timezone_set('Asia/Manila');

// Get active school year
$active_school_year = '';
$result = mysqli_query($conn, "SELECT school_year FROM school_year WHERE status = 'active' LIMIT 1");
if ($result && mysqli_num_rows($result) > 0) {
    $row = mysqli_fetch_assoc($result);
    $active_school_year = $row['school_year'];
}
mysqli_free_result($result);

/**
 * Add a new subject
 */
function addSubject(mysqli $conn, string $name, float $ww, float $pt, float $qa) {
    $stmt = $conn->prepare("
        INSERT INTO subject (SubjectName, written_work_percentage, performance_task_percentage, quarterly_assessment_percentage)
        VALUES (?, ?, ?, ?)
    ");
    if (!$stmt) return false;
    $stmt->bind_param("sddd", $name, $ww, $pt, $qa);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Update an existing subject
 */
function updateSubject(mysqli $conn, int $subjectId, string $name, float $ww, float $pt, float $qa) {
    $stmt = $conn->prepare("
        UPDATE subject
        SET SubjectName = ?, written_work_percentage = ?, performance_task_percentage = ?, quarterly_assessment_percentage = ?
        WHERE SubjectID = ?
    ");
    if (!$stmt) return false;
    $stmt->bind_param("sdddi", $name, $ww, $pt, $qa, $subjectId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Assign subject to teacher
 */
function assignSubjectToTeacher(mysqli $conn, int $teacherId, int $subjectId, int $sectionId, string $schoolYear) {
    $stmt = $conn->prepare("
        INSERT INTO assigned_subject (teacher_id, subject_id, section_id, school_year)
        VALUES (?, ?, ?, ?)
    ");
    if (!$stmt) return false;
    $stmt->bind_param("iiis", $teacherId, $subjectId, $sectionId, $schoolYear);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Remove subject assignment from teacher
 */
function removeSubjectAssignment(mysqli $conn, int $assignmentId) {
    $stmt = $conn->prepare("DELETE FROM assigned_subject WHERE ID = ?");
    if (!$stmt) return false;
    $stmt->bind_param("i", $assignmentId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

// Handle all form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add Subject
    if (isset($_POST['add_subject'])) {
        $name = trim($_POST['subject_name'] ?? '');
        $ww   = floatval($_POST['written_work_percentage'] ?? 0);
        $pt   = floatval($_POST['performance_task_percentage'] ?? 0);
        $qa   = floatval($_POST['quarterly_assessment_percentage'] ?? 0);
        $total = $ww + $pt + $qa;
        
        if ($name === '') {
            $_SESSION['error'] = "Subject name is required.";
        } elseif ($total !== 100.0) {
            $_SESSION['error'] = "Percentages must total 100%. Current total: $total%.";
        } elseif (!addSubject($conn, $name, $ww/100, $pt/100, $qa/100)) {
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
        $ww        = floatval($_POST['edit_written_work_percentage'] ?? 0);
        $pt        = floatval($_POST['edit_performance_task_percentage'] ?? 0);
        $qa        = floatval($_POST['edit_quarterly_assessment_percentage'] ?? 0);
        $total     = $ww + $pt + $qa;
        
        if (!$subjectId || $name === '') {
            $_SESSION['error'] = "All fields are required to edit.";
        } elseif ($total !== 100.0) {
            $_SESSION['error'] = "Percentages must total 100%. Current total: $total%.";
        } elseif (!updateSubject($conn, $subjectId, $name, $ww/100, $pt/100, $qa/100)) {
            $_SESSION['error'] = "Failed to update subject.";
        } else {
            $_SESSION['message'] = "Subject updated successfully.";
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Assign Subject to Teacher
    if (isset($_POST['assign_subject'])) {
        $teacherId = intval($_POST['teacher_id'] ?? 0);
        $subjectId = intval($_POST['assign_subject_id'] ?? 0);
        $sectionId = intval($_POST['section_id'] ?? 0);
        
        if (!$teacherId || !$subjectId || !$sectionId) {
            $_SESSION['error'] = "All fields are required to assign subject.";
        } elseif (!assignSubjectToTeacher($conn, $teacherId, $subjectId, $sectionId, $active_school_year)) {
            $_SESSION['error'] = "Failed to assign subject to teacher.";
        } else {
            $_SESSION['message'] = "Subject assigned to teacher successfully.";
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Remove Assignment
    if (isset($_POST['remove_assignment'])) {
        $assignmentId = intval($_POST['assignment_id'] ?? 0);
        if (!$assignmentId || !removeSubjectAssignment($conn, $assignmentId)) {
            $_SESSION['error'] = "Failed to remove assignment.";
        } else {
            $_SESSION['message'] = "Assignment removed successfully.";
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
  <title>Manage Subjects & Assignments</title>
  <link rel="icon" type="image/png" href="../img/logo.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <style>
    .card {
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        border: 1px solid rgba(0, 0, 0, 0.125);
    }
    .table-responsive {
        max-height: 500px;
        overflow-y: auto;
    }
    .subject-list {
        border-right: 2px solid #dee2e6;
    }
    .badge-percentage {
        font-size: 0.75em;
    }
  </style>
</head>
<body>
  <?php include '../navs/headNav.php'; ?>

  <div class="container-fluid mt-4">
    <?php if (!empty($_SESSION['error'])): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['message'])): ?>
      <div class="alert alert-success"><?= htmlspecialchars($_SESSION['message']) ?></div>
      <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <!-- School Year Indicator -->
    <?php if (!empty($active_school_year)): ?>
    <div class="alert alert-info d-flex justify-content-between align-items-center">
      <div>
        <i class="fas fa-calendar-alt me-2"></i>
        <strong>Active School Year:</strong> <?= htmlspecialchars($active_school_year) ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="row">
      <!-- Left Column: Subjects List -->
      <div class="col-md-5 subject-list">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Subjects</h5>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
              Add Subject
            </button>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-bordered table-hover">
                <thead class="table-light">
                  <tr>
                    <th>Subject Name</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $subjects = $conn->query("
                    SELECT 
                      SubjectID,
                      SubjectName,
                      written_work_percentage,
                      performance_task_percentage,
                      quarterly_assessment_percentage
                    FROM subject
                    ORDER BY SubjectName
                  ");
                  
                  while ($subject = $subjects->fetch_assoc()):
                    $wwPercent = floatval($subject['written_work_percentage']) * 100;
                    $ptPercent = floatval($subject['performance_task_percentage']) * 100;
                    $qaPercent = floatval($subject['quarterly_assessment_percentage']) * 100;
                  ?>
                  <tr>
                    <td><?= htmlspecialchars($subject['SubjectName']) ?></td>
                    <td class="d-flex gap-1">
                      <button 
                        class="btn btn-info btn-sm"
                        data-bs-toggle="modal" 
                        data-bs-target="#viewSubjectModal"
                        data-subject-id="<?= (int)$subject['SubjectID'] ?>"
                        data-subject-name="<?= htmlspecialchars($subject['SubjectName']) ?>"
                        data-written-work="<?= $wwPercent ?>"
                        data-performance-task="<?= $ptPercent ?>"
                        data-quarterly-assessment="<?= $qaPercent ?>"
                      >
                        View
                      </button>

                      <button 
                        class="btn btn-light border-dark btn-sm"
                        data-bs-toggle="modal" 
                        data-bs-target="#editSubjectModal"
                        data-subject-id="<?= (int)$subject['SubjectID'] ?>"
                        data-subject-name="<?= htmlspecialchars($subject['SubjectName']) ?>"
                        data-written-work="<?= $wwPercent ?>"
                        data-performance-task="<?= $ptPercent ?>"
                        data-quarterly-assessment="<?= $qaPercent ?>"
                      >
                        Edit
                      </button>

                      <button 
                        class="btn btn-primary btn-sm"
                        data-bs-toggle="modal" 
                        data-bs-target="#assignSubjectModal"
                        data-subject-id="<?= (int)$subject['SubjectID'] ?>"
                        data-subject-name="<?= htmlspecialchars($subject['SubjectName']) ?>"
                      >
                        Assign
                      </button>
                    </td>
                  </tr>
                  <?php endwhile; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column: Assigned Subjects -->
      <div class="col-md-7">
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Assigned Subjects to Teachers</h5>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-bordered table-hover">
                <thead class="table-light">
                  <tr>
                    <th>Teacher</th>
                    <th>Subject</th>
                    <th>Section</th>
                    <th>School Year</th>
                    <th>Action</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $assignments = $conn->query("
                    SELECT 
                      a.ID as assignment_id,
                      CONCAT(t.fName, ' ', t.lName) as teacher_name,
                      s.SubjectName,
                      sec.SectionName,
                      a.school_year
                    FROM assigned_subject a
                    INNER JOIN teacher t ON a.teacher_id = t.TeacherID
                    INNER JOIN subject s ON a.subject_id = s.SubjectID
                    INNER JOIN section sec ON a.section_id = sec.SectionID
                    WHERE a.school_year = '" . mysqli_real_escape_string($conn, $active_school_year) . "'
                    ORDER BY t.lName, t.fName, s.SubjectName
                  ");
                  
                  if ($assignments && $assignments->num_rows > 0):
                    while ($assignment = $assignments->fetch_assoc()):
                  ?>
                  <tr>
                    <td><?= htmlspecialchars($assignment['teacher_name']) ?></td>
                    <td><?= htmlspecialchars($assignment['SubjectName']) ?></td>
                    <td><?= htmlspecialchars($assignment['SectionName']) ?></td>
                    <td><?= htmlspecialchars($assignment['school_year']) ?></td>
                    <td>
                      <form method="post" class="d-inline">
                        <input type="hidden" name="assignment_id" value="<?= $assignment['assignment_id'] ?>">
                        <button type="submit" name="remove_assignment" class="btn btn-sm btn-danger" 
                                onclick="return confirm('Are you sure you want to remove this assignment?')">
                          Remove
                        </button>
                      </form>
                    </td>
                  </tr>
                  <?php 
                    endwhile;
                  else:
                  ?>
                  <tr>
                    <td colspan="5" class="text-center text-muted">No subjects assigned to teachers yet.</td>
                  </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
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

  <!-- View Subject Modal -->
  <div class="modal fade" id="viewSubjectModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Subject Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label fw-bold">Subject Name</label>
            <p id="view-subject-name" class="form-control-plaintext fs-5"></p>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Written Work</label>
            <p id="view-ww-percentage" class="form-control-plaintext fs-6"></p>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Performance Task</label>
            <p id="view-pt-percentage" class="form-control-plaintext fs-6"></p>
          </div>
          <div class="mb-3">
            <label class="form-label fw-bold">Quarterly Assessment</label>
            <p id="view-qa-percentage" class="form-control-plaintext fs-6"></p>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
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

  <!-- Assign Subject Modal -->
  <div class="modal fade" id="assignSubjectModal" tabindex="-1">
    <div class="modal-dialog">
      <form method="post" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Assign Subject to Teacher</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="assign_subject_id" id="assign-subject-id">
          <div class="mb-3">
            <label class="form-label">Subject</label>
            <input type="text" id="assign-subject-name" class="form-control" readonly>
          </div>
          <div class="mb-3">
            <label class="form-label">Teacher</label>
            <select name="teacher_id" class="form-select" required>
              <option value="" disabled selected>Select teacher</option>
              <?php
              $teachers = $conn->query("SELECT TeacherID, fName, mName, lName FROM teacher ORDER BY lName, fName");
              while ($teacher = $teachers->fetch_assoc()):
                $name = htmlspecialchars("{$teacher['fName']} {$teacher['mName']} {$teacher['lName']}");
              ?>
                <option value="<?= (int)$teacher['TeacherID'] ?>"><?= $name ?></option>
              <?php endwhile; ?>
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
            <small class="text-muted">Subject will be assigned for the active school year: <strong><?= htmlspecialchars($active_school_year) ?></strong></small>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="assign_subject" class="btn btn-primary">Assign</button>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script>
    // Populate View Subject modal
    $('#viewSubjectModal').on('show.bs.modal', function(e) {
      const btn = $(e.relatedTarget);
      $('#view-subject-name').text(btn.data('subject-name'));
      
      const ww = btn.data('written-work');
      const pt = btn.data('performance-task');
      const qa = btn.data('quarterly-assessment');
      
      $('#view-ww-percentage').text(ww + '%');
      $('#view-pt-percentage').text(pt + '%');
      $('#view-qa-percentage').text(qa + '%');
    });

    // Populate Edit Subject modal
    $('#editSubjectModal').on('show.bs.modal', function(e) {
      const btn = $(e.relatedTarget);
      $('#edit-subject-id').val(btn.data('subject-id'));
      $('#edit-subject-name').val(btn.data('subject-name'));
      $('#edit-written-work-percentage').val(btn.data('written-work'));
      $('#edit-performance-task-percentage').val(btn.data('performance-task'));
      $('#edit-quarterly-assessment-percentage').val(btn.data('quarterly-assessment'));
    });
    
    // Populate Assign Subject modal
    $('#assignSubjectModal').on('show.bs.modal', function(e) {
      const btn = $(e.relatedTarget);
      $('#assign-subject-id').val(btn.data('subject-id'));
      $('#assign-subject-name').val(btn.data('subject-name'));
    });

    // Real-time percentage total validation
    function validatePercentageTotal(modalId) {
      const modal = $(modalId);
      const ww = parseFloat(modal.find('input[name*="written_work_percentage"]').val()) || 0;
      const pt = parseFloat(modal.find('input[name*="performance_task_percentage"]').val()) || 0;
      const qa = parseFloat(modal.find('input[name*="quarterly_assessment_percentage"]').val()) || 0;
      const total = ww + pt + qa;
      
      modal.find('.percentage-warning').remove();
      
      if (total !== 100) {
        modal.find('.modal-body').append('<div class="alert alert-warning percentage-warning mt-2">Percentages total: ' + total + '%. Must equal 100%.</div>');
        return false;
      }
      return true;
    }

    // Attach validation to percentage inputs
    $('#editSubjectModal').on('input', 'input[type="number"]', function() {
      validatePercentageTotal('#editSubjectModal');
    });
    
    $('#addSubjectModal').on('input', 'input[type="number"]', function() {
      validatePercentageTotal('#addSubjectModal');
    });

    // Validate before form submission
    $('form').on('submit', function() {
      if ($(this).find('input[name*="written_work_percentage"]').length > 0) {
        const modalId = $(this).closest('.modal').attr('id');
        return validatePercentageTotal('#' + modalId);
      }
      return true;
    });
  </script>
</body>
</html>