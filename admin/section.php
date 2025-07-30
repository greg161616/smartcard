<?php
// admin/sections.php
session_start();
require __DIR__ . '/../config.php';

// ── Handle form submissions ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  // Add Section
  if (isset($_POST['add_section'])) {
    $secName = trim($_POST['section_name'] ?? '');
    $grade   = intval($_POST['grade_level'] ?? 0);
    $adv     = intval($_POST['adviser_id'] ?? 0);

    if ($secName === '') {
      $_SESSION['error'] = "Section name is required.";
    } else {
      // max 5 per grade
      $stmt = $conn->prepare("SELECT COUNT(*) FROM section WHERE GradeLevel = ?");
      $stmt->bind_param("i", $grade);
      $stmt->execute();
      $stmt->bind_result($cnt);
      $stmt->fetch();
      $stmt->close();

      if ($cnt >= 5) {
        $_SESSION['error'] = "Already 5 sections for grade {$grade}.";
      } else {
        $ins = $conn->prepare("
          INSERT INTO section (GradeLevel, SectionName, AdviserID)
          VALUES (?, ?, ?)
        ");
        $ins->bind_param("isi", $grade, $secName, $adv);
        if ($ins->execute()) {
          $_SESSION['message'] = "Section added.";
        } else {
          $_SESSION['error'] = "DB error: " . $ins->error;
        }
        $ins->close();
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
      // max 5 per grade (exclude this section)
      $stmt = $conn->prepare("
        SELECT COUNT(*) FROM section
        WHERE GradeLevel = ? AND SectionID <> ?
      ");
      $stmt->bind_param("ii", $grade, $id);
      $stmt->execute();
      $stmt->bind_result($cnt);
      $stmt->fetch();
      $stmt->close();

      if ($cnt >= 5) {
        $_SESSION['error'] = "Already 5 sections for grade {$grade}.";
      } else {
        $upd = $conn->prepare("
          UPDATE section
          SET GradeLevel = ?, SectionName = ?, AdviserID = ?
          WHERE SectionID = ?
        ");
        $upd->bind_param("isii", $grade, $secName, $adv, $id);
        if ($upd->execute()) {
          $_SESSION['message'] = "Section updated.";
        } else {
          $_SESSION['error'] = "DB error: " . $upd->error;
        }
        $upd->close();
      }
    }
  }

  // Delete Section
  if (isset($_POST['delete_section'])) {
    $id = intval($_POST['section_id']);
    // remove enrollments first
    $conn->query("DELETE FROM section_enrollment WHERE SectionID = {$id}");
    if ($conn->query("DELETE FROM section WHERE SectionID = {$id}")) {
      $_SESSION['message'] = "Section deleted.";
    } else {
      $_SESSION['error'] = "DB error: " . $conn->error;
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
        INSERT INTO section_enrollment (SectionID, StudentID, SchoolYear)
        VALUES (?, ?, ?)
      ");
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
</head>
<body>
  <?php include __DIR__ . '/../navs/adminNav.php'; ?>

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
      <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSectionModal">
        Add Section
      </button>
    </div>

    <table class="table table-bordered table-hover align-middle">
      <thead class="table-secondary">
        <tr>
          <th>Grade</th>
          <th>Section Name</th>
          <th>Adviser</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $rs = $conn->query("
          SELECT
            s.SectionID,
            s.GradeLevel,
            s.SectionName,
            s.AdviserID,
            CONCAT(t.fName,' ',t.mName,' ',t.lName) AS AdviserName
          FROM section s
          LEFT JOIN teacher t ON s.AdviserID = t.TeacherID
        ");
        while ($r = $rs->fetch_assoc()):
        ?>
        <tr>
          <td><?= $r['GradeLevel'] ?></td>
          <td><?= htmlspecialchars($r['SectionName']) ?></td>
          <td><?= htmlspecialchars($r['AdviserName'] ?? 'N/A') ?></td>
          <td>
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
            <form method="post" class="d-inline">
              <input type="hidden" name="section_id" value="<?= $r['SectionID'] ?>">
              <button class="btn btn-sm btn-danger" name="delete_section"
                onclick="return confirm('Delete this section?')">Delete</button>
            </form>
          </td>
        </tr>
        <?php endwhile; ?>
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
                <option value="" disabled selected>Select teacher</option>
                <?php
                $tr = $conn->query("SELECT TeacherID, fName, mName, lName FROM teacher");
                while ($t = $tr->fetch_assoc()):
                  $nm = htmlspecialchars("{$t['fName']} {$t['mName']} {$t['lName']}");
                ?>
                  <option value="<?= $t['TeacherID'] ?>"><?= $nm ?></option>
                <?php endwhile; ?>
              </select>
            </div>
          </diV>
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
                $tr2 = $conn->query("SELECT TeacherID, FirstName, MiddleName, LastName FROM teacher");
                while ($t2 = $tr2->fetch_assoc()):
                  $nm2 = htmlspecialchars("{$t2['FirstName']} {$t2['MiddleName']} {$t2['LastName']}");
                ?>
                  <option value="<?= $t2['TeacherID'] ?>"><?= $nm2 ?></option>
                <?php endwhile; ?>
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

  <!-- Enroll Students Modal -->
  <div class="modal fade" id="enrollModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <form method="post">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Enroll Students</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="section_id">
            <div class="mb-3">
              <label class="form-label">School Year</label>
              <input type="text" name="school_year" class="form-control"
                     value="<?= date('Y') . '-' . (date('Y')+1) ?>" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Students</label>
              <select name="students[]" class="form-select" multiple required>
                <?php
                $sr = $conn->query("
                  SELECT StudentID, FirstName, MiddleName, LastName
                  FROM student
                ");
                while ($s = $sr->fetch_assoc()):
                  $nm = htmlspecialchars("{$s['FirstName']} {$s['MiddleName']} {$s['LastName']}");
                ?>
                  <option value="<?= $s['StudentID'] ?>"><?= $nm ?></option>
                <?php endwhile; ?>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="enroll_students" class="btn btn-success">Enroll</button>
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
    // Populate Enroll modal
    $('#enrollModal').on('show.bs.modal', function(e) {
      var b = $(e.relatedTarget), m = $(this);
      m.find('input[name=section_id]').val(b.data('id'));
    });
  </script>
</body>
</html>
