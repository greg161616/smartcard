<?php
// session_timeout.php

// Set session lifetime to 2 minutes
ini_set('session.gc_maxlifetime', 120);
session_set_cookie_params(120);

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check for inactivity and logout if needed
function check_session_timeout() {
    $timeout_duration = 120; // 2 minutes in seconds
    
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $timeout_duration)) {
        // Last request was more than 2 minutes ago
        session_unset();
        session_destroy();
        
        // Redirect to login page with timeout message
        header('Location: ../login.php?timeout=1');
        exit;
    }
    
    // Update last activity time
    $_SESSION['LAST_ACTIVITY'] = time();
    
    // Regenerate session ID periodically to prevent fixation
    if (!isset($_SESSION['CREATED'])) {
        $_SESSION['CREATED'] = time();
    } else if (time() - $_SESSION['CREATED'] > 300) { // 5 minutes
        session_regenerate_id(true);
        $_SESSION['CREATED'] = time();
    }
}

// Call this function on every page load
check_session_timeout();
?>