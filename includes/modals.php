<!-- Create User Modal -->
<div class="modal fade" id="createUserModal" tabindex="-1" aria-labelledby="createUserModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="createUserModalLabel">Create New User</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="createUserForm">
          <input type="hidden" name="action" value="create_user">
          <div class="mb-3">
            <label for="username" class="form-label">Username</label>
            <input type="text" class="form-control" id="username" name="username" required>
          </div>
          <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" name="email" required>
          </div>
          <div class="mb-3">
            <label for="first_name" class="form-label">First Name</label>
            <input type="text" class="form-control" id="first_name" name="first_name" required>
          </div>
          <div class="mb-3">
            <label for="last_name" class="form-label">Last Name</label>
            <input type="text" class="form-control" id="last_name" name="last_name" required>
          </div>
          <div class="mb-3">
            <label for="password" class="form-label">Password</label>
            <input type="password" class="form-control" id="password" name="password" required>
          </div>
          <div class="mb-3">
            <label for="role" class="form-label">Role</label>
            <select class="form-select" id="role" name="role">
              <option value="member">Member</option>
              <option value="group_leader">Group Leader</option>
              <option value="finance_officer">Finance Officer</option>
              <option value="chief_finance_officer">Chief Finance Officer</option>
              <option value="elder_admin">Elder Admin</option>
              <option value="system_admin">System Admin</option>
            </select>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" form="createUserForm" class="btn btn-primary">Create User</button>
      </div>
    </div>
  </div>
</div>

<!-- Create Group Modal -->
<div class="modal fade" id="createGroupModal" tabindex="-1" aria-labelledby="createGroupModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="createGroupModalLabel">Create New Group</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="createGroupForm">
          <input type="hidden" name="action" value="create_group">
          <div class="mb-3">
            <label for="group_name" class="form-label">Group Name</label>
            <input type="text" class="form-control" id="group_name" name="group_name" required>
          </div>
          <div class="mb-3">
            <label for="leader_name" class="form-label">Leader Name</label>
            <input type="text" class="form-control" id="leader_name" name="leader_name">
          </div>
          <div class="mb-3">
            <label for="description" class="form-label">Description</label>
            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" form="createGroupForm" class="btn btn-primary">Create Group</button>
      </div>
    </div>
  </div>
</div>

<!-- Update System Settings Modal -->
<div class="modal fade" id="updateSystemSettingsModal" tabindex="-1" aria-labelledby="updateSystemSettingsModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="updateSystemSettingsModalLabel">Update System Settings</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <form id="updateSystemSettingsForm">
          <input type="hidden" name="action" value="update_system_settings">
          <div class="mb-3">
            <label for="system_name" class="form-label">System Name</label>
            <input type="text" class="form-control" id="system_name" name="system_name">
          </div>
          <div class="mb-3 form-check">
            <input type="checkbox" class="form-check-input" id="maintenance_mode" name="maintenance_mode" value="1">
            <label class="form-check-label" for="maintenance_mode">Maintenance Mode</label>
          </div>
          <div class="mb-3">
            <label for="backup_frequency" class="form-label">Backup Frequency</label>
            <input type="text" class="form-control" id="backup_frequency" name="backup_frequency">
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        <button type="submit" form="updateSystemSettingsForm" class="btn btn-primary">Update Settings</button>
      </div>
    </div>
  </div>
</div>
