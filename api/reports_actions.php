<?php
/**
 * Reports Actions API Endpoint
 */

session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/error_handler.php'; // For logging errors

// Check if user is logged in and has permission to view reports
if (!isLoggedIn()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$role = $_SESSION['role'];
$can_view_reports = in_array($role, ['finance_officer', 'chief_finance_officer', 'elder_admin', 'system_admin', 'member']);

if (!$can_view_reports) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Insufficient permissions']);
    exit;
}

$action = $_REQUEST['action'] ?? '';

if ($action === 'download_csv' || $action === 'download_pdf') {
    $input = $_GET;
} else {
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
}

header('Content-Type: application/json');

try {
    // Initialize ErrorHandler with PDO connection
    ErrorHandler::init($pdo, DEBUG_MODE);

    switch ($action) {
        case 'get_reports':
            // Logic to fetch filtered pledges
            $search_term = $input['search'] ?? '';
            $status_filter = $input['status'] ?? '';
            $category_filter = $input['category'] ?? ''; // Added this line
            $date_from = $input['date_from'] ?? '';
            $date_to = $input['date_to'] ?? date('Y-m-d');
            $rp_page = (int)($input['rp_page'] ?? 1);
            $limit_rp = 10;
            $offset_rp = ($rp_page - 1) * $limit_rp;

            $where_clauses = [];
            $params = [];

            if (!empty($search_term)) {
                $where_clauses[] = "(m.first_name LIKE ? OR m.last_name LIKE ? OR g.group_name LIKE ? OR pc.category_name LIKE ?)";
                $params[] = '%' . $search_term . '%';
                $params[] = '%' . $search_term . '%';
                $params[] = '%' . $search_term . '%';
                $params[] = '%' . $search_term . '%';
            }
            if (!empty($status_filter)) {
                $where_clauses[] = "p.status = ?";
                $params[] = $status_filter;
            }
            if (!empty($category_filter)) { // Added this block
                $where_clauses[] = "pc.category_name = ?";
                $params[] = $category_filter;
            }
            if (!empty($date_from)) {
                $where_clauses[] = "p.created_at >= ?";
                $params[] = $date_from;
            }
            if (!empty($date_to)) {
                $where_clauses[] = "p.created_at <= ?";
                $params[] = $date_to;
            }

            $sql_where = count($where_clauses) > 0 ? " WHERE " . implode(" AND ", $where_clauses) : "";

            // Count total pledges for pagination
            $count_stmt = $pdo->prepare("SELECT COUNT(DISTINCT p.pledge_id) FROM pledges p JOIN members m ON p.member_id = m.member_id LEFT JOIN groups g ON p.group_id = g.group_id LEFT JOIN pledge_categories pc ON p.category_id = pc.category_id" . $sql_where);
            $count_stmt->execute($params);
            $total_rp_pledges = $count_stmt->fetchColumn();
            $total_rp_pages = ceil($total_rp_pledges / $limit_rp);

            $stmt = $pdo->prepare("
                SELECT p.pledge_id, p.amount, p.status, p.created_at,
                       m.first_name, m.last_name, g.group_name, pc.category_name,
                       COALESCE(SUM(t.amount_paid), 0) as paid_amount
                FROM pledges p
                JOIN members m ON p.member_id = m.member_id
                LEFT JOIN groups g ON p.group_id = g.group_id
                LEFT JOIN pledge_categories pc ON p.category_id = pc.category_id
                LEFT JOIN transactions t ON p.pledge_id = t.pledge_id
                " . $sql_where . "
                GROUP BY p.pledge_id, pc.category_name
                ORDER BY p.created_at DESC
                LIMIT " . (int)$limit_rp . " OFFSET " . (int)$offset_rp . "
            ");
            
            $stmt->execute($params);
            $recent_pledges = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'pledges' => $recent_pledges,
                'total_pledges' => $total_rp_pledges,
                'total_pages' => $total_rp_pages,
                'current_page' => $rp_page
            ]);
            break;

        case 'download_csv':
            // Logic to generate CSV
            // Headers for CSV download
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="pledge_report_' . date('Ymd_His') . '.csv"');

            $output = fopen('php://output', 'w');
            fputcsv($output, ['Pledge ID', 'Member Name', 'Group Name', 'Category', 'Amount', 'Paid', 'Remaining', 'Status', 'Created At']); // CSV Headers

            // Fetch all filtered data (no pagination for download)
            $search_term = $input['search'] ?? '';
            $status_filter = $input['status'] ?? '';
            $category_filter = $input['category'] ?? ''; // Added this line
            $date_from = $input['date_from'] ?? '';
            $date_to = $input['date_to'] ?? date('Y-m-d');

            $where_clauses = [];
            $params = [];

            if (!empty($search_term)) {
                $where_clauses[] = "(m.first_name LIKE ? OR m.last_name LIKE ? OR g.group_name LIKE ? OR pc.category_name LIKE ?)";
                $params[] = '%' . $search_term . '%';
                $params[] = '%' . $search_term . '%';
                $params[] = '%' . $search_term . '%';
                $params[] = '%' . $search_term . '%';
            }
            if (!empty($status_filter)) {
                $where_clauses[] = "p.status = ?";
                $params[] = $status_filter;
            }
            if (!empty($category_filter)) { // Added this block
                $where_clauses[] = "pc.category_name = ?";
                $params[] = $category_filter;
            }
            if (!empty($date_from)) {
                $where_clauses[] = "p.created_at >= ?";
                $params[] = $date_from;
            }
            if (!empty($date_to)) {
                $where_clauses[] = "p.created_at <= ?";
                $params[] = $date_to;
            }

            $sql_where = count($where_clauses) > 0 ? " WHERE " . implode(" AND ", $where_clauses) : "";

            $stmt = $pdo->prepare("
                SELECT p.pledge_id, p.amount, p.status, p.created_at,
                       m.first_name, m.last_name, g.group_name, pc.category_name,
                       COALESCE(SUM(t.amount_paid), 0) as paid_amount
                FROM pledges p
                JOIN members m ON p.member_id = m.member_id
                LEFT JOIN groups g ON p.group_id = g.group_id
                LEFT JOIN pledge_categories pc ON p.category_id = pc.category_id
                LEFT JOIN transactions t ON p.pledge_id = t.pledge_id
                " . $sql_where . "
                GROUP BY p.pledge_id, pc.category_name
                ORDER BY p.created_at DESC
            ");
            
            $stmt->execute($params);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $remaining = $row['amount'] - $row['paid_amount'];
                fputcsv($output, [
                    $row['pledge_id'],
                    $row['first_name'] . ' ' . $row['last_name'],
                    $row['group_name'],
                    $row['category_name'],
                    $row['amount'],
                    $row['paid_amount'],
                    $remaining,
                    $row['status'],
                    $row['created_at']
                ]);
            }
            fclose($output);
            exit; // Important to exit after file download

        case 'download_pdf':
            header('Location: ../index.php?page=coming_soon');
            exit;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid action specified']);
            break;
    }

} catch (Exception $e) {
    http_response_code(500);
    error_log("Reports API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
    echo json_encode(['success' => false, 'message' => 'An internal server error occurred. Please try again later.']);
}

?>