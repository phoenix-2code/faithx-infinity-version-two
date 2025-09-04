<?php
/**
 * CFO CRUD Operations Dashboard
 */
requireRole('chief_finance_officer');

$user_id = $_SESSION['user_id'];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Security token mismatch.');
    } else {
        $action = $_POST['action'] ?? '';
        
        try {
            switch ($action) {
                case 'create_category':
                    $category_name = sanitize($_POST['category_name']);
                    $description = sanitize($_POST['description']);
                    $group_ids = $_POST['group_ids'] ?? [];
                    
                    if (!$category_name) {
                        throw new Exception('Category name is required.');
                    }
                    
                    // Create category for each selected group
                    foreach ($group_ids as $group_id) {
                        $stmt = $pdo->prepare("
                            INSERT INTO pledge_categories (group_id, category_name, description, created_by)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([$group_id, $category_name, $description, $user_id]);
                    }
                    
                    setFlashMessage('success', 'Pledge category created successfully for selected groups.');
                    break;
                    
                case 'update_pledge':
                    $pledge_id = (int)$_POST['pledge_id'];
                    $amount = (float)$_POST['amount'];
                    $status = sanitize($_POST['status']);
                    $category_id = (int)$_POST['category_id'];
                    
                    $stmt = $pdo->prepare("
                        UPDATE pledges 
                        SET amount = ?, status = ?, category_id = ?
                        WHERE pledge_id = ?
                    ");
                    $stmt->execute([$amount, $status, $category_id, $pledge_id]);
                    
                    setFlashMessage('success', 'Pledge updated successfully.');
                    break;
                    
                case 'delete_pledge':
                    $pledge_id = (int)$_POST['pledge_id'];
                    
                    // Check if pledge has payments
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE pledge_id = ?");
                    $stmt->execute([$pledge_id]);
                    $payment_count = $stmt->fetchColumn();
                    
                    if ($payment_count > 0) {
                        throw new Exception('Cannot delete pledge with existing payments. Cancel it instead.');
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM pledges WHERE pledge_id = ?");
                    $stmt->execute([$pledge_id]);
                    
                    setFlashMessage('success', 'Pledge deleted successfully.');
                    break;
            }
        } catch (Exception $e) {
            setFlashMessage('danger', $e->getMessage());
        }
        
        redirect('index.php?page=dashboard&tab=crud');
    }
}

// Get data for forms
$groups = [];
$categories = [];
$pledges = [];

try {
    // Get all groups
    $stmt = $pdo->query("SELECT * FROM groups ORDER BY group_name");
    $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all categories
    $stmt = $pdo->query("SELECT pc.*, g.group_name FROM pledge_categories pc LEFT JOIN groups g ON pc.group_id = g.group_id ORDER BY pc.category_name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent pledges
    $stmt = $pdo->prepare("
        SELECT p.*, pc.category_name, m.first_name, m.last_name, g.group_name
        FROM pledges p
        JOIN members m ON p.member_id = m.member_id
        LEFT JOIN pledge_categories pc ON p.category_id = pc.category_id
        LEFT JOIN groups g ON p.group_id = g.group_id
        ORDER BY p.created_at DESC
        LIMIT 20
    ");
    $stmt->execute();
    $pledges = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error_message = "Error loading data: " . $e->getMessage();
}

$csrf_token = generateCSRFToken();
?>

<!-- Back Button -->
<div class="mb-3">
    <button onclick="history.back()" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> Back to Dashboard
    </button>
</div>

<div class="container-fluid">
    <!-- Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header gradient-header">
                    <h2 class="mb-0"><i class="bi bi-tools"></i> CFO Management Tools</h2>
                    <p class="mb-0">Create, Update, and Delete operations</p>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
    </div>
    <?php endif; ?>

    <?php 
    $flash = getFlashMessage();
    if ($flash): 
    ?>
    <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- CRUD Operations Tabs -->
    <ul class="nav nav-tabs" id="crudTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="create-tab" data-bs-toggle="tab" data-bs-target="#create" type="button">
                <i class="bi bi-plus-circle"></i> Create
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="update-tab" data-bs-toggle="tab" data-bs-target="#update" type="button">
                <i class="bi bi-pencil-square"></i> Update
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="delete-tab" data-bs-toggle="tab" data-bs-target="#delete" type="button">
                <i class="bi bi-trash"></i> Delete
            </button>
        </li>
    </ul>

    <div class="tab-content" id="crudTabContent">
        <!-- Create Tab -->
        <div class="tab-pane fade show active" id="create" role="tabpanel">
            <div class="card">
                <div class="card-header gradient-header">
                    <h5 class="mb-0"><i class="bi bi-plus-circle"></i> Create New Pledge Category</h5>
                </div>
                <div class="card-body">
                    <form method="POST" class="needs-validation" novalidate>
                        <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                        <input type="hidden" name="action" value="create_category">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="category_name" class="form-label">Category Name</label>
                                    <input type="text" class="form-control" id="category_name" name="category_name" required>
                                    <div class="invalid-feedback">Please enter a category name.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="description" class="form-label">Description</label>
                                    <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Assign to Groups</label>
                                    <div class="border rounded p-3" style="max-height: 200px; overflow-y: auto;">
                                        <?php foreach ($groups as $group): ?>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="group_ids[]" 
                                                   value="<?= $group['group_id'] ?>" id="group_<?= $group['group_id'] ?>">
                                            <label class="form-check-label" for="group_<?= $group['group_id'] ?>">
                                                <?= htmlspecialchars($group['group_name']) ?>
                                            </label>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <small class="text-muted">Select which groups can use this category</small>
                                </div>
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Create Category
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Update Tab -->
        <div class="tab-pane fade" id="update" role="tabpanel">
            <div class="card">
                <div class="card-header gradient-header">
                    <h5 class="mb-0"><i class="bi bi-pencil-square"></i> Update Pledge</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Group</th>
                                    <th>Category</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pledges as $pledge): ?>
                                <tr>
                                    <td><?= htmlspecialchars($pledge['first_name'] . ' ' . $pledge['last_name']) ?></td>
                                    <td><?= htmlspecialchars($pledge['group_name'] ?? 'No Group') ?></td>
                                    <td><?= htmlspecialchars($pledge['category_name'] ?? 'General') ?></td>
                                    <td><?= formatCurrency($pledge['amount']) ?></td>
                                    <td><?= getStatusBadge($pledge['status']) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#updateModal<?= $pledge['pledge_id'] ?>">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                    </td>
                                </tr>
                                
                                <!-- Update Modal -->
                                <div class="modal fade" id="updateModal<?= $pledge['pledge_id'] ?>" tabindex="-1">
                                    <div class="modal-dialog">
                                        <div class="modal-content">
                                            <form method="POST">
                                                <div class="modal-header gradient-header">
                                                    <h5 class="modal-title">Update Pledge</h5>
                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                                    <input type="hidden" name="action" value="update_pledge">
                                                    <input type="hidden" name="pledge_id" value="<?= $pledge['pledge_id'] ?>">
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Amount</label>
                                                        <input type="number" name="amount" class="form-control" 
                                                               value="<?= $pledge['amount'] ?>" step="0.01" required>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Status</label>
                                                        <select name="status" class="form-select" required>
                                                            <option value="active" <?= $pledge['status'] === 'active' ? 'selected' : '' ?>>Active</option>
                                                            <option value="pending" <?= $pledge['status'] === 'pending' ? 'selected' : '' ?>>Pending</option>
                                                            <option value="completed" <?= $pledge['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
                                                            <option value="cancelled" <?= $pledge['status'] === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="mb-3">
                                                        <label class="form-label">Category</label>
                                                        <select name="category_id" class="form-select" required>
                                                            <?php foreach ($categories as $category): ?>
                                                            <option value="<?= $category['category_id'] ?>" 
                                                                    <?= $pledge['category_id'] == $category['category_id'] ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($category['category_name']) ?>
                                                                (<?= htmlspecialchars($category['group_name'] ?? 'All Groups') ?>)
                                                            </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                    <button type="submit" class="btn btn-primary">Update Pledge</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Delete Tab -->
        <div class="tab-pane fade" id="delete" role="tabpanel">
            <div class="card">
                <div class="card-header gradient-header">
                    <h5 class="mb-0"><i class="bi bi-trash"></i> Delete Operations</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle"></i>
                        <strong>Warning:</strong> Delete operations are permanent and cannot be undone.
                        Pledges with existing payments cannot be deleted.
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pledges as $pledge): ?>
                                <tr>
                                    <td><?= htmlspecialchars($pledge['first_name'] . ' ' . $pledge['last_name']) ?></td>
                                    <td><?= formatCurrency($pledge['amount']) ?></td>
                                    <td><?= getStatusBadge($pledge['status']) ?></td>
                                    <td><?= formatDate($pledge['created_at']) ?></td>
                                    <td>
                                        <form method="POST" class="d-inline" 
                                              onsubmit="return confirm('Are you sure you want to delete this pledge? This action cannot be undone.')">
                                            <input type="hidden" name="csrf_token" value="<?= $csrf_token ?>">
                                            <input type="hidden" name="action" value="delete_pledge">
                                            <input type="hidden" name="pledge_id" value="<?= $pledge['pledge_id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Form validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });
});
</script>