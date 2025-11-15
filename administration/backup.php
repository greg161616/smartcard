<?php
session_start();
require __DIR__ . '/../config.php';
require __DIR__ . '/../api/log_helper.php';

if (!isset($_SESSION['email']) || $_SESSION['role'] !== 'head') {
    header('Location: ../login.php');
    exit;
}

// Export database as SQL
function export_database($conn) {
    $dbname = mysqli_real_escape_string($conn, mysqli_fetch_row(mysqli_query($conn, "SELECT DATABASE()"))[0]);
    $sqlOutput = "-- Database backup for: {$dbname}\n-- Generated: " . date('Y-m-d H:i:s') . "\n\n";

    // Get list of tables
    $tablesRes = mysqli_query($conn, "SHOW TABLES");
    $tables = [];
    while ($row = mysqli_fetch_row($tablesRes)) {
        $tables[] = $row[0];
    }

    foreach ($tables as $table) {
        // Add DROP TABLE
        $sqlOutput .= "DROP TABLE IF EXISTS `{$table}`;\n";

        // CREATE TABLE
        $createRes = mysqli_query($conn, "SHOW CREATE TABLE `{$table}`");
        $createRow = mysqli_fetch_assoc($createRes);
        $sqlOutput .= $createRow['Create Table'] . ";\n\n";

        // INSERTS
        $rowsRes = mysqli_query($conn, "SELECT * FROM `{$table}`");
        $numRows = mysqli_num_rows($rowsRes);
        if ($numRows > 0) {
            $fields = array_keys(mysqli_fetch_assoc($rowsRes));
            // reset pointer after fetch
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

// Handle export request
if (isset($_POST['action']) && $_POST['action'] === 'export') {
    $dump = export_database($conn);
    $filename = 'smartcard_backup_' . date('Ymd_His') . '.sql';
    header('Content-Type: application/sql; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    // Log
    log_system_action($conn, 'backup_export', $_SESSION['user_id'] ?? null, ['filename' => $filename], 'info');
    echo $dump;
    exit;
}

// Handle restore request
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
            // Execute SQL. Use multi_query to allow multiple statements
            mysqli_autocommit($conn, false);
            if (mysqli_multi_query($conn, $content)) {
                do {
                    /* flush multi_queries */
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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<?php include __DIR__ . '/../navs/headNav.php'; ?>
<div class="container mt-5">
  <h2>Database Backup & Restore</h2>
  <?php if (!empty($restoreMessage)): ?>
    <div class="alert alert-info"><?php echo htmlspecialchars($restoreMessage); ?></div>
  <?php endif; ?>

  <div class="row">
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

    <div class="col-md-6">
      <div class="card mb-3">
        <div class="card-header">Import (Restore)</div>
        <div class="card-body">
          <p>Upload a previously exported .sql file to restore the database. This will run the SQL in the file and may overwrite data.</p>
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

  <div class="mt-3">
    <small class="text-muted">Important: Restoring will execute SQL from the uploaded file. Ensure the file is trusted. Backups are stored client-side only unless you save them to server.</small>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
