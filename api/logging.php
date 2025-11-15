<?php
/**
 * Universal Logging System for SmartCard
 * Can be included in any PHP file
 */

class Logger {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Main logging function - Can log any action
     * @param string $action - The action name (e.g., "Login", "Grade Entry", "Export Data")
     * @param int $userId - User ID (optional, will use session if not provided)
     * @param string $details - Additional details (JSON string or plain text)
     * @param string $level - Log level: info, success, warning, error
     * @return bool - Success status
     */
    public function log($action, $userId = null, $details = '', $level = 'info') {
        try {
            // Get user ID from session if not provided
            if ($userId === null && isset($_SESSION['user_id'])) {
                $userId = $_SESSION['user_id'];
            }
            
            // Default to user ID 0 if no user logged in
            if ($userId === null) {
                $userId = 0;
            }
            
            // Prepare details for JSON storage
            if (is_array($details)) {
                $details = json_encode($details);
            }
            
            // Insert log entry
            $stmt = $this->conn->prepare("
                INSERT INTO system_logs (action, user_id, details, log_level, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            
            $stmt->bind_param('siss', $action, $userId, $details, $level);
            $result = $stmt->execute();
            $stmt->close();
            
            return $result;
        } catch (Exception $e) {
            // Fallback to file logging if database fails
            $this->logToFile($action, $userId ?? 0, $details, $level, $e->getMessage());
            return false;
        }
    }
    
    /**
     * Quick logging helper - Usage: Logger::quick($conn, "Action Name", $details)
     */
    public static function quick($conn, $action, $details = '', $level = 'info') {
        $logger = new self($conn);
        return $logger->log($action, null, $details, $level);
    }
    
    /**
     * Log login attempts
     */
    public function logLogin($username, $success = true, $userId = null) {
        $level = $success ? 'success' : 'error';
        $action = $success ? 'Login Success' : 'Login Failed';
        $details = json_encode(['username' => $username]);
        return $this->log($action, $userId, $details, $level);
    }
    
    /**
     * Log logout
     */
    public function logLogout($userId = null) {
        return $this->log('Logout', $userId, '', 'info');
    }
    
    /**
     * Log data exports
     */
    public function logExport($exportType, $fileType = '', $userId = null) {
        $details = json_encode([
            'export_type' => $exportType,
            'file_type' => $fileType
        ]);
        return $this->log('Data Export', $userId, $details, 'info');
    }
    
    /**
     * Log errors
     */
    public function logError($errorMessage, $additionalDetails = '', $userId = null) {
        $details = json_encode([
            'error_message' => $errorMessage,
            'additional_details' => $additionalDetails
        ]);
        return $this->log('Error', $userId, $details, 'error');
    }
    
    /**
     * Log successful operations
     */
    public function logSuccess($action, $details = '', $userId = null) {
        return $this->log($action, $userId, $details, 'success');
    }
    
    /**
     * Log warnings
     */
    public function logWarning($message, $details = '', $userId = null) {
        return $this->log('Warning', $userId, json_encode([
            'message' => $message,
            'details' => $details
        ]), 'warning');
    }
    
    /**
     * Get logs with filters
     */
    public function getLogs($filters = []) {
        $query = "SELECT sl.*, 
                         t.fName as teacher_fname, t.lName as teacher_lname,
                         a.FullName as admin_name,
                         s.FirstName, s.LastName,
                         u.Email, u.Role
                  FROM system_logs sl
                  LEFT JOIN teacher t ON t.UserID = sl.user_id
                  LEFT JOIN admin a ON a.UserID = sl.user_id
                  LEFT JOIN student s ON s.userID = sl.user_id
                  LEFT JOIN user u ON sl.user_id = u.UserID
                  WHERE 1=1";
        
        $params = [];
        $types = '';
        
        if (isset($filters['user_id'])) {
            $query .= " AND sl.user_id = ?";
            $params[] = $filters['user_id'];
            $types .= 'i';
        }
        
        if (isset($filters['action'])) {
            $query .= " AND sl.action LIKE ?";
            $params[] = '%' . $filters['action'] . '%';
            $types .= 's';
        }
        
        if (isset($filters['level'])) {
            $query .= " AND sl.log_level = ?";
            $params[] = $filters['level'];
            $types .= 's';
        }
        
        if (isset($filters['date_from'])) {
            $query .= " AND sl.created_at >= ?";
            $params[] = $filters['date_from'];
            $types .= 's';
        }
        
        if (isset($filters['date_to'])) {
            $query .= " AND sl.created_at <= ?";
            $params[] = $filters['date_to'];
            $types .= 's';
        }
        
        $query .= " ORDER BY sl.created_at DESC LIMIT 1000";
        
        $stmt = $this->conn->prepare($query);

        if (!empty($params)) {
            // mysqli::bind_param requires references. Build an array of references.
            $bindParams = [];
            $bindParams[] = & $types;
            for ($i = 0; $i < count($params); $i++) {
                $bindParams[] = & $params[$i];
            }
            // Use call_user_func_array to bind params by reference
            call_user_func_array([$stmt, 'bind_param'], $bindParams);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $logs = [];
        
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        
        $stmt->close();
        
        return $logs;
    }
    
    /**
     * Delete old logs (older than specified days)
     */
    public function cleanOldLogs($daysOld = 90) {
        $stmt = $this->conn->prepare("
            DELETE FROM system_logs 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)
        ");
        
        $stmt->bind_param('i', $daysOld);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Fallback file logging
     */
    private function logToFile($action, $userId, $details, $level, $error = '') {
        $logEntry = date('Y-m-d H:i:s') . " - [{$level}] User: {$userId}, Action: {$action}, Details: {$details}";
        if ($error) {
            $logEntry .= ", Error: {$error}";
        }
        $logEntry .= PHP_EOL;
        
        $logFile = __DIR__ . '/../logs/system.log';
        $logDir = dirname($logFile);
        
        // Create directory if it doesn't exist
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        file_put_contents($logFile, $logEntry, FILE_APPEND);
    }
}
?>

