<?php
// Helper for logging system actions to system_logs table
function log_system_action($conn, $action, $user_id = null, $details = null, $level = 'info') {
  if (is_array($details) || is_object($details)) {
    $details = json_encode($details, JSON_UNESCAPED_UNICODE);
  }
  if ($user_id === null) {
    $stmt = $conn->prepare("INSERT INTO system_logs (action, details, log_level, created_at) VALUES (?, ?, ?, NOW())");
    if ($stmt) {
      $stmt->bind_param('sss', $action, $details, $level);
      $stmt->execute();
      $stmt->close();
    }
  } else {
    $stmt = $conn->prepare("INSERT INTO system_logs (action, user_id, details, log_level, created_at) VALUES (?, ?, ?, ?, NOW())");
    if ($stmt) {
      $stmt->bind_param('siss', $action, $user_id, $details, $level);
      $stmt->execute();
      $stmt->close();
    }
  }
}
?>
