<?php
session_start();
include '../config.php';
include '../api/logging.php';

// Check if user is admin
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? null;

if (!$userId || $userRole !== 'head') {
    header('Location: ../login.php');
    exit;
}

$logger = new Logger($conn);

// Handle filters
$filters = [];
if (isset($_GET['action']) && !empty($_GET['action'])) $filters['action'] = $_GET['action'];
if (isset($_GET['user_id']) && !empty($_GET['user_id'])) $filters['user_id'] = (int)$_GET['user_id'];
if (isset($_GET['level']) && !empty($_GET['level'])) $filters['level'] = $_GET['level'];
if (isset($_GET['date_from']) && !empty($_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
if (isset($_GET['date_to']) && !empty($_GET['date_to'])) $filters['date_to'] = $_GET['date_to'];

// Get logs with filters
$logs = $logger->getLogs($filters);

// Get unique actions for filter
$actionsResult = mysqli_query($conn, "SELECT DISTINCT action FROM system_logs ORDER BY action");
$actions = [];
while ($row = mysqli_fetch_assoc($actionsResult)) {
    $actions[] = $row['action'];
}

// Get unique log levels for filter
$levelsResult = mysqli_query($conn, "SELECT DISTINCT log_level FROM system_logs ORDER BY log_level");
$levels = [];
while ($row = mysqli_fetch_assoc($levelsResult)) {
    $levels[] = $row['log_level'];
}

// Get users for filter (teachers, admins, students)
$users = [];
$usersQuery = "
    SELECT u.UserID, u.Email, u.Role, 
           COALESCE(t.fName, a.FullName, s.FirstName) as first_name,
           COALESCE(t.lName, '', s.LastName) as last_name
    FROM user u
    LEFT JOIN teacher t ON u.UserID = t.userID
    LEFT JOIN admin a ON u.UserID = a.UserID
    LEFT JOIN student s ON u.UserID = s.userID
    WHERE u.UserID IN (SELECT DISTINCT user_id FROM system_logs WHERE user_id IS NOT NULL)
    ORDER BY first_name, last_name
";
$usersResult = mysqli_query($conn, $usersQuery);
while ($row = mysqli_fetch_assoc($usersResult)) {
    $users[$row['UserID']] = [
        'name' => trim($row['first_name'] . ' ' . $row['last_name']),
        'email' => $row['Email'],
        'role' => $row['Role']
    ];
}

// Get counts for statistics
$totalLogs = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as total FROM system_logs"))['total'];
$filteredCount = count($logs);

// Build filter display string
$filterDisplay = [];
if (!empty($_GET['action'])) $filterDisplay[] = "Action: " . htmlspecialchars($_GET['action']);
if (!empty($_GET['user_id'])) {
    $userName = isset($users[$_GET['user_id']]) ? $users[$_GET['user_id']]['name'] : 'User #' . $_GET['user_id'];
    $filterDisplay[] = "User: " . htmlspecialchars($userName);
}
if (!empty($_GET['level'])) $filterDisplay[] = "Level: " . htmlspecialchars($_GET['level']);
if (!empty($_GET['date_from'])) $filterDisplay[] = "From: " . htmlspecialchars($_GET['date_from']);
if (!empty($_GET['date_to'])) $filterDisplay[] = "To: " . htmlspecialchars($_GET['date_to']);
$filterString = implode(' • ', $filterDisplay);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Logs</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .log-info { background-color: #e8f4f8; }
        .log-success { background-color: #e8f5e8; }
        .log-warning { background-color: #fff9e6; }
        .log-error { background-color: #fde8e8; }
        
        .log-table { font-size: 14px; }
        .filter-card { margin-bottom: 20px; }
        .stats-card { text-align: center; padding: 15px; }
        .stats-value { font-size: 24px; font-weight: bold; }
        .filter-badge { 
            background-color: #6c757d; 
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
        }
        .details-popup {
            max-width: 500px;
            word-wrap: break-word;
        }
        
        @media print {
            .filter-card, .btn { display: none; }
        }
    </style>
</head>
<body>
    <?php include '../navs/headNav.php'; ?>
    
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <h2><i class="fas fa-file-alt"></i> System Logs</h2>
                
                <!-- Filter Display -->
                <?php if (!empty($filterDisplay)): ?>
                <div class="alert alert-info py-2">
                    <strong>Active Filters:</strong> <?php echo $filterString; ?>
                    <a href="view_logs.php" class="btn btn-sm btn-outline-danger ms-2">
                        <i class="fas fa-times"></i> Clear All
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="row mb-3">
            <div class="col-md-3">
                <div class="card stats-card bg-light">
                    <div class="text-muted">Total Logs</div>
                    <div class="stats-value text-primary"><?php echo number_format($totalLogs); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-light">
                    <div class="text-muted">Filtered Results</div>
                    <div class="stats-value text-info"><?php echo number_format($filteredCount); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-light">
                    <div class="text-muted">Unique Actions</div>
                    <div class="stats-value text-success"><?php echo number_format(count($actions)); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-light">
                    <div class="text-muted">Unique Users</div>
                    <div class="stats-value text-warning"><?php echo number_format(count($users)); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="card filter-card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-filter"></i> Filter Logs</h5>
            </div>
            <div class="card-body">
                <form method="GET" action="">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Action</label>
                            <select name="action" class="form-select">
                                <option value="">All Actions</option>
                                <?php foreach ($actions as $action): ?>
                                    <option value="<?php echo htmlspecialchars($action); ?>" 
                                        <?php echo (isset($_GET['action']) && $_GET['action'] === $action) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($action); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label class="form-label">User</label>
                            <select name="user_id" class="form-select">
                                <option value="">All Users</option>
                                <?php foreach ($users as $id => $user): ?>
                                    <option value="<?php echo $id; ?>" 
                                        <?php echo (isset($_GET['user_id']) && $_GET['user_id'] == $id) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($user['name'] ?: $user['email']); ?> 
                                        (<?php echo ucfirst($user['role']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Log Level</label>
                            <select name="level" class="form-select">
                                <option value="">All Levels</option>
                                <?php foreach ($levels as $level): ?>
                                    <option value="<?php echo htmlspecialchars($level); ?>" 
                                        <?php echo (isset($_GET['level']) && $_GET['level'] === $level) ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($level); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Date From</label>
                            <input type="date" name="date_from" class="form-control" 
                                   value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-2">
                            <label class="form-label">Date To</label>
                            <input type="date" name="date_to" class="form-control" 
                                   value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-12">
                            <hr>
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Apply Filters
                                </button>
                                <a href="view_logs.php" class="btn btn-secondary">
                                    <i class="fas fa-redo"></i> Reset
                                </a>
                                <button type="button" class="btn btn-success" onclick="exportLogs()">
                                    <i class="fas fa-download"></i> Export CSV
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Logs Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list"></i> Log Entries 
                    <span class="badge bg-primary ms-2"><?php echo $filteredCount; ?> records</span>
                </h5>
                <div>
                    <button onclick="window.print()" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-print"></i> Print
                    </button>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover log-table mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>Level</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i><br>
                                        No logs found matching your filters
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr class="log-<?php echo $log['log_level']; ?>">
                                        <td><small><?php echo $log['log_id']; ?></small></td>
                                        <td>
                                            <small>
                                                <?php echo date('M j, Y', strtotime($log['created_at'])); ?><br>
                                                <?php echo date('g:i A', strtotime($log['created_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php 
                                            // Determine user type and display name
                                            $displayName = 'Unknown User';
                                            $userRole = '';
                                            
                                            if (!empty($log['teacher_fname']) && !empty($log['teacher_lname'])) {
                                                $displayName = htmlspecialchars($log['teacher_fname'] . ' ' . $log['teacher_lname']);
                                                $userRole = 'Teacher';
                                            } elseif (!empty($log['admin_name'])) {
                                                $displayName = htmlspecialchars($log['admin_name']);
                                                $userRole = 'Admin';
                                            } elseif (!empty($log['FirstName']) && !empty($log['LastName'])) {
                                                $displayName = htmlspecialchars($log['FirstName'] . ' ' . $log['LastName']);
                                                $userRole = 'Student';
                                            } elseif (!empty($log['Email'])) {
                                                $displayName = htmlspecialchars($log['Email']);
                                                $userRole = ucfirst($log['Role'] ?? 'User');
                                            }
                                            
                                            echo '<div><strong>' . $displayName . '</strong></div>';
                                            if ($userRole) {
                                                echo '<small class="filter-badge">' . $userRole . '</small>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($log['action']); ?></strong>
                                        </td>
                                        <td>
                                            <?php 
                                            $details = json_decode($log['details'], true);
                                            if ($details && is_array($details)) {
                                                echo '<button type="button" class="btn btn-sm btn-outline-info" data-bs-toggle="modal" data-bs-target="#detailsModal' . $log['log_id'] . '">';
                                                echo '<i class="fas fa-eye"></i> View Details';
                                                echo '</button>';
                                                
                                                // Modal for details
                                                echo '
                                                <div class="modal fade" id="detailsModal' . $log['log_id'] . '" tabindex="-1">
                                                    <div class="modal-dialog modal-lg">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Details: ' . htmlspecialchars($log['action']) . '</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <pre class="details-popup"><code>' . htmlspecialchars(json_encode($details, JSON_PRETTY_PRINT)) . '</code></pre>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>';
                                            } else {
                                                echo '<small class="text-muted">' . htmlspecialchars($log['details']) . '</small>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $log['log_level']; ?>">
                                                <i class="fas fa-<?php 
                                                    switch($log['log_level']) {
                                                        case 'success': echo 'check'; break;
                                                        case 'error': echo 'exclamation-triangle'; break;
                                                        case 'warning': echo 'exclamation-circle'; break;
                                                        default: echo 'info';
                                                    }
                                                ?>"></i>
                                                <?php echo strtoupper($log['log_level']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Form (hidden) -->
    <form id="exportForm" method="POST" action="export_logs.php" style="display: none;">
        <input type="hidden" name="action" value="<?php echo htmlspecialchars($_GET['action'] ?? ''); ?>">
        <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($_GET['user_id'] ?? ''); ?>">
        <input type="hidden" name="level" value="<?php echo htmlspecialchars($_GET['level'] ?? ''); ?>">
        <input type="hidden" name="date_from" value="<?php echo htmlspecialchars($_GET['date_from'] ?? ''); ?>">
        <input type="hidden" name="date_to" value="<?php echo htmlspecialchars($_GET['date_to'] ?? ''); ?>">
    </form>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function exportLogs() {
            document.getElementById('exportForm').submit();
        }

        // Auto-submit form when date inputs change (optional)
        document.addEventListener('DOMContentLoaded', function() {
            const dateInputs = document.querySelectorAll('input[type="date"]');
            dateInputs.forEach(input => {
                input.addEventListener('change', function() {
                });
            });
        });
    </script>
</body>
</html>