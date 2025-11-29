<?php
session_start();
include '../config.php';
include '../api/logging.php';

// Check if user is principal
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'head') {
    header('Location: ../login');
    exit;
}

$logger = new Logger($conn);

// Get filters from POST
$filters = [];
if (!empty($_POST['action'])) $filters['action'] = $_POST['action'];
if (!empty($_POST['user_id'])) $filters['user_id'] = (int)$_POST['user_id'];
if (!empty($_POST['level'])) $filters['level'] = $_POST['level'];
if (!empty($_POST['date_from'])) $filters['date_from'] = $_POST['date_from'];
if (!empty($_POST['date_to'])) $filters['date_to'] = $_POST['date_to'];

// Get logs
$logs = $logger->getLogs($filters);

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=system_logs_' . date('Y-m-d') . '.csv');

$output = fopen('php://output', 'w');

// CSV header
fputcsv($output, ['ID', 'Timestamp', 'User', 'Role', 'Action', 'Details', 'Level']);

// CSV data
foreach ($logs as $log) {
    $userName = '';
    $userRole = '';
    
    if (!empty($log['teacher_fname']) && !empty($log['teacher_lname'])) {
        $userName = $log['teacher_fname'] . ' ' . $log['teacher_lname'];
        $userRole = 'Teacher';
    } elseif (!empty($log['admin_name'])) {
        $userName = $log['admin_name'];
        $userRole = 'Admin';
    } elseif (!empty($log['FirstName']) && !empty($log['LastName'])) {
        $userName = $log['FirstName'] . ' ' . $log['LastName'];
        $userRole = 'Student';
    } elseif (!empty($log['Email'])) {
        $userName = $log['Email'];
        $userRole = $log['Role'] ?? 'User';
    }
    
    $details = $log['details'];
    $jsonDetails = json_decode($details, true);
    if ($jsonDetails) {
        $details = json_encode($jsonDetails);
    }
    
    fputcsv($output, [
        $log['log_id'],
        $log['created_at'],
        $userName,
        $userRole,
        $log['action'],
        $details,
        strtoupper($log['log_level'])
    ]);
}

fclose($output);
exit;
?>