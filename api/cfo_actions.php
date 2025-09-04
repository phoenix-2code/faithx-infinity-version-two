<?php
require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/rbac.php';

header('Content-Type: application/json');

// Initialize authentication and RBAC
$auth = new AuthSystem($pdo);
$rbac = new RBAC($pdo, $_SESSION['user_id'] ?? null);

// Check if user is logged in and has CFO role
if (!isLoggedIn() || !hasRole('chief_finance_officer')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_POST['action'] ?? '';
$csrf_token = $input['csrf_token'] ?? $_POST['csrf_token'] ?? '';

// Validate CSRF token
if (!validateCSRFToken($csrf_token)) {
    echo json_encode(['success' => false, 'message' => 'CSRF token validation failed.']);
    exit();
}

switch ($action) {
    case 'add_pledge':
        // Implement add pledge logic
        $member_id = sanitize($input['member_id'] ?? '');
        $amount = sanitize($input['amount'] ?? '');
        $due_date = sanitize($input['due_date'] ?? '');
        $category_id = sanitize($input['category_id'] ?? '');
        $description = sanitize($input['description'] ?? '');

        if (empty($member_id) || empty($amount) || !is_numeric($amount) || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid member or amount.']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO pledges (member_id, amount, due_date, category_id, description, created_at, status) VALUES (?, ?, ?, ?, ?, NOW(), 'active')");
            $stmt->execute([$member_id, $amount, $due_date, $category_id, $description]);
            echo json_encode(['success' => true, 'message' => 'Pledge added successfully.']);
        } catch (PDOException $e) {
            error_log("Add Pledge Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'get_pledge_details':
        $pledge_id = sanitize($input['pledge_id'] ?? '');
        if (empty($pledge_id)) {
            echo json_encode(['success' => false, 'message' => 'Pledge ID is required.']);
            exit();
        }
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    p.pledge_id, p.amount, p.due_date, p.status, p.notes as description, p.category_id,
                    m.first_name, m.last_name
                FROM pledges p
                JOIN members m ON p.member_id = m.member_id
                WHERE p.pledge_id = ?
            ");
            $stmt->execute([$pledge_id]);
            $pledge = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($pledge) {
                echo json_encode(['success' => true, 'pledge' => $pledge]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Pledge not found.']);
            }
        } catch (PDOException $e) {
            error_log("Get Pledge Details Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'update_pledge':
        $pledge_id = sanitize($input['pledge_id'] ?? '');
        $amount = sanitize($input['amount'] ?? '');
        $due_date = sanitize($input['due_date'] ?? '');
        $category_id = sanitize($input['category_id'] ?? '');
        $status = sanitize($input['status'] ?? '');
        $description = sanitize($input['description'] ?? '');

        if (empty($pledge_id) || empty($amount) || !is_numeric($amount) || $amount <= 0 || empty($status)) {
            echo json_encode(['success' => false, 'message' => 'Invalid pledge data.']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("UPDATE pledges SET amount = ?, due_date = ?, category_id = ?, status = ?, description = ? WHERE pledge_id = ?");
            $stmt->execute([$amount, $due_date, $category_id, $status, $description, $pledge_id]);
            echo json_encode(['success' => true, 'message' => 'Pledge updated successfully.']);
        } catch (PDOException $e) {
            error_log("Update Pledge Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'delete_pledge':
        $pledge_id = sanitize($input['pledge_id'] ?? '');
        if (empty($pledge_id)) {
            echo json_encode(['success' => false, 'message' => 'Pledge ID is required.']);
            exit();
        }
        try {
            // Soft delete: Change status to 'archived'
            $stmt = $pdo->prepare("UPDATE pledges SET status = 'archived' WHERE pledge_id = ?");
            $stmt->execute([$pledge_id]);
            echo json_encode(['success' => true, 'message' => 'Pledge archived successfully.']);
        } catch (PDOException $e) {
            error_log("Delete Pledge Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'record_payment':
        // Implement record payment logic
        $pledge_id = sanitize($input['pledge_id'] ?? '');
        $amount_paid = sanitize($input['amount_paid'] ?? '');
        $payment_date = sanitize($input['payment_date'] ?? '');
        $payment_method = sanitize($input['payment_method'] ?? '');

        if (empty($pledge_id) || empty($amount_paid) || !is_numeric($amount_paid) || $amount_paid <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid pledge ID or amount paid.']);
            exit();
        }

        try {
            $pdo->beginTransaction();

            // Insert new transaction
            $stmt = $pdo->prepare("INSERT INTO transactions (pledge_id, amount_paid, payment_date, payment_method, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([$pledge_id, $amount_paid, $payment_date, $payment_method]);

            // Update pledge status if fully paid
            $stmt = $pdo->prepare("SELECT SUM(amount_paid) as total_paid FROM transactions WHERE pledge_id = ?");
            $stmt->execute([$pledge_id]);
            $current_total_paid = $stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT amount FROM pledges WHERE pledge_id = ?");
            $stmt->execute([$pledge_id]);
            $pledge_amount = $stmt->fetchColumn();

            if ($current_total_paid >= $pledge_amount) {
                $stmt = $pdo->prepare("UPDATE pledges SET status = 'completed' WHERE pledge_id = ?");
                $stmt->execute([$pledge_id]);
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Payment recorded successfully.']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Record Payment Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'get_payment_details':
        $payment_id = sanitize($input['payment_id'] ?? '');
        if (empty($payment_id)) {
            echo json_encode(['success' => false, 'message' => 'Payment ID is required.']);
            exit();
        }
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    t.transaction_id, t.pledge_id, t.amount_paid, t.payment_date, t.payment_method,
                    p.amount as pledge_amount, p.notes as pledge_description,
                    m.first_name, m.last_name
                FROM transactions t
                JOIN pledges p ON t.pledge_id = p.pledge_id
                JOIN members m ON p.member_id = m.member_id
                WHERE t.transaction_id = ?
            ");
            $stmt->execute([$payment_id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($payment) {
                echo json_encode(['success' => true, 'payment' => $payment]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Payment not found.']);
            }
        } catch (PDOException $e) {
            error_log("Get Payment Details Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'update_payment':
        $transaction_id = sanitize($input['transaction_id'] ?? '');
        $amount_paid = sanitize($input['amount_paid'] ?? '');
        $payment_date = sanitize($input['payment_date'] ?? '');
        $payment_method = sanitize($input['payment_method'] ?? '');

        if (empty($transaction_id) || empty($amount_paid) || !is_numeric($amount_paid) || $amount_paid <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid payment data.']);
            exit();
        }

        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE transactions SET amount_paid = ?, payment_date = ?, payment_method = ? WHERE transaction_id = ?");
            $stmt->execute([$amount_paid, $payment_date, $payment_method, $transaction_id]);

            // Re-evaluate pledge status after payment update
            $stmt = $pdo->prepare("SELECT pledge_id FROM transactions WHERE transaction_id = ?");
            $stmt->execute([$transaction_id]);
            $pledge_id = $stmt->fetchColumn();

            if ($pledge_id) {
                $stmt = $pdo->prepare("SELECT SUM(amount_paid) as total_paid FROM transactions WHERE pledge_id = ?");
                $stmt->execute([$pledge_id]);
                $current_total_paid = $stmt->fetchColumn();

                $stmt = $pdo->prepare("SELECT amount FROM pledges WHERE pledge_id = ?");
                $stmt->execute([$pledge_id]);
                $pledge_amount = $stmt->fetchColumn();

                if ($current_total_paid >= $pledge_amount) {
                    $stmt = $pdo->prepare("UPDATE pledges SET status = 'completed' WHERE pledge_id = ?");
                    $stmt->execute([$pledge_id]);
                } else {
                    // If payment was reduced and pledge is no longer fully paid, revert status from completed to active
                    $stmt = $pdo->prepare("UPDATE pledges SET status = 'active' WHERE pledge_id = ? AND status = 'completed'");
                    $stmt->execute([$pledge_id]);
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Payment updated successfully.']);
        } catch (PDOException $e) {
            $pdo->rollBack();
            error_log("Update Payment Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'delete_payment':
        $transaction_id = sanitize($input['transaction_id'] ?? '');
        if (empty($transaction_id)) {
            echo json_encode(['success' => false, 'message' => 'Payment ID is required.']);
            exit();
        }
        try {
            // Soft delete: Change status to 'archived' (assuming a status column in transactions table)
            // If no status column, you might move it to an archive table or hard delete if acceptable.
            // For now, let's assume a 'status' column in transactions and set it to 'archived'.
            // NOTE: You might need to add a 'status' column to your 'transactions' table if it doesn't exist.
            $stmt = $pdo->prepare("UPDATE transactions SET status = 'archived' WHERE transaction_id = ?");
            $stmt->execute([$transaction_id]);
            echo json_encode(['success' => true, 'message' => 'Payment archived successfully.']);
        } catch (PDOException $e) {
            error_log("Delete Payment Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'add_category':
        // Implement add category logic
        $category_name = sanitize($input['category_name'] ?? '');
        $description = sanitize($input['description'] ?? '');

        if (empty($category_name)) {
            echo json_encode(['success' => false, 'message' => 'Category name cannot be empty.']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO pledge_categories (category_name, description) VALUES (?, ?)");
            $stmt->execute([$category_name, $description]);
            echo json_encode(['success' => true, 'message' => 'Category added successfully.']);
        } catch (PDOException $e) {
            error_log("Add Category Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'update_category':
        $category_id = sanitize($input['category_id'] ?? '');
        $category_name = sanitize($input['category_name'] ?? '');
        $description = sanitize($input['description'] ?? '');

        if (empty($category_id) || empty($category_name)) {
            echo json_encode(['success' => false, 'message' => 'Category ID and name are required.']);
            exit();
        }

        try {
            $stmt = $pdo->prepare("UPDATE pledge_categories SET category_name = ?, description = ? WHERE category_id = ?");
            $stmt->execute([$category_name, $description, $category_id]);
            echo json_encode(['success' => true, 'message' => 'Category updated successfully.']);
        } catch (PDOException $e) {
            error_log("Update Category Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'get_categories':
        // Implement get categories logic
        try {
            $stmt = $pdo->query("SELECT category_id, category_name, description FROM pledge_categories ORDER BY category_name ASC");
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'categories' => $categories]);
        } catch (PDOException $e) {
            error_log("Get Categories Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'get_members':
        // Implement get members logic (for pledge creation dropdown)
        try {
            $stmt = $pdo->query("SELECT member_id, first_name, last_name FROM members ORDER BY first_name ASC");
            $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'members' => $members]);
        } catch (PDOException $e) {
            error_log("Get Members Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'delete_category':
        // Implement delete category logic
        $category_id = sanitize($input['category_id'] ?? '');

        if (empty($category_id)) {
            echo json_encode(['success' => false, 'message' => 'Category ID cannot be empty.']);
            exit();
        }

        try {
            // Check if any pledges are assigned to this category
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM pledges WHERE category_id = ?");
            $stmt->execute([$category_id]);
            $pledge_count = $stmt->fetchColumn();

            if ($pledge_count > 0) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete category: Pledges are assigned to it.']);
                exit();
            }

            $stmt = $pdo->prepare("DELETE FROM pledge_categories WHERE category_id = ?");
            $stmt->execute([$category_id]);
            echo json_encode(['success' => true, 'message' => 'Category deleted successfully.']);
        } catch (PDOException $e) {
            error_log("Delete Category Error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        break;

    case 'create_report':
        // Placeholder for create report logic
        // In a real scenario, this would trigger a report generation process (e.g., a background job)
        // and return a link to the generated report or a status update.
        $report_type = sanitize($input['report_type'] ?? '');
        $start_date = sanitize($input['start_date'] ?? '');
        $end_date = sanitize($input['end_date'] ?? '');

        // Log the report request for future processing
        error_log("Report Request: Type - {$report_type}, Start - {$start_date}, End - {$end_date} by User ID: {$user_id}");

        echo json_encode(['success' => true, 'message' => 'Report request received. Your custom report will be generated shortly.']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
        break;
}

?>