<?php
// admin/sections.php
session_start();
require __DIR__ . '/../config.php';

// ── Handle AJAX request for students without section ──────────────────
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_students') {
    $gradeLevel = intval($_POST['grade_level'] ?? 0);
    
    if ($gradeLevel > 0) {
        // Query to get students without a section for this grade level
        $query = "
            SELECT s.StudentID, s.FirstName, s.MiddleName, s.LastName 
            FROM student s
            WHERE s.GradeLevel = ? 
            AND NOT EXISTS (
                SELECT 1 
                FROM section_enrollment se 
                JOIN section sec ON se.SectionID = sec.SectionID 
                WHERE se.StudentID = s.StudentID 
                AND sec.GradeLevel = s.GradeLevel 
                AND se.status = 'active'
            )
            ORDER BY s.LastName, s.FirstName
        ";
        
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param("i", $gradeLevel);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                while ($student = $result->fetch_assoc()) {
                    $name = htmlspecialchars("{$student['LastName']}, {$student['FirstName']} {$student['MiddleName']}");
                    echo "<option value='{$student['StudentID']}'>{$name}</option>";
                }
            } else {
                echo "<option value='' disabled>No students available for this grade level</option>";
            }
            
            $stmt->close();
        } else {
            echo "<option value='' disabled>Database error</option>";
        }
    } else {
        echo "<option value='' disabled>Invalid grade level</option>";
    }
    exit;
}

// ── Handle form submissions ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // Add Section
  if (isset($_POST['add_section'])) {
    $secName = trim($_POST['section_name'] ?? '');
    $grade   = intval($_POST['grade_level'] ?? 0);
    $adv     = intval($_POST['adviser_id'] ?? 0);

    if ($secName === '') {
      $_SESSION['add_section_error'] = "Section name is required.";
      $_SESSION['add_section_old'] = ['grade' => $grade, 'section_name' => $secName, 'adviser_id' => $adv];
    } else {
      // check duplicate section name for the same grade (case-insensitive)
      $dup = $conn->prepare("SELECT 1 FROM section WHERE GradeLevel = ? AND LOWER(SectionName) = LOWER(?) LIMIT 1");
      if ($dup) {
        $dup->bind_param("is", $grade, $secName);
        $dup->execute();
        $dup->store_result();
        if ($dup->num_rows > 0) {
          $_SESSION['add_section_error'] = "A section with that name already exists for grade {$grade}.";
          $_SESSION['add_section_old'] = ['grade' => $grade, 'section_name' => $secName, 'adviser_id' => $adv];
          $dup->close();
        } else {
          $dup->close();
          // Check for any section with the same name (DB may have unique index on SectionName)
          $dupAny = $conn->prepare("SELECT GradeLevel FROM section WHERE LOWER(SectionName) = LOWER(?) LIMIT 1");
          if ($dupAny) {
            $dupAny->bind_param("s", $secName);
            $dupAny->execute();
            $resAny = $dupAny->get_result();
            if ($resAny && $rowAny = $resAny->fetch_assoc()) {
              $existingGrade = $rowAny['GradeLevel'];
              $_SESSION['add_section_error'] = "Section name '{$secName}' already exists (Grade {$existingGrade}). Please choose a different name.";
              $_SESSION['add_section_old'] = ['grade' => $grade, 'section_name' => $secName, 'adviser_id' => $adv];
              $dupAny->close();
            } else {
              $dupAny->close();
              // max 20 per grade
              $stmt = $conn->prepare("SELECT COUNT(*) FROM section WHERE GradeLevel = ?");
              if ($stmt) {
                $stmt->bind_param("i", $grade);
                $stmt->execute();
                $stmt->bind_result($cnt);
                $stmt->fetch();
                $stmt->close();

                if ($cnt >= 20) {
                  $_SESSION['error'] = "Already 20 sections for grade {$grade}.";
                } else {
                  $ins = $conn->prepare("INSERT INTO section (GradeLevel, SectionName, AdviserID) VALUES (?, ?, ?)");
                  if ($ins) {
                    $ins->bind_param("isi", $grade, $secName, $adv);
                    try {
                      if ($ins->execute()) {
                        $_SESSION['message'] = "Section added.";
                      } else {
                        $_SESSION['add_section_error'] = "DB error: " . $ins->error;
                        $_SESSION['add_section_old'] = ['grade' => $grade, 'section_name' => $secName, 'adviser_id' => $adv];
                      }
                    } catch (mysqli_sql_exception $e) {
                      // 1062 = duplicate entry
                      if ($e->getCode() == 1062) {
                        $_SESSION['add_section_error'] = "Duplicate section name detected: '{$secName}'. Please choose a different name.";
                      } else {
                        $_SESSION['add_section_error'] = "DB error: " . $e->getMessage();
                      }
                      $_SESSION['add_section_old'] = ['grade' => $grade, 'section_name' => $secName, 'adviser_id' => $adv];
                    }
                    $ins->close();
                  } else {
                    $_SESSION['add_section_error'] = "Failed to prepare insert statement.";
                    $_SESSION['add_section_old'] = ['grade' => $grade, 'section_name' => $secName, 'adviser_id' => $adv];
                  }
                }
              } else {
                $_SESSION['add_section_error'] = "Failed to prepare count statement.";
                $_SESSION['add_section_old'] = ['grade' => $grade, 'section_name' => $secName, 'adviser_id' => $adv];
              }
            }
          } else {
            $_SESSION['add_section_error'] = "Failed to prepare duplicate check statement.";
            $_SESSION['add_section_old'] = ['grade' => $grade, 'section_name' => $secName, 'adviser_id' => $adv];
          }
        }
      } else {
        $_SESSION['add_section_error'] = "Failed to prepare duplicate check statement.";
        $_SESSION['add_section_old'] = ['grade' => $grade, 'section_name' => $secName, 'adviser_id' => $adv];
      }
    }
  }

  // Edit Section
  if (isset($_POST['edit_section'])) {
    $id      = intval($_POST['section_id']);
    $secName = trim($_POST['section_name'] ?? '');
    $grade   = intval($_POST['grade_level'] ?? 0);
    $adv     = intval($_POST['adviser_id'] ?? 0);

    if ($secName === '') {
      $_SESSION['error'] = "Section name is required.";
    } else {
      // max 20 per grade (exclude this section)
      $stmt = $conn->prepare("
        SELECT COUNT(*) FROM section
        WHERE GradeLevel = ? AND SectionID <> ?
      ");
      if ($stmt) {
        $stmt->bind_param("ii", $grade, $id);
        $stmt->execute();
        $stmt->bind_result($cnt);
        $stmt->fetch();
        $stmt->close();

        if ($cnt >= 20) {
          $_SESSION['error'] = "Already 20 sections for grade {$grade}.";
        } else {
          $upd = $conn->prepare("
            UPDATE section
            SET GradeLevel = ?, SectionName = ?, AdviserID = ?
            WHERE SectionID = ?
          ");
          if ($upd) {
            $upd->bind_param("isii", $grade, $secName, $adv, $id);
            if ($upd->execute()) {
              $_SESSION['message'] = "Section updated.";
            } else {
              $_SESSION['error'] = "DB error: " . $upd->error;
            }
            $upd->close();
          } else {
            $_SESSION['error'] = "Failed to prepare update statement.";
          }
        }
      } else {
        $_SESSION['error'] = "Failed to prepare count statement.";
      }
    }
  }

  // Delete Section
  if (isset($_POST['delete_section'])) {
    $id = intval($_POST['section_id']);
    
    // Check if section has students enrolled
    $checkEnrollment = $conn->prepare("SELECT COUNT(*) FROM section_enrollment WHERE SectionID = ? AND status = 'active'");
    if ($checkEnrollment) {
      $checkEnrollment->bind_param("i", $id);
      $checkEnrollment->execute();
      $checkEnrollment->bind_result($enrollmentCount);
      $checkEnrollment->fetch();
      $checkEnrollment->close();
      
      if ($enrollmentCount > 0) {
        $_SESSION['error'] = "Cannot delete section. There are students enrolled in this section. Please transfer or remove students first.";
      } else {
        // Use prepared statements for security
        $deleteEnrollment = $conn->prepare("DELETE FROM section_enrollment WHERE SectionID = ?");
        if ($deleteEnrollment) {
          $deleteEnrollment->bind_param("i", $id);
          $deleteEnrollment->execute();
          $deleteEnrollment->close();
        }
        
        $deleteSection = $conn->prepare("DELETE FROM section WHERE SectionID = ?");
        if ($deleteSection) {
          $deleteSection->bind_param("i", $id);
          if ($deleteSection->execute()) {
            $_SESSION['message'] = "Section deleted successfully.";
          } else {
            $_SESSION['error'] = "Error deleting section: " . $deleteSection->error;
          }
          $deleteSection->close();
        } else {
          $_SESSION['error'] = "Failed to prepare delete statement.";
        }
      }
    } else {
      $_SESSION['error'] = "Failed to prepare enrollment check statement.";
    }
  }

  // Enroll Students
  if (isset($_POST['enroll_students'])) {
    $id         = intval($_POST['section_id']);
    $sy         = trim($_POST['school_year'] ?? '');
    $students   = $_POST['students'] ?? [];

    if ($sy === '' || empty($students)) {
      $_SESSION['error'] = "School year and at least one student required.";
    } else {
      $chk = $conn->prepare("
        SELECT 1 FROM section_enrollment
        WHERE SectionID = ? AND StudentID = ? AND SchoolYear = ?
      ");
      $ins = $conn->prepare("
        INSERT INTO section_enrollment (SectionID, StudentID, SchoolYear, status)
        VALUES (?, ?, ?, 'active')
      ");
      
      if ($chk && $ins) {
        $count = 0;
        foreach ($students as $stu) {
          $stu = intval($stu);
          // skip if already enrolled
          $chk->bind_param("iis", $id, $stu, $sy);
          $chk->execute();
          if (!$chk->fetch()) {
            $ins->bind_param("iis", $id, $stu, $sy);
            if ($ins->execute()) {
              $count++;
            }
          }
          $chk->free_result();
        }
        $chk->close();
        $ins->close();
        $_SESSION['message'] = "{$count} student(s) enrolled.";
      } else {
        $_SESSION['error'] = "Failed to prepare enrollment statements.";
      }
    }
  }

  // redirect to clear POST
  header("Location: " . $_SERVER['PHP_SELF']);
  exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Sections Management</title>
  <link rel="icon" href="../img/logo.png">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    .action-buttons {
      white-space: nowrap;
    }
    .action-buttons form {
      display: inline-block;
      margin-left: 5px;
    }
  </style>
</head>
<body>
  <?php include __DIR__ . '/../navs/headNav.php'; ?>

  <div class="container mt-4">
    <!-- messages -->
    <?php if (!empty($_SESSION['message'])): ?>
      <div class="alert alert-success"><?= $_SESSION['message']; unset($_SESSION['message']); ?></div>
    <?php endif; ?>
    <?php if (!empty($_SESSION['error'])): ?>
      <div class="alert alert-danger"><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="mb-0">Sections</h2>
      <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSectionModal">
        Add Section
      </button>
    </div>

    <table class="table table-bordered table-hover align-middle text-center">
      <thead class="table-secondary">
        <tr>
          <th>No.</th>
          <th>Section Name</th>
          <th>Grade</th>
          <th>Adviser</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $rs = $conn->query("SELECT s.SectionID, s.GradeLevel, s.SectionName, s.AdviserID, CONCAT(t.fName,' ',t.mName,' ',t.lName) AS AdviserName
                            FROM section s LEFT JOIN teacher t ON s.AdviserID = t.TeacherID
                            ORDER BY s.GradeLevel, s.SectionName");
        $rowNum = 1;
        if ($rs && $rs->num_rows > 0):
          while ($r = $rs->fetch_assoc()):
        ?>
        <tr>
          <td><?= $rowNum++ ?></td>
          <td><?= htmlspecialchars($r['SectionName']) ?></td>
          <td><?= $r['GradeLevel'] ?></td>
          <td><?= htmlspecialchars($r['AdviserName'] ?? 'N/A') ?></td>
          <td class="action-buttons">
            <!-- Edit -->
            <button class="btn btn-sm btn-light border-dark"
              data-bs-toggle="modal"
              data-bs-target="#editSectionModal"
              data-id="<?= $r['SectionID'] ?>"
              data-grade="<?= $r['GradeLevel'] ?>"
              data-name="<?= htmlspecialchars($r['SectionName']) ?>"
              data-adv="<?= $r['AdviserID'] ?>">
              Edit
            </button>
            <!-- Delete -->
            <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this section? This action cannot be undone.');">
              <input type="hidden" name="section_id" value="<?= $r['SectionID'] ?>">
              <button type="submit" name="delete_section" class="btn btn-sm btn-danger">Delete</button>
            </form>
          </td>
        </tr>
        <?php 
          endwhile;
        else:
        ?>
        <tr>
          <td colspan="5" class="text-center">No sections found.</td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- Add Section Modal -->
  <div class="modal fade" id="addSectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form method="post">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Add Section</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Grade Level</label>
              <select name="grade_level" class="form-select" required>
                <option value="" disabled selected>Choose grade</option>
                <?php for ($g=7; $g<=10; $g++): ?>
                  <option value="<?= $g ?>" <?= (!empty($_SESSION['add_section_old']) && $_SESSION['add_section_old']['grade'] == $g) ? 'selected' : '' ?>><?= $g ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Section Name</label>
              <input type="text" name="section_name" class="form-control" required value="<?= htmlspecialchars($_SESSION['add_section_old']['section_name'] ?? '') ?>">
              <?php if (!empty($_SESSION['add_section_error'])): ?>
                <div class="text-danger small mt-1"><?= htmlspecialchars($_SESSION['add_section_error']) ?></div>
              <?php endif; ?>
            </div>
            <div class="mb-3">
              <label class="form-label">Adviser</label>
              <select name="adviser_id" class="form-select" required>
                <option value="" disabled selected>Select teacher</option>
                <?php
                $tr = $conn->query("SELECT TeacherID, fName, mName, lName FROM teacher");
                if ($tr && $tr->num_rows > 0):
                  while ($t = $tr->fetch_assoc()):
                    $nm = htmlspecialchars("{$t['fName']} {$t['mName']} {$t['lName']}");
                ?>
                  <option value="<?= $t['TeacherID'] ?>" <?= (!empty($_SESSION['add_section_old']) && $_SESSION['add_section_old']['adviser_id'] == $t['TeacherID']) ? 'selected' : '' ?>><?= $nm ?></option>
                <?php 
                  endwhile;
                endif;
                ?>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="add_section" class="btn btn-primary">Add</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Section Modal -->
  <div class="modal fade" id="editSectionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form method="post">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Edit Section</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="section_id">
            <div class="mb-3">
              <label class="form-label">Grade Level</label>
              <select name="grade_level" class="form-select" required>
                <option value="" disabled>Choose grade</option>
                <?php for ($g=7; $g<=10; $g++): ?>
                  <option value="<?= $g ?>"><?= $g ?></option>
                <?php endfor; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Section Name</label>
              <input type="text" name="section_name" class="form-control" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Adviser</label>
              <select name="adviser_id" class="form-select" required>
                <option value="" disabled>Select teacher</option>
                <?php
                $tr2 = $conn->query("SELECT TeacherID, fName, mName, lName FROM teacher");
                if ($tr2 && $tr2->num_rows > 0):
                  while ($t2 = $tr2->fetch_assoc()):
                    $nm2 = htmlspecialchars("{$t2['fName']} {$t2['mName']} {$t2['lName']}");
                ?>
                  <option value="<?= $t2['TeacherID'] ?>"><?= $nm2 ?></option>
                <?php 
                  endwhile;
                endif;
                ?>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="edit_section" class="btn btn-warning">Save</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Bootstrap + jQuery -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    // Populate Edit modal
    $('#editSectionModal').on('show.bs.modal', function(e) {
      var b = $(e.relatedTarget), m = $(this);
      m.find('input[name=section_id]').val(b.data('id'));
      m.find('select[name=grade_level]').val(b.data('grade'));
      m.find('input[name=section_name]').val(b.data('name'));
      m.find('select[name=adviser_id]').val(b.data('adv'));
    });

    // Auto-open Add Section modal if there was an add error
    <?php if (!empty($_SESSION['add_section_error'])): ?>
      $(function(){
        var addModal = new bootstrap.Modal(document.getElementById('addSectionModal'));
        addModal.show();
      });
    <?php endif; ?>
    <?php unset($_SESSION['add_section_error'], $_SESSION['add_section_old']); ?>
  </script>
</body>
</html>