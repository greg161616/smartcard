-- Create system_logs table for logging all user actions
CREATE TABLE IF NOT EXISTS `system_logs` (
    `log_id` INT AUTO_INCREMENT PRIMARY KEY,
    `action` VARCHAR(100) NOT NULL,
    `user_id` INT NOT NULL,
    `details` TEXT,
    `log_level` ENUM('info', 'warning', 'error', 'success') DEFAULT 'info',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_user_id` (`user_id`),
    INDEX `idx_action` (`action`),
    INDEX `idx_log_level` (`log_level`),
    INDEX `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key reference to user table if it exists
ALTER TABLE `system_logs` 
ADD CONSTRAINT `fk_system_logs_user` 
FOREIGN KEY (`user_id`) 
REFERENCES `user`(`UserID`) 
ON DELETE CASCADE;


