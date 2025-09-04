-- FaithX Infinity: Unified Schema for Group Access, Category Management, and Overdue Notification

CREATE DATABASE IF NOT EXISTS faithx_infinity;
USE faithx_infinity;

-- USERS TABLE
CREATE TABLE IF NOT EXISTS users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    parish VARCHAR(100),
    phone VARCHAR(20),
    role ENUM('member','group_leader','finance_officer','chief_finance_officer','elder_admin','system_admin') DEFAULT 'member',
    is_active TINYINT(1) DEFAULT 1,
    failed_login_attempts INT DEFAULT 0,
    last_login_attempt TIMESTAMP NULL,
    account_locked_until TIMESTAMP NULL,
    session_timeout INT DEFAULT 1800,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- GROUPS TABLE
CREATE TABLE IF NOT EXISTS groups (
    group_id INT AUTO_INCREMENT PRIMARY KEY,
    group_name VARCHAR(100) NOT NULL,
    leader_name VARCHAR(100),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- USER GROUP ACCESS TABLE
CREATE TABLE IF NOT EXISTS user_group_access (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    group_id INT NOT NULL,
    access_level ENUM('member','finance_officer','elder_admin') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (group_id) REFERENCES groups(group_id)
);

-- PLEDGE CATEGORIES TABLE
CREATE TABLE IF NOT EXISTS pledge_categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    group_id INT,
    category_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES groups(group_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);

-- MEMBERS TABLE
CREATE TABLE IF NOT EXISTS members (
    member_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    phone VARCHAR(20),
    group_id INT,
    join_date DATE,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (group_id) REFERENCES groups(group_id)
);

-- PLEDGES TABLE
CREATE TABLE IF NOT EXISTS pledges (
    pledge_id INT AUTO_INCREMENT PRIMARY KEY,
    member_id INT NOT NULL,
    group_id INT,
    category_id INT,
    amount DECIMAL(12,2) NOT NULL,
    pledge_date DATE NOT NULL,
    due_date DATE,
    target_date DATE,
    payment_schedule ENUM('weekly','monthly','quarterly','annually','one_time') DEFAULT 'one_time',
    installment_amount DECIMAL(12,2),
    notes TEXT,
    status ENUM('active','pending','completed','delayed','cancelled','overdue','on_hold') DEFAULT 'active',
    reminder_sent TINYINT(1) DEFAULT 0,
    last_reminder_date DATE,
    reminder_count INT DEFAULT 0,
    modification_count INT DEFAULT 0,
    last_modified_date DATE,
    created_by INT,
    approved_by INT,
    approval_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (member_id) REFERENCES members(member_id),
    FOREIGN KEY (group_id) REFERENCES groups(group_id),
    FOREIGN KEY (category_id) REFERENCES pledge_categories(category_id),
    FOREIGN KEY (created_by) REFERENCES users(user_id),
    FOREIGN KEY (approved_by) REFERENCES users(user_id)
);

-- TRANSACTIONS TABLE
CREATE TABLE IF NOT EXISTS transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    pledge_id INT NOT NULL,
    amount_paid DECIMAL(12,2) NOT NULL,
    payment_method ENUM('cash','check','bank_transfer','mobile_money','online') NOT NULL,
    payment_date DATE NOT NULL,
    recorded_by INT,
    reference_number VARCHAR(50),
    notes TEXT,
    is_voided TINYINT(1) DEFAULT 0,
    voided_by INT NULL,
    void_reason TEXT NULL,
    void_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (pledge_id) REFERENCES pledges(pledge_id),
    FOREIGN KEY (recorded_by) REFERENCES users(user_id),
    FOREIGN KEY (voided_by) REFERENCES users(user_id)
);

-- EMAIL NOTIFICATIONS TABLE
CREATE TABLE IF NOT EXISTS email_notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    pledge_id INT NOT NULL,
    user_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    subject VARCHAR(255),
    message TEXT,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pledge_id) REFERENCES pledges(pledge_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- BUILDING FUND PROJECTS TABLE
CREATE TABLE IF NOT EXISTS building_fund_projects (
    project_id INT AUTO_INCREMENT PRIMARY KEY,
    project_name VARCHAR(200) NOT NULL,
    project_description TEXT,
    target_amount DECIMAL(15,2) NOT NULL,
    current_amount DECIMAL(15,2) DEFAULT 0,
    start_date DATE NOT NULL,
    target_completion_date DATE,
    actual_completion_date DATE NULL,
    status ENUM('planning','active','completed','on_hold','cancelled') DEFAULT 'planning',
    created_by INT NOT NULL,
    approved_by INT NULL,
    approval_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id),
    FOREIGN KEY (approved_by) REFERENCES users(user_id)
);

-- AUDIT LOGS TABLE
CREATE TABLE IF NOT EXISTS audit_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action_type VARCHAR(100) NOT NULL,
    resource_type VARCHAR(100) NOT NULL,
    resource_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    session_id VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- SYSTEM SETTINGS TABLE
CREATE TABLE IF NOT EXISTS system_settings (
    setting_id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type ENUM('string','integer','boolean','json') DEFAULT 'string',
    description TEXT,
    is_public TINYINT(1) DEFAULT 0,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(user_id)
);

-- ANNOUNCEMENTS TABLE
CREATE TABLE IF NOT EXISTS announcements (
    announcement_id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    image_path VARCHAR(500),
    is_active TINYINT(1) DEFAULT 1,
    priority ENUM('low','normal','high','urgent') DEFAULT 'normal',
    target_roles JSON,
    start_date DATE,
    end_date DATE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(user_id)
);

-- TEMPORARY ROLE ASSIGNMENTS TABLE
CREATE TABLE IF NOT EXISTS temporary_role_assignments (
    assignment_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    temporary_role ENUM('finance_officer','chief_finance_officer','elder_admin','system_admin') NOT NULL,
    assigned_by INT NOT NULL,
    reason TEXT NOT NULL,
    start_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_date TIMESTAMP NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    revoked_by INT NULL,
    revoked_at TIMESTAMP NULL,
    revoke_reason TEXT NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (assigned_by) REFERENCES users(user_id),
    FOREIGN KEY (revoked_by) REFERENCES users(user_id)
);

-- BREAK GLASS ACCESS TABLE
CREATE TABLE IF NOT EXISTS break_glass_access (
    access_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activated_by INT NOT NULL,
    emergency_reason TEXT NOT NULL,
    access_level ENUM('system_override') NOT NULL,
    start_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    end_time TIMESTAMP NULL,
    is_active TINYINT(1) DEFAULT 1,
    deactivated_by INT NULL,
    deactivated_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (activated_by) REFERENCES users(user_id),
    FOREIGN KEY (deactivated_by) REFERENCES users(user_id)
);

-- GROUP TRANSFER REQUESTS TABLE
CREATE TABLE IF NOT EXISTS group_transfer_requests (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    from_group_id INT,
    to_group_id INT NOT NULL,
    requested_by INT NOT NULL,
    transfer_reason TEXT NOT NULL,
    cfo_approval_status ENUM('pending','approved','rejected') DEFAULT 'pending',
    elder_approval_status ENUM('pending','approved','rejected') DEFAULT 'pending',
    final_status ENUM('pending','approved','rejected','completed') DEFAULT 'pending',
    cfo_approved_by INT NULL,
    elder_approved_by INT NULL,
    cfo_approval_date TIMESTAMP NULL,
    elder_approval_date TIMESTAMP NULL,
    completion_date TIMESTAMP NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    FOREIGN KEY (from_group_id) REFERENCES groups(group_id),
    FOREIGN KEY (to_group_id) REFERENCES groups(group_id),
    FOREIGN KEY (requested_by) REFERENCES users(user_id),
    FOREIGN KEY (cfo_approved_by) REFERENCES users(user_id),
    FOREIGN KEY (elder_approved_by) REFERENCES users(user_id)
);

-- ERROR LOGS TABLE
CREATE TABLE IF NOT EXISTS error_logs (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    error_type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    file VARCHAR(255),
    line INT,
    user_id INT NULL,
    username VARCHAR(50) NULL,
    page VARCHAR(255) NULL,
    solution TEXT NULL,
    status ENUM('new','in_progress','resolved') DEFAULT 'new',
    FOREIGN KEY (user_id) REFERENCES users(user_id)
);

-- Insert sample groups
INSERT INTO groups (group_name, leader_name, description) VALUES
('Youth Ministry', 'John Doe', 'Young adults and teenagers ministry'),
('Dorcas Group', 'Mary Smith', 'Women fellowship and community service'),
('Cornelius Group', 'Peter Johnson', 'Men ministry and outreach'),
('Children Ministry', 'Sarah Wilson', 'Sabbath school and children programs'),
('Choir Ministry', 'David Brown', 'Music and worship ministry');

-- Insert sample pledge categories
INSERT INTO pledge_categories (category_name, description, is_active) VALUES
('Building Fund', 'Church construction and renovation projects', 1),
('General Offering', 'Regular church operations and maintenance', 1),
('Cornelius Programs', 'Men activities and events', 1),
('Dorcas Fund', 'Women activities and events', 1),
('Youth Programs', 'Youth ministry activities and events', 1),
('Benevolence Fund', 'Helping members in need', 1);

-- Insert sample users with secure password hashes
-- Password for all accounts: 'FaithX2025!'
INSERT INTO users (username, email, password, first_name, last_name, phone, role) VALUES
('sysadmin', 'sysadmin@faithx.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System', 'Administrator', '+254700000000', 'system_admin'),
('elderadmin', 'elder@faithx.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Elder', 'Admin', '+254700000001', 'elder_admin'),
('cfo', 'cfo@faithx.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Chief Finance', 'Officer', '+254700000002', 'chief_finance_officer'),
('finance1', 'finance@faithx.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Finance', 'Officer', '+254700000003', 'finance_officer'),
('leader1', 'leader1@faithx.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Group', 'Leader', '+254700000004', 'group_leader'),
('member1', 'member1@faithx.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John', 'Doe', '+254700000005', 'member'),
('member2', 'member2@faithx.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane', 'Smith', '+254700000006', 'member'),
('member3', 'member3@faithx.org', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Peter', 'Johnson', '+254700000007', 'member');

-- Insert sample members
INSERT INTO members (user_id, first_name, last_name, phone, group_id, join_date) VALUES
(1, 'System', 'Administrator', '+254700000000', 1, '2024-01-01'),
(2, 'Elder', 'Admin', '+254700000001', 1, '2024-01-01'),
(3, 'Chief Finance', 'Officer', '+254700000002', 1, '2024-01-01'),
(4, 'Finance', 'Officer', '+254700000003', 2, '2024-01-10'),
(5, 'Group', 'Leader', '+254700000004', 3, '2024-01-15'),
(6, 'John', 'Doe', '+254700000005', 3, '2024-01-15'),
(7, 'Jane', 'Smith', '+254700000006', 4, '2024-02-01'),
(8, 'Peter', 'Johnson', '+254700000007', 5, '2024-02-15');

-- Insert sample pledges
INSERT INTO pledges (member_id, category_id, amount, pledge_date, target_date, status, created_by) VALUES
(6, 1, 50000.00, '2024-01-20', '2024-12-31', 'active', 6),
(6, 2, 10000.00, '2024-02-01', '2024-06-30', 'active', 6),
(7, 1, 75000.00, '2024-01-25', '2024-12-31', 'active', 7),
(7, 3, 25000.00, '2024-02-10', '2024-08-31', 'active', 7),
(8, 1, 100000.00, '2024-02-15', '2024-12-31', 'active', 8),
(8, 4, 15000.00, '2024-02-20', '2024-05-31', 'pending', 8);

-- Insert sample transactions
INSERT INTO transactions (pledge_id, amount_paid, payment_date, payment_method, recorded_by, reference_number) VALUES
(1, 15000.00, '2024-02-01', 'mobile_money', 4, 'MM240201001'),
(2, 5000.00, '2024-02-15', 'cash', 4, 'CASH240215001'),
(3, 25000.00, '2024-02-20', 'check', 4, 'CHK240220001');

-- Insert sample building fund projects
INSERT INTO building_fund_projects (project_name, project_description, target_amount, start_date, target_completion_date, created_by) VALUES
('New Sanctuary Construction', 'Building a new sanctuary to accommodate growing congregation', 5000000.00, '2024-01-01', '2024-12-31', 3),
('Youth Center Renovation', 'Renovating the youth center with modern facilities', 1500000.00, '2024-03-01', '2024-08-31', 3),
('Church Kitchen Upgrade', 'Upgrading kitchen facilities for community events', 800000.00, '2024-02-01', '2024-06-30', 3);

-- Insert system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_public) VALUES
('church_name', 'FaithX Infinity Church', 'string', 'Official church name', 1),
('currency_symbol', 'KSh', 'string', 'Currency symbol for display', 1),
('session_timeout', '1800', 'integer', 'Session timeout in seconds', 0),
('max_login_attempts', '5', 'integer', 'Maximum failed login attempts before lockout', 0),
('lockout_duration', '900', 'integer', 'Account lockout duration in seconds', 0),
('pledge_modification_limit', '1', 'integer', 'Number of pledge modifications allowed per quarter', 0),
('large_pledge_threshold', '1000', 'integer', 'Amount threshold requiring admin approval', 0),
('auto_refresh_interval', '900', 'integer', 'Dashboard auto-refresh interval in seconds', 0);

-- Create views for easier reporting
CREATE VIEW pledge_summary AS
SELECT 
    p.pledge_id,
    p.member_id,
    p.group_id,
    p.category_id,
    p.amount as pledged_amount,
    COALESCE(SUM(CASE WHEN t.is_voided = 0 THEN t.amount_paid ELSE 0 END), 0) as paid_amount,
    (p.amount - COALESCE(SUM(CASE WHEN t.is_voided = 0 THEN t.amount_paid ELSE 0 END), 0)) as remaining_amount,
    CASE 
        WHEN COALESCE(SUM(CASE WHEN t.is_voided = 0 THEN t.amount_paid ELSE 0 END), 0) >= p.amount THEN 100
        ELSE (COALESCE(SUM(CASE WHEN t.is_voided = 0 THEN t.amount_paid ELSE 0 END), 0) / p.amount) * 100
    END as completion_percentage,
    p.status,
    p.pledge_date,
    p.target_date,
    p.due_date,
    CASE 
        WHEN p.due_date IS NULL THEN 'no_due_date'
        WHEN DATEDIFF(p.due_date, CURDATE()) >= 28 THEN 'green'
        WHEN DATEDIFF(p.due_date, CURDATE()) >= 21 THEN 'yellow'
        WHEN DATEDIFF(p.due_date, CURDATE()) >= 14 THEN 'orange'
        WHEN DATEDIFF(p.due_date, CURDATE()) >= 7 THEN 'red'
        ELSE 'overdue'
    END as due_date_color,
    pc.category_name,
    m.first_name,
    m.last_name,
    g.group_name,
    u.user_id
FROM pledges p
JOIN pledge_categories pc ON p.category_id = pc.category_id
JOIN members m ON p.member_id = m.member_id
JOIN users u ON m.user_id = u.user_id
LEFT JOIN groups g ON m.group_id = g.group_id
LEFT JOIN transactions t ON p.pledge_id = t.pledge_id
GROUP BY p.pledge_id;

-- Create view for group financial summary (Elder Admin access)
CREATE VIEW group_financial_summary AS
SELECT 
    COALESCE(g.group_id, 0) as group_id,
    COALESCE(g.group_name, 'Unassigned') as group_name,
    COUNT(DISTINCT m.member_id) as active_members,
    COUNT(DISTINCT p.pledge_id) as total_pledges,
    COALESCE(SUM(p.amount), 0) as total_pledged,
    COALESCE(SUM(ps.paid_amount), 0) as total_paid,
    COALESCE(SUM(ps.remaining_amount), 0) as total_remaining,
    CASE 
        WHEN SUM(p.amount) > 0 THEN (SUM(ps.paid_amount) / SUM(p.amount)) * 100
        ELSE 0
    END as completion_percentage
FROM groups g
LEFT JOIN members m ON g.group_id = m.group_id
LEFT JOIN pledges p ON m.member_id = p.member_id
LEFT JOIN pledge_summary ps ON p.pledge_id = ps.pledge_id
WHERE g.group_id IS NOT NULL
GROUP BY g.group_id, g.group_name;

-- Create view for CFO building fund dashboard
CREATE VIEW cfo_building_fund_dashboard AS
SELECT 
    bf.project_id,
    bf.project_name,
    bf.project_description,
    bf.target_amount,
    COALESCE(SUM(ps.paid_amount), 0) as current_amount,
    bf.start_date,
    bf.target_completion_date,
    bf.actual_completion_date,
    bf.status,
    CASE 
        WHEN bf.target_amount > 0 THEN (COALESCE(SUM(ps.paid_amount), 0) / bf.target_amount) * 100
        ELSE 0
    END as progress_percentage,
    COUNT(DISTINCT p.pledge_id) as total_pledges,
    COUNT(DISTINCT p.member_id) as contributing_members
FROM building_fund_projects bf
LEFT JOIN pledge_categories pc ON pc.category_name = 'Building Fund'
LEFT JOIN pledges p ON pc.category_id = p.category_id
LEFT JOIN pledge_summary ps ON p.pledge_id = ps.pledge_id
GROUP BY bf.project_id;

-- Create view for CFO church summary
CREATE VIEW cfo_church_summary AS
SELECT 
    COUNT(DISTINCT u.user_id) as total_members,
    COUNT(DISTINCT p.pledge_id) as total_pledges,
    COUNT(DISTINCT g.group_id) as total_groups,
    COALESCE(SUM(p.amount), 0) as total_pledged,
    COALESCE(SUM(ps.paid_amount), 0) as total_paid,
    COALESCE(SUM(ps.remaining_amount), 0) as total_remaining,
    CASE 
        WHEN SUM(p.amount) > 0 THEN (SUM(ps.paid_amount) / SUM(p.amount)) * 100
        ELSE 0
    END as overall_completion_percentage
FROM users u
LEFT JOIN members m ON u.user_id = m.user_id
LEFT JOIN pledges p ON m.member_id = p.member_id
LEFT JOIN pledge_summary ps ON p.pledge_id = ps.pledge_id
LEFT JOIN groups g ON m.group_id = g.group_id
WHERE u.role = 'member';

-- Create view for group transfer approval queue
CREATE VIEW group_transfer_approval_queue AS
SELECT 
    gtr.request_id,
    gtr.user_id,
    u.first_name,
    u.last_name,
    u.username,
    fg.group_name as from_group_name,
    tg.group_name as to_group_name,
    gtr.transfer_reason,
    gtr.cfo_approval_status,
    gtr.elder_approval_status,
    gtr.final_status,
    gtr.created_at,
    gtr.updated_at,
    COUNT(p.pledge_id) as active_pledges,
    COALESCE(SUM(ps.remaining_amount), 0) as outstanding_amount
FROM group_transfer_requests gtr
JOIN users u ON gtr.user_id = u.user_id
LEFT JOIN groups fg ON gtr.from_group_id = fg.group_id
JOIN groups tg ON gtr.to_group_id = tg.group_id
LEFT JOIN members m ON u.user_id = m.user_id
LEFT JOIN pledges p ON m.member_id = p.member_id AND p.status IN ('active', 'pending')
LEFT JOIN pledge_summary ps ON p.pledge_id = ps.pledge_id
GROUP BY gtr.request_id;

-- Create view for category statistics
CREATE VIEW category_statistics AS
SELECT 
    pc.category_id,
    pc.category_name,
    pc.group_id,
    g.group_name,
    COUNT(p.pledge_id) as pledge_count,
    COALESCE(SUM(p.amount), 0) as total_pledged,
    COALESCE(SUM(ps.paid_amount), 0) as total_paid,
    COALESCE(SUM(ps.remaining_amount), 0) as total_remaining,
    CASE 
        WHEN SUM(p.amount) > 0 THEN (SUM(ps.paid_amount) / SUM(p.amount)) * 100
        ELSE 0
    END as completion_percentage
FROM pledge_categories pc
LEFT JOIN groups g ON pc.group_id = g.group_id
LEFT JOIN pledges p ON pc.category_id = p.category_id
LEFT JOIN pledge_summary ps ON p.pledge_id = ps.pledge_id
WHERE pc.is_active = 1
GROUP BY pc.category_id;

-- Add indexes for performance
CREATE INDEX idx_pledges_status ON pledges(status);
CREATE INDEX idx_pledges_due_date ON pledges(due_date);
CREATE INDEX idx_pledges_member_id ON pledges(member_id);
CREATE INDEX idx_pledges_category_id ON pledges(category_id);
CREATE INDEX idx_transactions_pledge_id ON transactions(pledge_id);
CREATE INDEX idx_transactions_payment_date ON transactions(payment_date);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_audit_logs_user_id ON audit_logs(user_id);
CREATE INDEX idx_audit_logs_created_at ON audit_logs(created_at);

-- Note: All demo accounts use password 'FaithX2025!' for security
-- In production, use unique, strong passwords for each account
