<?php
// check_session.php
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['LAST_ACTIVITY'])) {
    echo json_encode(['active' => false]);
    exit;
}

$timeout_duration = 120; // 2 minutes

if (time() - $_SESSION['LAST_ACTIVITY'] > $timeout_duration) {
    echo json_encode(['active' => false]);
} else {
    // Update last activity for this check
    $_SESSION['LAST_ACTIVITY'] = time();
    echo json_encode(['active' => true]);
}
?>