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
 * Assign subject to teacher - handles MAPEH assignment to 4 components
 */
function assignSubjectToTeacher(mysqli $conn, int $teacherId, int $subjectId, int $sectionId, string $schoolYear, string $subjectName) {
    // Validate all parameters
    if (!$teacherId || !$sectionId || empty($schoolYear) || (!$subjectId && strtoupper($subjectName) != 'MAPEH')) {
        return false;
    }
    
    // Check if this is MAPEH subject
    if (strtoupper($subjectName) == 'MAPEH') {
        // Get the 4 MAPEH component subject IDs
        $componentNames = ['Music', 'Arts', 'Physical Education', 'Health'];
        $componentIds = [];
        
        foreach ($componentNames as $component) {
            $stmt = $conn->prepare("SELECT SubjectID FROM subject WHERE SubjectName = ? LIMIT 1");
            if (!$stmt) continue;
            $stmt->bind_param("s", $component);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result && $result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $componentIds[] = (int)$row['SubjectID'];
            }
            $stmt->close();
        }
        
        if (empty($componentIds)) {
            return false; // No MAPEH components found
        }
        
        // Assign all 4 components
        $success = true;
        foreach ($componentIds as $compId) {
            $stmt = $conn->prepare("
                INSERT INTO assigned_subject (teacher_id, subject_id, section_id, school_year)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE school_year = VALUES(school_year)
            ");
            if (!$stmt) {
                $success = false;
                continue;
            }
            $stmt->bind_param("iiis", $teacherId, $compId, $sectionId, $schoolYear);
            if (!$stmt->execute()) {
                $success = false;
            }
            $stmt->close();
        }
        return $success;
    } else {
        // Regular subject assignment
        if (!$subjectId) return false;
        
        $stmt = $conn->prepare("
            INSERT INTO assigned_subject (teacher_id, subject_id, section_id, school_year)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE school_year = VALUES(school_year)
        ");
        if (!$stmt) return false;
        $stmt->bind_param("iiis", $teacherId, $subjectId, $sectionId, $schoolYear);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

/**
 * Remove subject assignment from teacher - handles MAPEH component removal
 */
function removeSubjectAssignment(mysqli $conn, int $assignmentId, int $subjectId, string $subjectName, string $schoolYear) {
    global $active_school_year;
    
    // Use provided school year or fallback to active school year
    $targetSchoolYear = !empty($schoolYear) ? $schoolYear : $active_school_year;
    
    if (!$assignmentId) {
        return false;
    }
    
    // Check if this is a MAPEH component
    $isMapehComponent = in_array($subjectName, ['Music', 'Arts', 'Physical Education', 'Health']);
    
    if ($isMapehComponent) {
        // When removing a MAPEH component, remove all components for consistency
        // First, get the teacher and section for this assignment
        $stmt = $conn->prepare("SELECT teacher_id, section_id, school_year FROM assigned_subject WHERE ID = ?");
        if (!$stmt) return false;
        $stmt->bind_param("i", $assignmentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $teacherId = (int)$row['teacher_id'];
            $sectionId = (int)$row['section_id'];
            $assignmentSchoolYear = $row['school_year'];
            $stmt->close();
            
            // Get all MAPEH component IDs
            $componentNames = ['Music', 'Arts', 'Physical Education', 'Health'];
            $componentIds = [];
            
            foreach ($componentNames as $component) {
                $stmt = $conn->prepare("SELECT SubjectID FROM subject WHERE SubjectName = ? LIMIT 1");
                if (!$stmt) continue;
                $stmt->bind_param("s", $component);
                $stmt->execute();
                $compResult = $stmt->get_result();
                if ($compResult && $compResult->num_rows > 0) {
                    $compRow = $compResult->fetch_assoc();
                    $componentIds[] = (int)$compRow['SubjectID'];
                }
                $stmt->close();
            }
            
            // Remove all MAPEH components for this teacher and section
            $success = true;
            foreach ($componentIds as $compId) {
                $stmt = $conn->prepare("
                    DELETE FROM assigned_subject 
                    WHERE teacher_id = ? AND subject_id = ? AND section_id = ? AND school_year = ?
                ");
                if (!$stmt) {
                    $success = false;
                    continue;
                }
                $stmt->bind_param("iiis", $teacherId, $compId, $sectionId, $assignmentSchoolYear);
                if (!$stmt->execute()) {
                    $success = false;
                }
                $stmt->close();
            }
            return $success;
        }
        $stmt->close();
        return false;
    } else {
        // Regular subject removal
        $stmt = $conn->prepare("DELETE FROM assigned_subject WHERE ID = ?");
        if (!$stmt) return false;
        $stmt->bind_param("i", $assignmentId);
        $ok = $stmt->execute();
        $stmt->close();
        return $ok;
    }
}

/**
 * Check if MAPEH components exist
 */
function checkMapehComponents($conn) {
    $components = ['Music', 'Arts', 'Physical Education', 'Health'];
    $existing = [];
    
    foreach ($components as $component) {
        $stmt = $conn->prepare("SELECT SubjectID FROM subject WHERE SubjectName = ? LIMIT 1");
        $stmt->bind_param("s", $component);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $existing[] = $component;
        }
        $stmt->close();
    }
    
    return $existing;
}

/**
 * Get MAPEH component IDs
 */
function getMapehComponentIds($conn) {
    $components = ['Music', 'Arts', 'Physical Education', 'Health'];
    $ids = [];
    
    foreach ($components as $component) {
        $stmt = $conn->prepare("SELECT SubjectID FROM subject WHERE SubjectName = ? LIMIT 1");
        $stmt->bind_param("s", $component);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $ids[] = $row['SubjectID'];
        }
        $stmt->close();
    }
    
    return $ids;
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
        $subjectName = trim($_POST['assign_subject_name'] ?? '');
        $sectionId = intval($_POST['section_id'] ?? 0);
        
        // Validate required fields - subjectId can be 0 for MAPEH
        if (!$teacherId || !$sectionId || (empty($subjectName) && !$subjectId)) {
            $_SESSION['error'] = "Please select a teacher, section, and subject.";
        } elseif (empty($active_school_year)) {
            $_SESSION['error'] = "No active school year is set.";
        } elseif (!assignSubjectToTeacher($conn, $teacherId, $subjectId, $sectionId, $active_school_year, $subjectName)) {
            $_SESSION['error'] = "Failed to assign subject to teacher. Please check that the subject exists.";
        } else {
            $_SESSION['message'] = "Subject assigned to teacher successfully. " . 
                                  (strtoupper($subjectName) == 'MAPEH' ? "All 4 MAPEH components (Music, Arts, PE, Health) have been assigned." : "");
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }

    // Remove Assignment
    if (isset($_POST['remove_assignment'])) {
        $assignmentId = intval($_POST['assignment_id'] ?? 0);
        $subjectName = trim($_POST['subject_name'] ?? '');
        $subjectId = intval($_POST['subject_id'] ?? 0);
        $schoolYear = trim($_POST['school_year'] ?? '');
        
        if (!$assignmentId) {
            $_SESSION['error'] = "Invalid assignment ID.";
        } elseif (!removeSubjectAssignment($conn, $assignmentId, $subjectId, $subjectName, $schoolYear)) {
            $_SESSION['error'] = "Failed to remove assignment. Please try again.";
        } else {
            $_SESSION['message'] = "Assignment removed successfully." . 
                                  (in_array($subjectName, ['Music', 'Arts', 'Physical Education', 'Health']) ? 
                                  " All MAPEH components have been removed." : "");
        }
        header("Location: " . $_SERVER['REQUEST_URI']);
        exit;
    }
}

// Get MAPEH components status
$mapehComponents = checkMapehComponents($conn);
$mapehComponentIds = getMapehComponentIds($conn);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Manage Subjects & Assignments</title>
  <link rel="icon" type="image/png" href="../img/logo.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
    .mapeh-subject {
        background-color: #f0f8ff;
        border-left: 4px solid #007bff;
    }
    .mapeh-badge {
        font-size: 0.7em;
        background-color: #007bff;
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
            <h5 class="mb-0">Subjects List</h5>
            <div>
              <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addSubjectModal">
                Add Subject
              </button>
            </div>
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
                  // Get all subjects except MAPEH components, but include a virtual MAPEH entry
                  $subjects = $conn->query("
                    SELECT 
                      SubjectID,
                      SubjectName,
                      written_work_percentage,
                      performance_task_percentage,
                      quarterly_assessment_percentage
                    FROM subject
                    WHERE SubjectName NOT IN ('Music', 'Arts', 'Physical Education', 'Health')
                    ORDER BY SubjectName
                  ");
                  
                  // Display regular subjects first
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
                  
                  <!-- Virtual MAPEH Entry -->
                  <?php if (count($mapehComponents) > 0): ?>
                  <tr class="mapeh-subject">
                    <td>
                      <strong>MAPEH</strong>
                      <span class="badge mapeh-badge">Includes 4 components</span>
                    </td>
                    <td class="d-flex gap-1">
                      <button 
                        class="btn btn-info btn-sm"
                        data-bs-toggle="modal" 
                        data-bs-target="#viewMapehModal"
                      >
                        View Components
                      </button>

                      <button 
                        class="btn btn-primary btn-sm"
                        data-bs-toggle="modal" 
                        data-bs-target="#assignSubjectModal"
                        data-subject-id="0"
                        data-subject-name="MAPEH"
                      >
                        Assign MAPEH
                      </button>
                    </td>
                  </tr>
                  <?php endif; ?>
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
                  // Get all assignments
                  $assignments = $conn->query("
                    SELECT 
                      a.ID as assignment_id,
                      CONCAT(t.fName, ' ', t.lName) as teacher_name,
                      s.SubjectName,
                      s.SubjectID,
                      sec.SectionName,
                      a.school_year
                    FROM assigned_subject a
                    INNER JOIN teacher t ON a.teacher_id = t.TeacherID
                    INNER JOIN subject s ON a.subject_id = s.SubjectID
                    INNER JOIN section sec ON a.section_id = sec.SectionID
                    WHERE a.school_year = '" . mysqli_real_escape_string($conn, $active_school_year) . "'
                    ORDER BY t.lName, t.fName, 
                      CASE 
                        WHEN s.SubjectName IN ('Music', 'Arts', 'Physical Education', 'Health') THEN 1
                        ELSE 0
                      END,
                      s.SubjectName
                  ");
                  
                  if ($assignments && $assignments->num_rows > 0):
                    while ($assignment = $assignments->fetch_assoc()):
                      $isMapehComponent = in_array($assignment['SubjectName'], ['Music', 'Arts', 'Physical Education', 'Health']);
                  ?>
                  <tr class="<?= $isMapehComponent ? 'mapeh-subject' : '' ?>">
                    <td><?= htmlspecialchars($assignment['teacher_name']) ?></td>
                    <td>
                      <?= htmlspecialchars($assignment['SubjectName']) ?>
                      <?php if ($isMapehComponent): ?>
                        <span class="badge mapeh-badge">MAPEH Component</span>
                      <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($assignment['SectionName']) ?></td>
                    <td><?= htmlspecialchars($assignment['school_year']) ?></td>
                    <td>
                      <form method="post" class="d-inline">
                        <input type="hidden" name="assignment_id" value="<?= $assignment['assignment_id'] ?>">
                        <input type="hidden" name="subject_id" value="<?= $assignment['SubjectID'] ?>">
                        <input type="hidden" name="subject_name" value="<?= htmlspecialchars($assignment['SubjectName']) ?>">
                        <input type="hidden" name="school_year" value="<?= htmlspecialchars($assignment['school_year']) ?>">
                        <button type="submit" name="remove_assignment" class="btn btn-sm btn-danger" 
                                onclick="return confirm('Are you sure you want to remove this assignment? <?= $isMapehComponent ? "This will remove ALL MAPEH components for this teacher and section." : "" ?>')">
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
            <small class="text-muted">Note: To add MAPEH components, use names: Music, Arts, Physical Education, Health</small>
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

  <!-- View MAPEH Components Modal -->
  <div class="modal fade" id="viewMapehModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">MAPEH Components</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info">
            <i class="fas fa-info-circle me-2"></i>
            MAPEH consists of 4 separate subjects that are assigned together:
          </div>
          
          <table class="table table-bordered">
            <thead class="table-light">
              <tr>
                <th>Component</th>
                <th>Written Work</th>
                <th>Performance Task</th>
                <th>Quarterly Assessment</th>
              </tr>
            </thead>
            <tbody>
              <?php
              foreach ($mapehComponents as $component):
                $stmt = $conn->prepare("
                  SELECT written_work_percentage, performance_task_percentage, quarterly_assessment_percentage 
                  FROM subject WHERE SubjectName = ? LIMIT 1
                ");
                $stmt->bind_param("s", $component);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($result && $result->num_rows > 0):
                  $row = $result->fetch_assoc();
                  $ww = $row['written_work_percentage'] * 100;
                  $pt = $row['performance_task_percentage'] * 100;
                  $qa = $row['quarterly_assessment_percentage'] * 100;
              ?>
              <tr>
                <td><?= htmlspecialchars($component) ?></td>
                <td><?= $ww ?>%</td>
                <td><?= $pt ?>%</td>
                <td><?= $qa ?>%</td>
              </tr>
              <?php 
                endif;
                $stmt->close();
              endforeach; 
              ?>
            </tbody>
          </table>
          
          <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle me-2"></i>
            <strong>Important:</strong> When you assign "MAPEH", all 4 components will be automatically assigned to the teacher.
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
          <input type="hidden" name="assign_subject_name" id="assign-subject-name">
          <div class="mb-3">
            <label class="form-label">Subject</label>
            <input type="text" id="assign-subject-display-name" class="form-control" readonly>
            <div id="mapeh-warning" class="alert alert-info mt-2" style="display: none;">
              <i class="fas fa-info-circle me-2"></i>
              This will assign all 4 MAPEH components: Music, Arts, Physical Education, and Health.
            </div>
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
      const subjectName = btn.data('subject-name');
      const subjectId = btn.data('subject-id');
      
      $('#assign-subject-id').val(subjectId);
      $('#assign-subject-name').val(subjectName);
      $('#assign-subject-display-name').val(subjectName);
      
      // Show MAPEH warning if assigning MAPEH
      if (subjectName.toUpperCase() === 'MAPEH') {
        $('#mapeh-warning').show();
      } else {
        $('#mapeh-warning').hide();
      }
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