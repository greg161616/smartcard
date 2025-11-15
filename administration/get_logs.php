<?php
include '../config.php';
header('Content-Type: application/json');

function getSystemLogs($conn, $filters = [], $page = 1, $limit = 20) {
    $offset = ($page - 1) * $limit;
    
    $query = "SELECT sl.*, 
                     u.Email as user_email,
                     COALESCE(a.FullName, t.fName, s.FirstName, 'System') as user_name,
                     COALESCE(u.Role, 'system') as role
              FROM system_logs sl
              LEFT JOIN user u ON sl.user_id = u.UserID
              LEFT JOIN admin a ON u.UserID = a.UserID
              LEFT JOIN teacher t ON u.UserID = t.UserID
              LEFT JOIN student s ON u.UserID = s.userID
              WHERE 1=1";
    
    $countQuery = "SELECT COUNT(*) as total 
                   FROM system_logs sl
                   LEFT JOIN user u ON sl.user_id = u.UserID
                   WHERE 1=1";
    
    $params = [];
    $types = '';
    
    // Apply filters
    if (!empty($filters['type'])) {
        $query .= " AND sl.log_type = ?";
        $countQuery .= " AND sl.log_type = ?";
        $params[] = $filters['type'];
        $types .= 's';
    }
    
    if (!empty($filters['role'])) {
        $query .= " AND u.Role = ?";
        $countQuery .= " AND u.Role = ?";
        $params[] = $filters['role'];
        $types .= 's';
    }
    
    if (!empty($filters['dateFrom'])) {
        $query .= " AND DATE(sl.timestamp) >= ?";
        $countQuery .= " AND DATE(sl.timestamp) >= ?";
        $params[] = $filters['dateFrom'];
        $types .= 's';
    }
    
    if (!empty($filters['dateTo'])) {
        $query .= " AND DATE(sl.timestamp) <= ?";
        $countQuery .= " AND DATE(sl.timestamp) <= ?";
        $params[] = $filters['dateTo'];
        $types .= 's';
    }
    
    $query .= " ORDER BY sl.timestamp DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= 'ii';
    
    // Get total count
    $stmt = $conn->prepare($countQuery);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $totalResult = $stmt->get_result();
    $totalLogs = $totalResult->fetch_assoc()['total'];
    
    // Get logs
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    
    return [
        'logs' => $logs,
        'totalLogs' => $totalLogs
    ];
}

function getLogStatistics($conn) {
    $stats = [];
    
    // Total logs
    $result = $conn->query("SELECT COUNT(*) as total FROM system_logs");
    $stats['totalLogs'] = $result->fetch_assoc()['total'];
    
    // User activities (using action field)
    $result = $conn->query("SELECT COUNT(*) as total FROM system_logs WHERE action LIKE '%user%' OR action LIKE '%login%'");
    $stats['userActivities'] = $result->fetch_assoc()['total'];
    
    // Grade updates (using action field)
    $result = $conn->query("SELECT COUNT(*) as total FROM system_logs WHERE action LIKE '%grade%' OR action LIKE '%upload%'");
    $stats['gradeUpdates'] = $result->fetch_assoc()['total'];
    
    // Today's logs (using created_at field)
    $result = $conn->query("SELECT COUNT(*) as total FROM system_logs WHERE DATE(created_at) = CURDATE()");
    $stats['todayLogs'] = $result->fetch_assoc()['total'];
    
    return $stats;
}

function exportLogsToCSV($conn, $filters = []) {
    $logsData = getSystemLogs($conn, $filters, 1, 10000); // Get all logs
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="system_logs_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // CSV header
    fputcsv($output, ['Timestamp', 'User', 'Role', 'Action', 'Description', 'IP Address', 'Status']);
    
    // CSV data
    foreach ($logsData['logs'] as $log) {
        fputcsv($output, [
            $log['timestamp'],
            $log['user_name'],
            $log['role'],
            $log['action'],
            $log['description'],
            $log['ip_address'],
            $log['status']
        ]);
    }
    
    fclose($output);
    exit;
}

function clearOldLogs($conn) {
    $sixMonthsAgo = date('Y-m-d H:i:s', strtotime('-6 months'));
    $stmt = $conn->prepare("DELETE FROM system_logs WHERE created_at < ?");
    $stmt->bind_param('s', $sixMonthsAgo);
    
    if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Logs cleared successfully'];
    } else {
        return ['success' => false, 'message' => $conn->error];
    }
}

// Main request handler
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isset($_GET['action'])) {
        switch ($_GET['action']) {
            case 'get_logs':
                $filters = [
                    'type' => $_GET['type'] ?? '',
                    'role' => $_GET['role'] ?? '',
                    'dateFrom' => $_GET['dateFrom'] ?? '',
                    'dateTo' => $_GET['dateTo'] ?? ''
                ];
                
                $page = intval($_GET['page'] ?? 1);
                $limit = intval($_GET['limit'] ?? 20);
                
                $logsData = getSystemLogs($conn, $filters, $page, $limit);
                $stats = getLogStatistics($conn);
                
                echo json_encode([
                    'logs' => $logsData['logs'],
                    'totalLogs' => $logsData['totalLogs'],
                    'stats' => $stats
                ]);
                break;
                
            case 'export_logs':
                $filters = [
                    'type' => $_GET['type'] ?? '',
                    'role' => $_GET['role'] ?? '',
                    'dateFrom' => $_GET['dateFrom'] ?? '',
                    'dateTo' => $_GET['dateTo'] ?? ''
                ];
                exportLogsToCSV($conn, $filters);
                break;
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'clear_old_logs':
                $result = clearOldLogs($conn);
                echo json_encode($result);
                break;
        }
    }
}
?>