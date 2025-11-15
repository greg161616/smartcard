<?php
session_start();
require_once 'config.php';
require_once 'api/log_helper.php';

$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
$email = isset($_SESSION['email']) ? $_SESSION['email'] : null;

if ($user_id || $email) {
	// Log successful logout
	log_system_action($conn, 'logout', $user_id, 'User logged out', 'info');
} else {
	// Log attempt to logout with no user session
	log_system_action($conn, 'logout', null, 'Logout attempted with no active session', 'warning');
}

session_unset();
session_destroy();
header('Location: login.php');
exit();
?>