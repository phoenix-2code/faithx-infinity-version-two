<?php
// API Endpoint for Data Export

session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

// Check if user is logged in and has permission to export (e.g., finance_officer, cfo, elder_admin, system_admin)
if (!isLoggedIn() || !in_array($_SESSION['role'], ['finance_officer', 'chief_finance_officer', 'elder_admin', 'system_admin'])) {
    http_response_code(403);
    exit('Unauthorized access');
}

$format = $_GET['format'] ?? 'csv';
$search_term = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build WHERE clause for filtering
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
if (!empty($date_from)) {
    $where_clauses[] = "DATE(p.created_at) >= ?";
    $params[] = $date_from;
}
if (!empty($date_to)) {
    $where_clauses[] = "DATE(p.created_at) <= ?";
    $params[] = $date_to;
}

$where_sql = count($where_clauses) > 0 ? " WHERE " . implode(" AND ", $where_clauses) : "";

// Fetch all data based on filters
$sql = "
    SELECT p.pledge_id, p.amount, p.pledge_date, p.due_date, p.status, p.notes,
           pc.category_name, m.first_name, m.last_name, g.group_name,
           COALESCE(SUM(t.amount_paid), 0) as paid_amount
    FROM pledges p
    JOIN members m ON p.member_id = m.member_id
    LEFT JOIN pledge_categories pc ON p.category_id = pc.category_id
    LEFT JOIN groups g ON p.group_id = g.group_id
    LEFT JOIN transactions t ON p.pledge_id = t.pledge_id
    " . $where_sql . "
    GROUP BY p.pledge_id
    ORDER BY p.created_at DESC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $pledge_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Export data query failed: " . $e->getMessage());
    http_response_code(500);
    exit('Error fetching data for export.');
}

// Log the export action
logAction('data_export', 'pledges', null, [
    'format' => $format,
    'filters' => [
        'search' => $search_term,
        'status' => $status_filter,
        'date_from' => $date_from,
        'date_to' => $date_to
    ],
    'exported_by' => $_SESSION['username'] ?? 'anonymous',
    'num_records' => count($pledge_data)
]);

if ($format === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="pledges_export_' . date('Ymd_His') . '.csv"');

    $output = fopen('php://output', 'w');

    // CSV Headers
    fputcsv($output, [
        'Pledge ID', 'Member Name', 'Group Name', 'Category', 'Pledge Amount', 
        'Paid Amount', 'Remaining Amount', 'Pledge Date', 'Due Date', 'Status', 'Notes'
    ]);

    // CSV Data
    foreach ($pledge_data as $row) {
        $remaining_amount = $row['amount'] - $row['paid_amount'];
        fputcsv($output, [
            $row['pledge_id'],
            $row['first_name'] . ' ' . $row['last_name'],
            $row['group_name'],
            $row['category_name'],
            $row['amount'],
            $row['paid_amount'],
            $remaining_amount,
            $row['pledge_date'],
            $row['due_date'],
            $row['status'],
            $row['notes']
        ]);
    }

    fclose($output);
    exit;
} elseif ($format === 'pdf') {
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="pledges_report_' . date('Ymd_His') . '.pdf"');

    $output_content = "FaithX Infinity Pledge Report\n\n";
    $output_content .= "Generated on: " . date('Y-m-d H:i:s') . "\n";
    $output_content .= "Filters: Search='$search_term', Status='$status_filter', From='$date_from', To='$date_to'\n\n";

    // Headers
    $output_content .= sprintf("%-5s %-20s %-15s %-15s %-15s %-15s %-15s %-12s %-12s %-10s\n",
        "ID", "Member", "Group", "Category", "Pledged", "Paid", "Remaining", "Pledge Date", "Due Date", "Status");
    $output_content .= str_repeat("-", 140) . "\n";

    // Data
    foreach ($pledge_data as $row) {
        $remaining_amount = $row['amount'] - $row['paid_amount'];
        $output_content .= sprintf("%-5s %-20s %-15s %-15s %-15.2f %-15.2f %-15.2f %-12s %-12s %-10s\n",
            $row['pledge_id'],
            substr($row['first_name'] . ' ' . $row['last_name'], 0, 19),
            substr($row['group_name'], 0, 14),
            substr($row['category_name'], 0, 14),
            $row['amount'],
            $row['paid_amount'],
            $remaining_amount,
            $row['pledge_date'],
            $row['due_date'],
            $row['status']
        );
    }

    echo $output_content;
    exit;
} else {
    http_response_code(400);
    exit('Invalid export format.');
}

?>