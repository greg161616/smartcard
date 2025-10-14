<?php 
include '../config.php';

// Handle AJAX requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'get_logs':
            echo getLogs();
            exit;
        case 'clear_logs':
            echo clearLogs();
            exit;
    }
}

// Function to get logs with filters
function getLogs() {
    global $conn;
    
    // Create logs table if it doesn't exist
    createLogsTable();
    
    // Get filter parameters
    $logType = $_GET['logType'] ?? '';
    $userRole = $_GET['userRole'] ?? '';
    $dateFrom = $_GET['dateFrom'] ?? '';
    $dateTo = $_GET['dateTo'] ?? '';
    $search = $_GET['search'] ?? '';
    $logLevel = $_GET['logLevel'] ?? '';

    // Build query
    $query = "SELECT l.*, u.Email as user_email, u.Role as user_role 
              FROM system_logs l 
              LEFT JOIN user u ON l.user_id = u.UserID 
              WHERE 1=1";

    $params = [];
    $types = '';

    if ($logType) {
        $query .= " AND l.log_type = ?";
        $params[] = $logType;
        $types .= 's';
    }

    if ($userRole) {
        $query .= " AND u.Role = ?";
        $params[] = $userRole;
        $types .= 's';
    }

    if ($dateFrom) {
        $query .= " AND DATE(l.timestamp) >= ?";
        $params[] = $dateFrom;
        $types .= 's';
    }

    if ($dateTo) {
        $query .= " AND DATE(l.timestamp) <= ?";
        $params[] = $dateTo;
        $types .= 's';
    }

    if ($logLevel) {
        $query .= " AND l.log_level = ?";
        $params[] = $logLevel;
        $types .= 's';
    }

    if ($search) {
        $query .= " AND (l.action LIKE ? OR l.details LIKE ? OR u.Email LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'sss';
    }

    $query .= " ORDER BY l.timestamp DESC";

    $stmt = $conn->prepare($query);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = [
            'log_id' => $row['log_id'],
            'timestamp' => $row['timestamp'],
            'user_id' => $row['user_id'],
            'user_name' => $row['user_email'],
            'user_role' => $row['user_role'],
            'log_type' => $row['log_type'],
            'action' => $row['action'],
            'details' => $row['details'],
            'ip_address' => $row['ip_address'],
            'log_level' => $row['log_level']
        ];
    }

    return json_encode($logs);
}

// Function to clear all logs
function clearLogs() {
    global $conn;
    
    // Check if user has admin privileges
    session_start();
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        return json_encode(['success' => false, 'message' => 'Unauthorized']);
    }

    $query = "TRUNCATE TABLE system_logs";
    if ($conn->query($query)) {
        return json_encode(['success' => true]);
    } else {
        return json_encode(['success' => false, 'message' => $conn->error]);
    }
}

// Function to create logs table
function createLogsTable() {
    global $conn;
    
    $query = "CREATE TABLE IF NOT EXISTS system_logs (
        log_id INT AUTO_INCREMENT PRIMARY KEY,
        timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
        user_id INT NULL,
        log_type VARCHAR(50) NOT NULL,
        action VARCHAR(100) NOT NULL,
        details TEXT NOT NULL,
        ip_address VARCHAR(45),
        log_level ENUM('info', 'warning', 'error') DEFAULT 'info',
        FOREIGN KEY (user_id) REFERENCES user(UserID) ON DELETE SET NULL
    )";
    
    $conn->query($query);
}

// Function to log actions (for use in other files)
function logAction($userId, $logType, $action, $details, $logLevel = 'info') {
    global $conn;
    
    // Create logs table if it doesn't exist
    createLogsTable();

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    $stmt = $conn->prepare("INSERT INTO system_logs (user_id, log_type, action, details, ip_address, log_level) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssss", $userId, $logType, $action, $details, $ipAddress, $logLevel);
    return $stmt->execute();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BANAHIS | System Logs</title>
    <link rel="icon" type="image/png" href="../img/logo.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .log-table {
            font-size: 0.85rem;
        }
        .badge-login { background-color: #28a745; }
        .badge-grade { background-color: #007bff; }
        .badge-attendance { background-color: #ffc107; color: #000; }
        .badge-enrollment { background-color: #6f42c1; }
        .badge-system { background-color: #6c757d; }
        .badge-announcement { background-color: #17a2b8; }
        .badge-document { background-color: #fd7e14; }
        .badge-event { background-color: #e83e8c; }
        .log-level-info { color: #17a2b8; }
        .log-level-warning { color: #ffc107; }
        .log-level-error { color: #dc3545; }
        .filter-section {
            background-color: #f8f9fa;
            border-radius: 0.375rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .table-responsive {
            max-height: 70vh;
            overflow-y: auto;
        }
        .stats-card {
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <?php include '../navs/headNav.php'; ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2><i class="fas fa-clipboard-list me-2"></i>System Logs</h2>
            <div>
                <button class="btn btn-outline-success me-2" id="exportLogsBtn">
                    <i class="fas fa-download me-1"></i>Export
                </button>
                <button class="btn btn-outline-danger" id="clearLogsBtn">
                    <i class="fas fa-trash me-1"></i>Clear Logs
                </button>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card stats-card bg-primary text-white">
                    <div class="card-body text-center">
                        <h4><i class="fas fa-sign-in-alt"></i></h4>
                        <h5 id="loginCount">0</h5>
                        <small>Logins</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card bg-success text-white">
                    <div class="card-body text-center">
                        <h4><i class="fas fa-graduation-cap"></i></h4>
                        <h5 id="gradeCount">0</h5>
                        <small>Grade Updates</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card bg-warning text-dark">
                    <div class="card-body text-center">
                        <h4><i class="fas fa-calendar-check"></i></h4>
                        <h5 id="attendanceCount">0</h5>
                        <small>Attendance</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card bg-info text-white">
                    <div class="card-body text-center">
                        <h4><i class="fas fa-bullhorn"></i></h4>
                        <h5 id="announcementCount">0</h5>
                        <small>Announcements</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card bg-danger text-white">
                    <div class="card-body text-center">
                        <h4><i class="fas fa-exclamation-triangle"></i></h4>
                        <h5 id="errorCount">0</h5>
                        <small>Errors</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card stats-card bg-secondary text-white">
                    <div class="card-body text-center">
                        <h4><i class="fas fa-database"></i></h4>
                        <h5 id="totalCount">0</h5>
                        <small>Total Logs</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters Section -->
        <div class="filter-section">
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Log Type</label>
                    <select class="form-select" id="logTypeFilter">
                        <option value="">All Types</option>
                        <option value="login">Login/Logout</option>
                        <option value="grade">Grade Management</option>
                        <option value="attendance">Attendance</option>
                        <option value="enrollment">Enrollment</option>
                        <option value="announcement">Announcements</option>
                        <option value="document">Documents</option>
                        <option value="event">Events</option>
                        <option value="system">System Events</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">User Role</label>
                    <select class="form-select" id="userRoleFilter">
                        <option value="">All Roles</option>
                        <option value="admin">Admin</option>
                        <option value="teacher">Teacher</option>
                        <option value="student">Student</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date From</label>
                    <input type="date" class="form-control" id="dateFromFilter">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Date To</label>
                    <input type="date" class="form-control" id="dateToFilter">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" class="form-control" id="searchFilter" placeholder="Search logs...">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Log Level</label>
                    <select class="form-select" id="logLevelFilter">
                        <option value="">All Levels</option>
                        <option value="info">Info</option>
                        <option value="warning">Warning</option>
                        <option value="error">Error</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Items per page</label>
                    <select class="form-select" id="itemsPerPage">
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-primary w-100 me-2" id="applyFilters">
                        <i class="fas fa-filter me-1"></i>Apply Filters
                    </button>
                    <button class="btn btn-outline-secondary" id="resetFilters" title="Reset Filters">
                        <i class="fas fa-redo"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Logs Table -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-history me-2"></i>System Activity Log</h5>
                <div>
                    <span class="badge bg-secondary me-2" id="showingLogs">Showing 0 logs</span>
                    <span class="badge bg-primary" id="totalLogs">Total: 0</span>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0 log-table">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <th>Timestamp</th>
                                <th>User</th>
                                <th>Role</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>IP Address</th>
                                <th>Level</th>
                            </tr>
                        </thead>
                        <tbody id="logsTableBody">
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    <div class="spinner-border spinner-border-sm me-2" role="status"></div>
                                    Loading logs...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pagination -->
        <nav aria-label="Logs pagination" class="mt-3">
            <ul class="pagination justify-content-center" id="paginationContainer">
                <!-- Pagination will be generated here -->
            </ul>
        </nav>
    </div>

    <!-- Clear Logs Confirmation Modal -->
    <div class="modal fade" id="clearLogsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle text-warning me-2"></i>Confirm Clear Logs</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to clear all system logs? This action cannot be undone.</p>
                    <div class="alert alert-warning mb-0">
                        <i class="fas fa-info-circle me-2"></i>
                        This will permanently delete all log records from the system.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmClearLogs">
                        <i class="fas fa-trash me-1"></i>Clear All Logs
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    $(document).ready(function() {
        let currentPage = 1;
        let logsPerPage = 20;
        let allLogs = [];

        // Initialize date filters to last 30 days
        const today = new Date();
        const thirtyDaysAgo = new Date();
        thirtyDaysAgo.setDate(today.getDate() - 30);
        
        $('#dateFromFilter').val(thirtyDaysAgo.toISOString().split('T')[0]);
        $('#dateToFilter').val(today.toISOString().split('T')[0]);

        // Load logs on page load
        loadLogs();

        // Apply filters
        $('#applyFilters').click(function() {
            currentPage = 1;
            loadLogs();
        });

        // Reset filters
        $('#resetFilters').click(function() {
            $('#logTypeFilter').val('');
            $('#userRoleFilter').val('');
            $('#logLevelFilter').val('');
            $('#searchFilter').val('');
            $('#itemsPerPage').val('20');
            
            const today = new Date();
            const thirtyDaysAgo = new Date();
            thirtyDaysAgo.setDate(today.getDate() - 30);
            
            $('#dateFromFilter').val(thirtyDaysAgo.toISOString().split('T')[0]);
            $('#dateToFilter').val(today.toISOString().split('T')[0]);
            
            currentPage = 1;
            logsPerPage = 20;
            loadLogs();
        });

        // Items per page change
        $('#itemsPerPage').change(function() {
            logsPerPage = parseInt($(this).val());
            currentPage = 1;
            loadLogs();
        });

        // Clear logs confirmation
        $('#clearLogsBtn').click(function() {
            $('#clearLogsModal').modal('show');
        });

        $('#confirmClearLogs').click(function() {
            clearAllLogs();
        });

        // Export logs
        $('#exportLogsBtn').click(function() {
            exportLogs();
        });

        function loadLogs() {
            const filters = {
                action: 'get_logs',
                logType: $('#logTypeFilter').val(),
                userRole: $('#userRoleFilter').val(),
                dateFrom: $('#dateFromFilter').val(),
                dateTo: $('#dateToFilter').val(),
                search: $('#searchFilter').val(),
                logLevel: $('#logLevelFilter').val()
            };

            $.ajax({
                url: window.location.href,
                type: 'GET',
                data: filters,
                dataType: 'json',
                success: function(response) {
                    allLogs = response;
                    displayLogs();
                    updatePagination();
                    updateStatistics();
                },
                error: function(xhr, status, error) {
                    console.error('Error loading logs:', error);
                    $('#logsTableBody').html('<tr><td colspan="7" class="text-center text-danger py-4">Error loading logs</td></tr>');
                }
            });
        }

        function displayLogs() {
            const startIndex = (currentPage - 1) * logsPerPage;
            const endIndex = startIndex + logsPerPage;
            const pageLogs = allLogs.slice(startIndex, endIndex);
            
            const tbody = $('#logsTableBody');
            tbody.empty();

            if (pageLogs.length === 0) {
                tbody.append('<tr><td colspan="7" class="text-center text-muted py-4">No logs found matching your criteria</td></tr>');
                return;
            }

            pageLogs.forEach(log => {
                const badgeClass = getBadgeClass(log.log_type);
                const levelClass = `log-level-${log.log_level}`;
                const userDisplay = log.user_name || 'System';
                const roleDisplay = log.user_role || 'System';
                
                const row = `
                    <tr>
                        <td><small>${formatDateTime(log.timestamp)}</small></td>
                        <td>${userDisplay}</td>
                        <td><span class="badge bg-secondary">${roleDisplay}</span></td>
                        <td><span class="badge ${badgeClass}">${log.action}</span></td>
                        <td>${log.details}</td>
                        <td><small class="text-muted">${log.ip_address || 'N/A'}</small></td>
                        <td><span class="badge ${levelClass === 'log-level-error' ? 'bg-danger' : levelClass === 'log-level-warning' ? 'bg-warning text-dark' : 'bg-info'}">${log.log_level.toUpperCase()}</span></td>
                    </tr>
                `;
                tbody.append(row);
            });

            $('#showingLogs').text(`Showing ${pageLogs.length} logs`);
            $('#totalLogs').text(`Total: ${allLogs.length}`);
        }

        function getBadgeClass(logType) {
            switch(logType) {
                case 'login': return 'badge-login';
                case 'grade': return 'badge-grade';
                case 'attendance': return 'badge-attendance';
                case 'enrollment': return 'badge-enrollment';
                case 'announcement': return 'badge-announcement';
                case 'document': return 'badge-document';
                case 'event': return 'badge-event';
                default: return 'badge-system';
            }
        }

        function formatDateTime(timestamp) {
            const date = new Date(timestamp);
            return date.toLocaleString();
        }

        function updateStatistics() {
            const stats = {
                login: 0,
                grade: 0,
                attendance: 0,
                announcement: 0,
                error: 0,
                total: allLogs.length
            };

            allLogs.forEach(log => {
                if (log.log_type === 'login') stats.login++;
                if (log.log_type === 'grade') stats.grade++;
                if (log.log_type === 'attendance') stats.attendance++;
                if (log.log_type === 'announcement') stats.announcement++;
                if (log.log_level === 'error') stats.error++;
            });

            $('#loginCount').text(stats.login);
            $('#gradeCount').text(stats.grade);
            $('#attendanceCount').text(stats.attendance);
            $('#announcementCount').text(stats.announcement);
            $('#errorCount').text(stats.error);
            $('#totalCount').text(stats.total);
        }

        function updatePagination() {
            const totalPages = Math.ceil(allLogs.length / logsPerPage);
            const pagination = $('#paginationContainer');
            pagination.empty();

            if (totalPages <= 1) return;

            // Previous button
            const prevDisabled = currentPage === 1 ? 'disabled' : '';
            pagination.append(`
                <li class="page-item ${prevDisabled}">
                    <a class="page-link" href="#" onclick="changePage(${currentPage - 1})">Previous</a>
                </li>
            `);

            // Page numbers
            const maxVisiblePages = 5;
            let startPage = Math.max(1, currentPage - Math.floor(maxVisiblePages / 2));
            let endPage = Math.min(totalPages, startPage + maxVisiblePages - 1);

            if (endPage - startPage + 1 < maxVisiblePages) {
                startPage = Math.max(1, endPage - maxVisiblePages + 1);
            }

            if (startPage > 1) {
                pagination.append(`
                    <li class="page-item">
                        <a class="page-link" href="#" onclick="changePage(1)">1</a>
                    </li>
                    ${startPage > 2 ? '<li class="page-item disabled"><span class="page-link">...</span></li>' : ''}
                `);
            }

            for (let i = startPage; i <= endPage; i++) {
                const active = i === currentPage ? 'active' : '';
                pagination.append(`
                    <li class="page-item ${active}">
                        <a class="page-link" href="#" onclick="changePage(${i})">${i}</a>
                    </li>
                `);
            }

            if (endPage < totalPages) {
                pagination.append(`
                    ${endPage < totalPages - 1 ? '<li class="page-item disabled"><span class="page-link">...</span></li>' : ''}
                    <li class="page-item">
                        <a class="page-link" href="#" onclick="changePage(${totalPages})">${totalPages}</a>
                    </li>
                `);
            }

            // Next button
            const nextDisabled = currentPage === totalPages ? 'disabled' : '';
            pagination.append(`
                <li class="page-item ${nextDisabled}">
                    <a class="page-link" href="#" onclick="changePage(${currentPage + 1})">Next</a>
                </li>
            `);
        }

        function changePage(page) {
            currentPage = page;
            displayLogs();
            $('html, body').animate({ scrollTop: 0 }, 'slow');
        }

        function clearAllLogs() {
            $.ajax({
                url: window.location.href,
                type: 'GET',
                data: { action: 'clear_logs' },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#clearLogsModal').modal('hide');
                        allLogs = [];
                        displayLogs();
                        updatePagination();
                        updateStatistics();
                    } else {
                        alert('Error clearing logs: ' + response.message);
                    }
                },
                error: function() {
                    alert('Error clearing logs');
                }
            });
        }

        function exportLogs() {
            // Create CSV content
            let csvContent = "Timestamp,User,Role,Action,Details,IP Address,Level\n";
            
            allLogs.forEach(log => {
                const row = [
                    `"${formatDateTime(log.timestamp)}"`,
                    `"${log.user_name || 'System'}"`,
                    `"${log.user_role || 'System'}"`,
                    `"${log.action}"`,
                    `"${log.details.replace(/"/g, '""')}"`,
                    `"${log.ip_address || 'N/A'}"`,
                    `"${log.log_level}"`
                ];
                csvContent += row.join(',') + '\n';
            });

            // Create and download file
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `system_logs_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }

        // Make functions available globally for onclick events
        window.changePage = changePage;
    });
    </script>
</body>
</html>