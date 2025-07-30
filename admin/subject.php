<?php
session_start();
require __DIR__ . '/../config.php';
date_default_timezone_set('Asia/Manila');

/**
 * Add a new subject.
 */
function addSubjectByGrade(mysqli $conn, string $name, int $gradeLevel) {
    $stmt = $conn->prepare("
        INSERT INTO subject (SubjectName, GradeLevel)
        VALUES (?, ?)
    ");
    if (!$stmt) return false;
    $stmt->bind_param("si", $name, $gradeLevel);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Update an existing subject.
 */
function updateSubject(mysqli $conn, int $subjectId, string $name, int $gradeLevel) {
    $stmt = $conn->prepare("
        UPDATE subject
        SET SubjectName = ?, GradeLevel = ?
        WHERE SubjectID = ?
    ");
    if (!$stmt) return false;
    $stmt->bind_param("sii", $name, $gradeLevel, $subjectId);
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
 * Schedule (insert) a new time slot for a subject.
 */
function scheduleSubject(mysqli $conn, int $subjectId, int $sectionId, string $time, string $day) {
    // find the assigned teacher
    $stmt = $conn->prepare("SELECT TeacherID FROM subject WHERE SubjectID = ?");
    $stmt->bind_param("i", $subjectId);
    $stmt->execute();
    $stmt->bind_result($teacherId);
    if (!$stmt->fetch() || !$teacherId) {
        $stmt->close();
        return false;
    }
    $stmt->close();

    // insert into sched
    $stmt2 = $conn->prepare("
        INSERT INTO sched (`day`, `Time`, SubjectID, TeacherID, SectionID)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt2->bind_param("ssiii", $day, $time, $subjectId, $teacherId, $sectionId);
    $ok = $stmt2->execute();
    $stmt2->close();
    return $ok;
}

/**
 * Update an existing schedule entry.
 */
function updateSchedule(mysqli $conn, int $schedId, string $time, string $day) {
    $stmt = $conn->prepare("
        UPDATE sched
        SET `day` = ?, `Time` = ?
        WHERE schedID = ?
    ");
    if (!$stmt) return false;
    $stmt->bind_param("ssi", $day, $time, $schedId);
    $ok = $stmt->execute();
    $stmt->close();
    return $ok;
}

/**
 * Delete all schedule entries for a subject.
 */
function clearSchedule(mysqli $conn, int $subjectId) {
    $stmt = $conn->prepare("DELETE FROM sched WHERE SubjectID = ?");
    if (!$stmt) return false;
    $stmt->bind_param("i", $subjectId);
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
        if ($name === '' || !$level) {
            $_SESSION['error'] = "Subject name and grade level are required.";
        } elseif (!addSubjectByGrade($conn, $name, $level)) {
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
        if (!$subjectId || $name === '' || !$level) {
            $_SESSION['error'] = "All fields are required to edit.";
        } elseif (!updateSubject($conn, $subjectId, $name, $level)) {
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

    // Schedule Subject (convert to 12-hour format)
    if (isset($_POST['schedule_subject'])) {
        $subjectId = intval($_POST['schedule_subject_id'] ?? 0);
        $sectionId = intval($_POST['section_id'] ?? 0);
        $day       = trim($_POST['schedule_day'] ?? '');
        $start     = trim($_POST['schedule_time_start'] ?? '');
        $end       = trim($_POST['schedule_time_end'] ?? '');
        if (!$subjectId || !$sectionId || $day === '' || $start === '' || $end === '') {
            $_SESSION['error'] = "All fields are required for scheduling.";
        } else {
            $start12 = date('g:i A', strtotime($start));
            $end12   = date('g:i A', strtotime($end));
            $time    = "{$start12} - {$end12}";
            if (!scheduleSubject($conn, $subjectId, $sectionId, $time, $day)) {
                $_SESSION['error'] = "Failed to schedule subject. Ensure a teacher is assigned.";
            } else {
                $_SESSION['message'] = "Subject scheduled successfully.";
            }
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Update Schedule Entry (convert to 12-hour format)
    if (isset($_POST['update_schedule'])) {
        $schedId = intval($_POST['edit_schedule_id'] ?? 0);
        $day     = trim($_POST['edit_schedule_day'] ?? '');
        $start   = trim($_POST['edit_schedule_time_start'] ?? '');
        $end     = trim($_POST['edit_schedule_time_end'] ?? '');
        if (!$schedId || $day === '' || $start === '' || $end === '') {
            $_SESSION['error'] = "All fields are required to update schedule.";
        } else {
            $start12 = date('g:i A', strtotime($start));
            $end12   = date('g:i A', strtotime($end));
            $time    = "{$start12} - {$end12}";
            if (!updateSchedule($conn, $schedId, $time, $day)) {
                $_SESSION['error'] = "Failed to update schedule entry.";
            } else {
                $_SESSION['message'] = "Schedule entry updated successfully.";
            }
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Clear Schedule
    if (isset($_POST['clear_schedule'])) {
        $subjectId = intval($_POST['clear_schedule_subject_id'] ?? 0);
        if (!$subjectId || !clearSchedule($conn, $subjectId)) {
            $_SESSION['error'] = "Failed to clear schedule.";
        } else {
            $_SESSION['message'] = "Schedule cleared successfully.";
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
  <title>Manage Subjects & Schedule</title>
  <link rel="icon" type="image/png" href="../img/logo.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
</head>
<body>
  <?php include '../navs/adminNav.php'; ?>

  <div class="container mt-5">
    <?php if (!empty($_SESSION['error'])): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($_SESSION['error']) ?></div>
      <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
    <?php if (!empty($_SESSION['message'])): ?>
      <div class="alert alert-success"><?= htmlspecialchars($_SESSION['message']) ?></div>
      <?php unset($_SESSION['message']); ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2>Subjects & Schedule</h2>
      <button class="btn btn-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
        Add Subject
      </button>
    </div>

    <table class="table table-bordered table-hover">
      <thead class="table-light">
        <tr>
          <th>Subject</th>
          <th>Grade</th>
          <th>Teacher</th>
          <th>Sections</th>
          <th>Schedule</th>
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
            CONCAT(t.fName,' ',t.mName,' ',t.lName) AS TeacherName,
            GROUP_CONCAT(DISTINCT CONCAT(sec.GradeLevel,'-',sec.SectionName) SEPARATOR ', ') AS Sections
          FROM subject s
          LEFT JOIN teacher t   ON s.TeacherID = t.TeacherID
          LEFT JOIN sched   sch ON s.SubjectID = sch.SubjectID
          LEFT JOIN section sec ON sch.SectionID = sec.SectionID
          GROUP BY s.SubjectID
          ORDER BY s.SubjectID
        ");
        while ($r = $rs->fetch_assoc()):
        ?>
        <tr>
          <td><?= htmlspecialchars($r['SubjectName']) ?></td>
          <td><?= (int)$r['GradeLevel'] ?></td>
          <td><?= htmlspecialchars($r['TeacherName'] ?: '—') ?></td>
          <td><?= htmlspecialchars($r['Sections']     ?: '—') ?></td>
          <td>
            <?php
              $stmt = $conn->prepare("
                SELECT schedID, `day`, `Time`
                  FROM sched
                 WHERE SubjectID = ?
              ORDER BY `day`, `Time`
              ");
              $stmt->bind_param("i", $r['SubjectID']);
              $stmt->execute();
              $dr = $stmt->get_result();
              if ($dr->num_rows) {
                echo '<ul class="list-unstyled mb-0">';
                while ($d = $dr->fetch_assoc()) {
                  list($start, $end) = explode(' - ', $d['Time']);
                  echo '<li class="d-flex justify-content-between align-items-center mb-2">'
                     . '<span>' . htmlspecialchars($d['day'] . ' ' . $d['Time']) . '</span>'
                     . '<button '
                     .   'class="btn btn-sm btn-light border-dark" '
                     .   'data-bs-toggle="modal" '
                     .   'data-bs-target="#editScheduleModal" '
                     .   'data-schedule-id="'. (int)$d['schedID'] .'" '
                     .   'data-day="'. htmlspecialchars($d['day']) .'" '
                     .   'data-start="'. $start .'" '
                     .   'data-end="'. $end .'">'
                     .   'Edit'
                     . '</button>'
                     . '</li>';
                }
                echo '</ul>';
              } else {
                echo '—';
              }
              $stmt->close();
            ?>
          </td>
          <td class="d-flex gap-1">
            <button 
              class="btn btn-light border-dark btn-sm"
              data-bs-toggle="modal" 
              data-bs-target="#editSubjectModal"
              data-subject-id="<?= (int)$r['SubjectID'] ?>"
              data-subject-name="<?= htmlspecialchars($r['SubjectName']) ?>"
              data-grade-level="<?= (int)$r['GradeLevel'] ?>"
            >Edit</button>

            <button 
              class="btn btn-sm <?= empty($r['TeacherName']) ? 'btn-primary' : 'btn-secondary' ?>"
              data-bs-toggle="modal" 
              data-bs-target="#assignTeacherModal"
              data-subject-id="<?= (int)$r['SubjectID'] ?>"
            >
              <?= empty($r['TeacherName']) ? 'Assign' : 'Reassign' ?>
            </button>

            <button 
              class="btn btn-info btn-sm" 
              data-bs-toggle="modal" 
              data-bs-target="#scheduleModal"
              data-subject-id="<?= (int)$r['SubjectID'] ?>"
            >Schedule</button>

            <form method="post" onsubmit="return confirm('Clear all schedule entries for this subject?');">
              <input type="hidden" name="clear_schedule_subject_id" value="<?= (int)$r['SubjectID'] ?>">
              <button type="submit" name="clear_schedule" class="btn btn-danger btn-sm">Clear</button>
            </form>
          </td>
        </tr>
        <?php endwhile; ?>
      </tbody>
    </table>
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
            <input type="number" name="grade_level" class="form-control" min="1" required>
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
            <input type="number" name="edit_grade-level" id="edit-grade-level" class="form-control" min="1" required>
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

  <!-- Schedule Subject Modal -->
  <div class="modal fade" id="scheduleModal" tabindex="-1">
    <div class="modal-dialog">
      <form method="post" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Schedule Subject</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="schedule_subject_id" id="schedule-subject-id">
          <div class="mb-3">
            <label class="form-label">Section</label>
            <select name="section_id" class="form-select" required>
              <option value="" disabled selected>Select section</option>
              <?php
              $sr = $conn->query("SELECT SectionID, GradeLevel, SectionName FROM section");
              while ($s = $sr->fetch_assoc()):
                $label = htmlspecialchars("Grade {$s['GradeLevel']}-{$s['SectionName']}");
              ?>
                <option value="<?= (int)$s['SectionID'] ?>"><?= $label ?></option>
              <?php endwhile; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Day</label>
            <select name="schedule_day" class="form-select" required>
              <option value="" disabled selected>Select day</option>
              <option>Monday</option>
              <option>Tuesday</option>
              <option>Wednesday</option>
              <option>Thursday</option>
              <option>Friday</option>
              <option>Saturday</option>
            </select>
          </div>
          <div class="row">
            <div class="col mb-3">
              <label class="form-label">Start Time</label>
              <input type="time" name="schedule_time_start" class="form-control" required>
            </div>
            <div class="col mb-3">
              <label class="form-label">End Time</label>
              <input type="time" name="schedule_time_end" class="form-control" required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="schedule_subject" class="btn btn-info">Schedule</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Schedule Entry Modal -->
  <div class="modal fade" id="editScheduleModal" tabindex="-1">
    <div class="modal-dialog">
      <form method="post" class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Edit Schedule Entry</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="edit_schedule_id" id="edit-schedule-id">
          <div class="mb-3">
            <label class="form-label">Day</label>
            <select name="edit_schedule_day" id="edit-schedule-day" class="form-select" required>
              <option value="" disabled>Select day</option>
              <option>Monday</option>
              <option>Tuesday</option>
              <option>Wednesday</option>
              <option>Thursday</option>
              <option>Friday</option>
              <option>Saturday</option>
            </select>
          </div>
          <div class="row">
            <div class="col mb-3">
              <label class="form-label">Start Time</label>
              <input type="time" name="edit_schedule_time_start" id="edit-schedule-time-start" class="form-control" required>
            </div>
            <div class="col mb-3">
              <label class="form-label">End Time</label>
              <input type="time" name="edit_schedule_time_end" id="edit-schedule-time-end" class="form-control" required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="update_schedule" class="btn btn-primary">Update</button>
        </div>
      </form>
    </div>
  </div>


  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Populate Edit Subject modal
    $('#editSubjectModal').on('show.bs.modal', function(e) {
      const btn = $(e.relatedTarget);
      $('#edit-subject-id').val(btn.data('subject-id'));
      $('#edit-subject-name').val(btn.data('subject-name'));
      $('#edit-grade-level').val(btn.data('grade-level'));
    });
    // Populate Assign Teacher modal
    $('#assignTeacherModal').on('show.bs.modal', function(e) {
      $(this).find('#assign-subject-id').val($(e.relatedTarget).data('subject-id'));
    });
    // Populate Schedule modal
    $('#scheduleModal').on('show.bs.modal', function(e) {
      $(this).find('#schedule-subject-id').val($(e.relatedTarget).data('subject-id'));
    });
    // Populate Edit Schedule Entry modal
    $('#editScheduleModal').on('show.bs.modal', function(e) {
      const btn = $(e.relatedTarget);
      $('#edit-schedule-id').val(btn.data('schedule-id'));
      $('#edit-schedule-day').val(btn.data('day'));
      $('#edit-schedule-time-start').val(btn.data('start'));
      $('#edit-schedule-time-end').val(btn.data('end'));
    });
  </script>
</body>
</html>
