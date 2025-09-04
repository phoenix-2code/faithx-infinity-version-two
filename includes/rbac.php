<?php
/**
 * Role-Based Access Control (RBAC) System for FaithX Infinity
 * Handles permissions, group assignments, and access control
 */

class RBAC {
    private $pdo;
    private $user_id;
    private $user_role;
    private $user_groups;
    private $permissions_cache;

    public function __construct($pdo, $user_id = null) {
        $this->pdo = $pdo;
        $this->user_id = $user_id ?? $_SESSION['user_id'] ?? null;
        $this->loadUserData();
    }

    /**
     * Load user data and cache permissions
     */
    private function loadUserData() {
        if (!$this->user_id) return;

        // Get user role
        $stmt = $this->pdo->prepare("SELECT role FROM users WHERE user_id = ?");
        $stmt->execute([$this->user_id]);
        $this->user_role = $stmt->fetchColumn();

        // Get user groups
        $stmt = $this->pdo->prepare("
            SELECT g.*, uga.access_level as role_in_group, 1 as is_primary_group 
            FROM groups g 
            JOIN user_group_access uga ON g.group_id = uga.group_id 
            WHERE uga.user_id = ?
        ");
        $stmt->execute([$this->user_id]);
        $this->user_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Cache permissions
        $this->loadPermissions();
    }

    /**
     * Load and cache user permissions
     */
    private function loadPermissions() {
        $stmt = $this->pdo->prepare("
            SELECT DISTINCT permission_name, resource_type, action_type, scope_level
            FROM user_effective_permissions 
            WHERE user_id = ?
        ");
        $stmt->execute([$this->user_id]);
        $this->permissions_cache = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Check if user has specific permission
     */
    public function hasPermission($permission_name, $resource_type = null, $action_type = null, $scope_level = null) {
        if (!$this->permissions_cache) return false;

        // System admin has all permissions
        if ($this->user_role === 'system_admin') return true;

        foreach ($this->permissions_cache as $perm) {
            if ($perm['permission_name'] === $permission_name || $perm['permission_name'] === 'full_access') {
                if ($resource_type && $perm['resource_type'] !== $resource_type && $perm['resource_type'] !== '*') continue;
                if ($action_type && $perm['action_type'] !== $action_type && $perm['action_type'] !== '*') continue;
                if ($scope_level && $perm['scope_level'] !== $scope_level) continue;
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user can access specific group data
     */
    public function canAccessGroup($group_id, $required_role = 'member') {
        if ($this->user_role === 'system_admin') return true;
        if ($this->user_role === 'elder_admin') return true; // Elder admin can access all groups (summary level)

        foreach ($this->user_groups as $group) {
            if ($group['group_id'] == $group_id) {
                $role_hierarchy = ['member' => 1, 'finance_officer' => 2, 'leader' => 3];
                $user_level = $role_hierarchy[$group['role_in_group']] ?? 0;
                $required_level = $role_hierarchy[$required_role] ?? 0;
                return $user_level >= $required_level;
            }
        }
        return false;
    }

    public function isMemberOfGroup($member_id, $group_id) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM members WHERE member_id = ? AND group_id = ?");
        $stmt->execute([$member_id, $group_id]);
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Get groups user can access with specific role
     */
    public function getAccessibleGroups($min_role = 'member') {
        if ($this->user_role === 'system_admin' || $this->user_role === 'elder_admin') {
            // Return all groups for system/elder admin
            $stmt = $this->pdo->prepare("SELECT * FROM groups WHERE is_active = 1");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        $role_hierarchy = ['member' => 1, 'finance_officer' => 2, 'leader' => 3];
        $required_level = $role_hierarchy[$min_role] ?? 0;

        $accessible_groups = [];
        foreach ($this->user_groups as $group) {
            $user_level = $role_hierarchy[$group['role_in_group']] ?? 0;
            if ($user_level >= $required_level) {
                $accessible_groups[] = $group;
            }
        }
        return $accessible_groups;
    }

    /**
     * Check if user can access specific pledge
     */
    public function canAccessPledge($pledge_id) {
        if ($this->user_role === 'system_admin') return true;

        $stmt = $this->pdo->prepare("
            SELECT p.*, m.user_id as pledge_owner, m.group_id as pledge_group_id
            FROM pledges p 
            JOIN members m ON p.member_id = m.member_id 
            WHERE p.pledge_id = ?
        ");
        $stmt->execute([$pledge_id]);
        $pledge = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$pledge) return false;

        // Own pledge
        if ($pledge['pledge_owner'] == $this->user_id) return true;

        // Elder admin can see summaries but not individual pledges (unless own)
        if ($this->user_role === 'elder_admin') return false;

        // Group access for finance officers
        if ($this->user_role === 'finance_officer') {
            foreach ($this->user_groups as $group) {
                if ($group['group_id'] == $pledge['pledge_group_id']) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get pledges user can access
     */
    public function getAccessiblePledges($filters = []) {
        $where_conditions = [];
        $params = [];

        if ($this->user_role === 'system_admin') {
            // System admin sees all
            $where_conditions[] = "1=1";
        } elseif ($this->user_role === 'chief_finance_officer') {
            // Chief Finance Officer sees all pledges
            $where_conditions[] = "1=1";
        } elseif ($this->user_role === 'finance_officer') {
            $group_ids = array_column($this->user_groups, 'group_id');
            if (empty($group_ids)) {
                return [];
            }
            $placeholders = str_repeat('?,', count($group_ids) - 1) . '?';
            $where_conditions[] = "p.group_id IN ($placeholders)";
            $params = array_merge($params, $group_ids);
        } else {
            // Members see only their own pledges
            $where_conditions[] = "m.user_id = ?";
            $params[] = $this->user_id;
        }

        // Add filters
        if (!empty($filters['status'])) {
            $where_conditions[] = "p.status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['group_id'])) {
            $where_conditions[] = "p.group_id = ?";
            $params[] = $filters['group_id'];
        }

        $sql = "
            SELECT p.*, pc.category_name, m.first_name, m.last_name, g.group_name,
                   COALESCE(SUM(t.amount_paid), 0) as paid_amount
            FROM pledges p
            JOIN members m ON p.member_id = m.member_id
            LEFT JOIN pledge_categories pc ON p.category_id = pc.category_id
            LEFT JOIN groups g ON p.group_id = g.group_id
            LEFT JOIN transactions t ON p.pledge_id = t.pledge_id
            WHERE " . implode(" AND ", $where_conditions) . "
            GROUP BY p.pledge_id
            ORDER BY p.created_at DESC
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get financial summary based on user role
     */
    public function getFinancialSummary() {
        if ($this->user_role === 'system_admin') {
            return $this->getSystemAdminSummary();
        } elseif ($this->user_role === 'chief_finance_officer') {
            return $this->getCFOSummary();
        } elseif ($this->user_role === 'elder_admin') {
            return $this->getElderAdminSummary();
        } elseif ($this->user_role === 'finance_officer') {
            return $this->getFinanceOfficerSummary();
        } else {
            return $this->getMemberSummary();
        }
    }

    /**
     * Get member's own financial summary
     */
    private function getMemberSummary() {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(p.pledge_id) as total_pledges,
                SUM(p.amount) as total_pledged,
                SUM(CASE WHEN p.status = 'completed' THEN p.amount ELSE 0 END) as completed_amount,
                COALESCE(SUM(t.amount_paid), 0) as total_paid,
                (SUM(p.amount) - COALESCE(SUM(t.amount_paid), 0)) as remaining_amount
            FROM pledges p
            JOIN members m ON p.member_id = m.member_id
            LEFT JOIN transactions t ON p.pledge_id = t.pledge_id
            WHERE m.user_id = ?
        ");
        $stmt->execute([$this->user_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get finance officer's group summary
     */
    private function getFinanceOfficerSummary() {
        $finance_groups = [];
        foreach ($this->user_groups as $group) {
            if ($group['role_in_group'] === 'finance_officer') {
                $finance_groups[] = $group['group_id'];
            }
        }

        if (empty($finance_groups)) {
            return $this->getMemberSummary();
        }

        $placeholders = str_repeat('?,', count($finance_groups) - 1) . '?';
        $stmt = $this->pdo->prepare("
            SELECT * FROM group_financial_summary 
            WHERE group_id IN ($placeholders)
        ");
        $stmt->execute($finance_groups);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get elder admin's church-wide summary
     */
    private function getElderAdminSummary() {
        $stmt = $this->pdo->prepare("SELECT * FROM group_financial_summary");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get CFO's comprehensive summary (church-wide + building fund focus)
     */
    private function getCFOSummary() {
        // Get church-wide summary like Elder Admin
        $church_summary = $this->getElderAdminSummary();
        
        // Get building fund specific data
        $stmt = $this->pdo->prepare("SELECT * FROM cfo_building_fund_dashboard");
        $stmt->execute();
        $building_fund_projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get church-wide totals
        $stmt = $this->pdo->prepare("SELECT * FROM cfo_church_summary");
        $stmt->execute();
        $church_totals = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get pending group transfers
        $stmt = $this->pdo->prepare("
            SELECT * FROM group_transfer_approval_queue 
            WHERE final_status = 'pending' 
            ORDER BY created_at ASC
        ");
        $stmt->execute();
        $pending_transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'church_summary' => $church_summary,
            'building_fund_projects' => $building_fund_projects,
            'church_totals' => $church_totals,
            'pending_transfers' => $pending_transfers,
            'role_type' => 'cfo'
        ];
    }

    /**
     * Get system admin's complete summary
     */
    private function getSystemAdminSummary() {
        // Return both group summaries and system-wide totals
        $group_summary = $this->getElderAdminSummary();
        
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(DISTINCT u.user_id) as total_users,
                COUNT(DISTINCT p.pledge_id) as total_pledges,
                COUNT(DISTINCT g.group_id) as total_groups,
                SUM(p.amount) as total_pledged,
                COALESCE(SUM(t.amount_paid), 0) as total_paid
            FROM users u
            LEFT JOIN members m ON u.user_id = m.user_id
            LEFT JOIN pledges p ON m.member_id = p.member_id
            LEFT JOIN transactions t ON p.pledge_id = t.pledge_id
            LEFT JOIN groups g ON 1=1
        ");
        $stmt->execute();
        $system_totals = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'groups' => $group_summary,
            'totals' => $system_totals
        ];
    }

    /**
     * Log user action for audit trail
     */
    

    /**
     * Assign user to group with specific role
     */
    public function assignUserToGroup($user_id, $group_id, $role_in_group = 'member', $is_primary = false) {
        if (!$this->hasPermission('assign_finance_officers') && !$this->hasPermission('full_access')) {
            throw new Exception("Insufficient permissions to assign users to groups");
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO user_group_access (user_id, group_id, access_level)
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                access_level = VALUES(access_level)
        ");

        $result = $stmt->execute([$user_id, $group_id, $role_in_group]);
        
        if ($result) {
            $this->logAction('assign_user_to_group', 'user_group_access', null, null, [
                'user_id' => $user_id,
                'group_id' => $group_id,
                'access_level' => $role_in_group
            ]);
        }

        return $result;
    }

    /**
     * Grant temporary role to user (CFO and Elder Admin only)
     */
    public function grantTemporaryRole($target_user_id, $temporary_role, $duration_hours, $reason) {
        if (!$this->hasPermission('grant_temporary_roles')) {
            throw new Exception("Insufficient permissions to grant temporary roles");
        }

        $end_date = date('Y-m-d H:i:s', strtotime("+{$duration_hours} hours"));
        
        $stmt = $this->pdo->prepare("
            INSERT INTO temporary_role_assignments (user_id, temporary_role, assigned_by, reason, end_date)
            VALUES (?, ?, ?, ?, ?)
        ");

        $result = $stmt->execute([$target_user_id, $temporary_role, $this->user_id, $reason, $end_date]);
        
        if ($result) {
            $this->logAction('grant_temporary_role', 'temporary_role_assignments', $this->pdo->lastInsertId(), null, [
                'target_user_id' => $target_user_id,
                'temporary_role' => $temporary_role,
                'duration_hours' => $duration_hours,
                'reason' => $reason
            ]);
        }

        return $result;
    }

    /**
     * Activate break glass emergency access (System Admin only)
     */
    public function activateBreakGlass($target_user_id, $emergency_reason, $duration_hours = 1) {
        if ($this->user_role !== 'system_admin') {
            throw new Exception("Only System Admin can activate break glass protocol");
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO break_glass_access (user_id, activated_by, emergency_reason, access_level, start_time)
            VALUES (?, ?, ?, 'system_override', NOW())
        ");

        $result = $stmt->execute([$target_user_id, $this->user_id, $emergency_reason]);
        
        if ($result) {
            $access_id = $this->pdo->lastInsertId();
            
            // Log the break glass activation
            $this->logAction('activate_break_glass', 'break_glass_access', $access_id, null, [
                'target_user_id' => $target_user_id,
                'emergency_reason' => $emergency_reason,
                'duration_hours' => $duration_hours
            ]);

            // Notify all admins (implementation would depend on notification system)
            $this->notifyAdminsBreakGlass($target_user_id, $emergency_reason);
        }

        return $result;
    }

    /**
     * Request group transfer (CFO or System Admin)
     */
    public function requestGroupTransfer($user_id, $to_group_id, $reason, $from_group_id = null) {
        if (!$this->hasPermission('manage_group_transfers')) {
            throw new Exception("Insufficient permissions to request group transfers");
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO group_transfer_requests (user_id, from_group_id, to_group_id, requested_by, transfer_reason)
            VALUES (?, ?, ?, ?, ?)
        ");

        $result = $stmt->execute([$user_id, $from_group_id, $to_group_id, $this->user_id, $reason]);
        
        if ($result) {
            $this->logAction('request_group_transfer', 'group_transfer_requests', $this->pdo->lastInsertId(), null, [
                'user_id' => $user_id,
                'from_group_id' => $from_group_id,
                'to_group_id' => $to_group_id,
                'reason' => $reason
            ]);
        }

        return $result;
    }

    /**
     * Approve group transfer (CFO or Elder Admin)
     */
    public function approveGroupTransfer($request_id, $approval_notes = '') {
        if (!$this->hasPermission('approve_group_transfers')) {
            throw new Exception("Insufficient permissions to approve group transfers");
        }

        $approval_field = ($this->user_role === 'chief_finance_officer') ? 'cfo_approval_status' : 'elder_approval_status';
        $approver_field = ($this->user_role === 'chief_finance_officer') ? 'cfo_approved_by' : 'elder_approved_by';
        $date_field = ($this->user_role === 'chief_finance_officer') ? 'cfo_approval_date' : 'elder_approval_date';

        $stmt = $this->pdo->prepare("
            UPDATE group_transfer_requests 
            SET {$approval_field} = 'approved', 
                {$approver_field} = ?, 
                {$date_field} = NOW(),
                notes = CONCAT(COALESCE(notes, ''), ?)
            WHERE request_id = ?
        ");

        $notes_addition = "\n[" . date('Y-m-d H:i:s') . "] " . $this->user_role . " approval: " . $approval_notes;
        $result = $stmt->execute([$this->user_id, $notes_addition, $request_id]);
        
        if ($result) {
            $this->logAction('approve_group_transfer', 'group_transfer_requests', $request_id, null, [
                'approval_type' => $this->user_role,
                'notes' => $approval_notes
            ]);
        }

        return $result;
    }

    /**
     * Get building fund projects (CFO access)
     */
    public function getBuildingFundProjects() {
        if (!$this->hasPermission('manage_building_fund')) {
            throw new Exception("Insufficient permissions to access building fund data");
        }

        $stmt = $this->pdo->prepare("SELECT * FROM cfo_building_fund_dashboard ORDER BY project_id DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Create building fund project (CFO only)
     */
    public function createBuildingProject($project_name, $description, $target_amount, $start_date, $target_completion_date) {
        if (!$this->hasPermission('manage_building_projects')) {
            throw new Exception("Insufficient permissions to create building projects");
        }

        $stmt = $this->pdo->prepare("
            INSERT INTO building_fund_projects (project_name, project_description, target_amount, start_date, target_completion_date, created_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $result = $stmt->execute([$project_name, $description, $target_amount, $start_date, $target_completion_date, $this->user_id]);
        
        if ($result) {
            $project_id = $this->pdo->lastInsertId();
            $this->logAction('create_building_project', 'building_fund_projects', $project_id, null, [
                'project_name' => $project_name,
                'target_amount' => $target_amount
            ]);
        }

        return $result;
    }

    /**
     * Notify admins of break glass activation
     */
    private function notifyAdminsBreakGlass($target_user_id, $reason) {
        // Get target user info
        $stmt = $this->pdo->prepare("SELECT username, first_name, last_name FROM users WHERE user_id = ?");
        $stmt->execute([$target_user_id]);
        $target_user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Log notification (actual email/SMS implementation would go here)
        $this->logAction('break_glass_notification', 'system_notifications', null, null, [
            'target_user' => $target_user['username'],
            'target_name' => $target_user['first_name'] . ' ' . $target_user['last_name'],
            'reason' => $reason,
            'activated_by' => $this->user_id,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get user's role and groups info
     */
    public function getUserInfo() {
        return [
            'user_id' => $this->user_id,
            'system_role' => $this->user_role,
            'groups' => $this->user_groups,
            'permissions' => $this->permissions_cache
        ];
    }
}

/**
 * Helper functions for backward compatibility
 */
function getRBAC() {
    global $pdo;
    static $rbac = null;
    if ($rbac === null) {
        $rbac = new RBAC($pdo);
    }
    return $rbac;
}

function hasRBACPermission($permission_name, $resource_type = null, $action_type = null, $scope_level = null) {
    return getRBAC()->hasPermission($permission_name, $resource_type, $action_type, $scope_level);
}

function canAccessGroup($group_id, $required_role = 'member') {
    return getRBAC()->canAccessGroup($group_id, $required_role);
}
?>