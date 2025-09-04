-- Payment Verification System Schema Updates
-- Add verification fields to existing transactions table

ALTER TABLE transactions 
ADD COLUMN verification_status ENUM('pending','verified','rejected','disputed') DEFAULT 'pending' AFTER notes,
ADD COLUMN verification_method ENUM('mpesa_code','bank_reference','cheque_number','cash_receipt','online_reference') NULL AFTER verification_status,
ADD COLUMN verification_reference VARCHAR(100) NULL AFTER verification_method,
ADD COLUMN verification_notes TEXT NULL AFTER verification_reference,
ADD COLUMN verified_by INT NULL AFTER verification_notes,
ADD COLUMN verified_at TIMESTAMP NULL AFTER verified_by,
ADD COLUMN rejection_reason TEXT NULL AFTER verified_at,
ADD COLUMN member_submitted_at TIMESTAMP NULL AFTER rejection_reason,
ADD COLUMN member_submitted_by INT NULL AFTER member_submitted_at,
ADD FOREIGN KEY (verified_by) REFERENCES users(user_id),
ADD FOREIGN KEY (member_submitted_by) REFERENCES users(user_id);

-- Add indexes for performance
CREATE INDEX idx_transactions_verification_status ON transactions(verification_status);
CREATE INDEX idx_transactions_verification_method ON transactions(verification_method);
CREATE INDEX idx_transactions_verified_by ON transactions(verified_by);

-- Create payment verification queue view for easier management
CREATE VIEW payment_verification_queue AS
SELECT 
    t.transaction_id,
    t.pledge_id,
    t.amount_paid,
    t.payment_method,
    t.verification_method,
    t.verification_reference,
    t.verification_status,
    t.payment_date,
    t.created_at,
    t.member_submitted_at,
    p.amount as pledge_amount,
    m.first_name,
    m.last_name,
    m.phone,
    g.group_name,
    pc.category_name,
    u.username as submitted_by_username,
    verifier.first_name as verifier_first_name,
    verifier.last_name as verifier_last_name
FROM transactions t
JOIN pledges p ON t.pledge_id = p.pledge_id
JOIN members m ON p.member_id = m.member_id
LEFT JOIN groups g ON m.group_id = g.group_id
LEFT JOIN pledge_categories pc ON p.category_id = pc.category_id
LEFT JOIN users u ON t.member_submitted_by = u.user_id
LEFT JOIN users verifier ON t.verified_by = verifier.user_id
WHERE t.verification_status = 'pending'
ORDER BY t.member_submitted_at ASC, t.created_at ASC;