ALTER TABLE users 
ADD COLUMN failed_login_attempts INT NOT NULL DEFAULT 0,
ADD COLUMN last_login_attempt TIMESTAMP NULL,
ADD COLUMN account_locked_until TIMESTAMP NULL,
ADD COLUMN session_timeout INT DEFAULT 1800;

CREATE TABLE IF NOT EXISTS error_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    error_type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    file VARCHAR(255),
    line INT,
    user_id INT NULL,
    ip_address VARCHAR(45)
);
