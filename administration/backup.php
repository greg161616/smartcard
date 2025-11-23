<?php
session_start();
require __DIR__ . '/../config.php';
require __DIR__ . '/../api/log_helper.php';

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'head') {
    header('Location: ../login.php');
    exit;
}

// Function to handle school year reset
function handle_school_year_reset($conn) {
    $message = '';
    $message_type = 'danger'; // danger for error, success for success
    
    if (isset($_POST['action']) && $_POST['action'] === 'reset_school_year') {
        $school_name = trim($_POST['school_name']);
        $school_year = trim($_POST['school_year']);
        
        // Validate school year format (YYYY-YYYY)
        if (!preg_match('/^\d{4}-\d{4}$/', $school_year)) {
            $message = 'Invalid school year format. Please use format: YYYY-YYYY (e.g., 2025-2026)';
        } elseif (empty($school_name)) {
            $message = 'School name is required.';
        } else {
            mysqli_autocommit($conn, false);
            
            try {
                // Set all existing school years to inactive
                $update_stmt = mysqli_prepare($conn, "UPDATE school_year SET status = 'inactive'");
                if (!$update_stmt || !mysqli_stmt_execute($update_stmt)) {
                    throw new Exception('Failed to deactivate previous school years: ' . mysqli_error($conn));
                }
                mysqli_stmt_close($update_stmt);
                
                // Check if school year already exists
                $check_stmt = mysqli_prepare($conn, "SELECT ID FROM school_year WHERE school_year = ?");
                mysqli_stmt_bind_param($check_stmt, 's', $school_year);
                mysqli_stmt_execute($check_stmt);
                mysqli_stmt_store_result($check_stmt);
                
                if (mysqli_stmt_num_rows($check_stmt) > 0) {
                    // Update existing school year
                    $update_stmt = mysqli_prepare($conn, "UPDATE school_year SET school_name = ?, status = 'active' WHERE school_year = ?");
                    mysqli_stmt_bind_param($update_stmt, 'ss', $school_name, $school_year);
                } else {
                    // Insert new school year
                    $insert_stmt = mysqli_prepare($conn, "INSERT INTO school_year (school_name, school_year, status) VALUES (?, ?, 'active')");
                    mysqli_stmt_bind_param($insert_stmt, 'ss', $school_name, $school_year);
                    $update_stmt = $insert_stmt;
                }
                mysqli_stmt_close($check_stmt);
                
                if (!$update_stmt || !mysqli_stmt_execute($update_stmt)) {
                    throw new Exception('Failed to set new school year: ' . mysqli_error($conn));
                }
                
                mysqli_commit($conn);
                $message = 'School year successfully reset to ' . $school_year . '!';
                $message_type = 'success';
                
                // Log the action
                log_system_action($conn, 'school_year_reset', $_SESSION['user_id'] ?? null, 
                    ['school_name' => $school_name, 'school_year' => $school_year], 'info');
                
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $message = $e->getMessage();
                log_system_action($conn, 'school_year_reset_failed', $_SESSION['user_id'] ?? null, 
                    ['error' => $e->getMessage()], 'error');
            }
            
            mysqli_autocommit($conn, true);
            if (isset($update_stmt)) mysqli_stmt_close($update_stmt);
        }
    }
    
    return ['message' => $message, 'type' => $message_type];
}

// Get current active school year info
function get_current_school_info($conn) {
    $school_name = '';
    $school_year = '';
    
    $stmt = mysqli_prepare($conn, "SELECT school_name, school_year FROM school_year WHERE status = 'active' LIMIT 1");
    if ($stmt && mysqli_stmt_execute($stmt)) {
        mysqli_stmt_bind_result($stmt, $current_school_name, $current_school_year);
        if (mysqli_stmt_fetch($stmt)) {
            $school_name = $current_school_name;
            $school_year = $current_school_year;
        }
        mysqli_stmt_close($stmt);
    }
    
    return ['school_name' => $school_name, 'school_year' => $school_year];
}

// Handle form submissions
$reset_result = handle_school_year_reset($conn);
$current_school = get_current_school_info($conn);

// Export database as SQL (existing function)
function export_database($conn) {
    $dbname = mysqli_real_escape_string($conn, mysqli_fetch_row(mysqli_query($conn, "SELECT DATABASE()"))[0]);
    $sqlOutput = "-- Database backup for: {$dbname}\n-- Generated: " . date('Y-m-d H:i:s') . "\n\n";

    $tablesRes = mysqli_query($conn, "SHOW TABLES");
    $tables = [];
    while ($row = mysqli_fetch_row($tablesRes)) {
        $tables[] = $row[0];
    }

    foreach ($tables as $table) {
        $sqlOutput .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $createRes = mysqli_query($conn, "SHOW CREATE TABLE `{$table}`");
        $createRow = mysqli_fetch_assoc($createRes);
        $sqlOutput .= $createRow['Create Table'] . ";\n\n";

        $rowsRes = mysqli_query($conn, "SELECT * FROM `{$table}`");
        $numRows = mysqli_num_rows($rowsRes);
        if ($numRows > 0) {
            $fields = array_keys(mysqli_fetch_assoc($rowsRes));
            mysqli_data_seek($rowsRes, 0);
            while ($r = mysqli_fetch_assoc($rowsRes)) {
                $vals = array_map(function($v) use ($conn) {
                    if ($v === null) return 'NULL';
                    return "'" . mysqli_real_escape_string($conn, $v) . "'";
                }, array_values($r));
                $sqlOutput .= "INSERT INTO `{$table}` (`" . implode('`,`', array_keys($r)) . "`) VALUES (" . implode(',', $vals) . ");\n";
            }
            $sqlOutput .= "\n";
        }
    }

    return $sqlOutput;
}

// Handle export request (existing code)
if (isset($_POST['action']) && $_POST['action'] === 'export') {
    $dump = export_database($conn);
    $filename = 'smartcard_backup_' . date('Ymd_His') . '.sql';
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    log_system_action($conn, 'backup_export', $_SESSION['user_id'] ?? null, ['filename' => $filename], 'info');
    echo $dump;
    exit;
}

// Handle restore request (existing code)
$restoreMessage = '';
if (isset($_POST['action']) && $_POST['action'] === 'restore') {
    if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
        $restoreMessage = 'No file uploaded or upload error.';
        log_system_action($conn, 'backup_restore_failed', $_SESSION['user_id'] ?? null, ['reason' => $restoreMessage], 'error');
    } else {
        $tmpPath = $_FILES['sql_file']['tmp_name'];
        $content = file_get_contents($tmpPath);
        if ($content === false) {
            $restoreMessage = 'Failed to read uploaded file.';
            log_system_action($conn, 'backup_restore_failed', $_SESSION['user_id'] ?? null, ['reason' => $restoreMessage], 'error');
        } else {
            mysqli_autocommit($conn, false);
            if (mysqli_multi_query($conn, $content)) {
                do {
                    if ($res = mysqli_store_result($conn)) {
                        mysqli_free_result($res);
                    }
                } while (mysqli_more_results($conn) && mysqli_next_result($conn));
                if (mysqli_errno($conn) === 0) {
                    mysqli_commit($conn);
                    $restoreMessage = 'Restore completed successfully.';
                    log_system_action($conn, 'backup_restore_success', $_SESSION['user_id'] ?? null, ['file' => $_FILES['sql_file']['name']], 'info');
                } else {
                    $err = mysqli_error($conn);
                    mysqli_rollback($conn);
                    $restoreMessage = 'Restore failed: ' . $err;
                    log_system_action($conn, 'backup_restore_failed', $_SESSION['user_id'] ?? null, ['error' => $err], 'error');
                }
            } else {
                $err = mysqli_error($conn);
                mysqli_rollback($conn);
                $restoreMessage = 'Restore failed: ' . $err;
                log_system_action($conn, 'backup_restore_failed', $_SESSION['user_id'] ?? null, ['error' => $err], 'error');
            }
            mysqli_autocommit($conn, true);
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Backup & Restore</title>
  <link rel="icon" type="image/png" href="../img/logo.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/../navs/headNav.php'; ?>
<div class="container mt-5">
  <h2>Database Management</h2>
  
  <!-- Display messages -->
  <?php if (!empty($reset_result['message'])): ?>
    <div class="alert alert-<?php echo $reset_result['type']; ?>"><?php echo htmlspecialchars($reset_result['message']); ?></div>
  <?php endif; ?>
  
  <?php if (!empty($restoreMessage)): ?>
    <div class="alert alert-info"><?php echo htmlspecialchars($restoreMessage); ?></div>
  <?php endif; ?>

  <div class="row">
    <!-- Reset School Year Card -->
    <div class="col-md-6">
      <div class="card mb-3">
        <div class="card-header">Reset School Year</div>
        <div class="card-body">
          <p>Reset the current school year. This will set all other school years to inactive.</p>
          <p><strong>Current Active:</strong> 
            <?php if (!empty($current_school['school_name'])): ?>
              <?php echo htmlspecialchars($current_school['school_name']); ?> - <?php echo htmlspecialchars($current_school['school_year']); ?>
            <?php else: ?>
              No active school year set
            <?php endif; ?>
          </p>
          <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#resetSchoolYearModal">
            Reset School Year
          </button>
        </div>
      </div>
    </div>

    <!-- Export Card -->
    <div class="col-md-6">
      <div class="card mb-3">
        <div class="card-header">Export (Backup)</div>
        <div class="card-body">
          <p>Click the button to download a full SQL dump of the database.</p>
          <form method="post">
            <input type="hidden" name="action" value="export">
            <button type="submit" class="btn btn-primary">Download Backup</button>
          </form>
        </div>
      </div>
    </div>

    <!-- Import Card -->
    <div class="col-md-6">
      <div class="card mb-3">
        <div class="card-header">Import (Restore)</div>
        <div class="card-body">
          <p>Upload a previously exported .sql file to restore the database.</p>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="restore">
            <div class="mb-3">
              <input type="file" name="sql_file" accept=".sql" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-danger">Restore from SQL</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Reset School Year Modal -->
  <div class="modal fade" id="resetSchoolYearModal" tabindex="-1" aria-labelledby="resetSchoolYearModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="resetSchoolYearModalLabel">Reset School Year</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="resetSchoolYearForm" method="post">
          <div class="modal-body">
            <input type="hidden" name="action" value="reset_school_year">
            <div class="mb-3">
              <label for="school_name" class="form-label">School Name *</label>
              <input type="text" class="form-control" id="school_name" name="school_name" 
                     value="<?php echo htmlspecialchars($current_school['school_name']); ?>" 
                     placeholder="Enter school name" required>
            </div>
            <div class="mb-3">
              <label for="school_year" class="form-label">School Year *</label>
              <input type="text" class="form-control" id="school_year" name="school_year" 
                     value="<?php echo htmlspecialchars($current_school['school_year']); ?>" 
                     placeholder="YYYY-YYYY (e.g., 2025-2026)" pattern="\d{4}-\d{4}" required>
              <div class="form-text">Format: YYYY-YYYY (e.g., 2025-2026)</div>
            </div>
            <div class="mb-3">
              <label for="admin_password" class="form-label">Admin Password *</label>
              <input type="password" class="form-control" id="admin_password" name="admin_password" placeholder="Enter your password" required>
            </div>
            <div class="alert alert-warning">
              <strong>Warning:</strong> This will set all other school years to inactive. Only one school year can be active at a time.
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="button" class="btn btn-warning" id="confirmResetBtn">Reset School Year</button>
          </div>
        </form>
        <!-- Confirmation Modal -->
        <div class="modal fade" id="confirmResetModal" tabindex="-1" aria-labelledby="confirmResetModalLabel" aria-hidden="true">
          <div class="modal-dialog">
            <div class="modal-content">
              <div class="modal-header">
                <h5 class="modal-title" id="confirmResetModalLabel">Confirm School Year Reset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
              </div>
              <div class="modal-body">
                Are you sure you want to reset the school year? This action cannot be undone and will set all other school years to inactive.
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-warning" id="finalConfirmBtn">Yes, Reset</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="mt-3">
    <small class="text-muted">Important: Always backup your data before making changes. Restoring will execute SQL from the uploaded file.</small>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
  // Show confirmation modal before submitting reset form
  document.getElementById('confirmResetBtn').addEventListener('click', function(e) {
    e.preventDefault();
    var resetModal = new bootstrap.Modal(document.getElementById('confirmResetModal'));
    resetModal.show();
  });

  document.getElementById('finalConfirmBtn').addEventListener('click', function() {
    document.getElementById('resetSchoolYearForm').submit();
  });
</script>
</body>
</html>