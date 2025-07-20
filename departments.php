<?php
// File: public/departments.php  (Department management with shared folder paths)

require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/utils.php';
require_once __DIR__ . '/backend/department_controller.php';

secureSessionStart();
checkLogin();
if (($_SESSION['role_id'] ?? 0) !== 1) {
    header('HTTP/1.1 403 Forbidden');
    exit('<h1>Access Denied</h1>');
}

// Initialize repository
$deptRepo = new Backend\DepartmentRepository($pdo);

// Handle create/update/delete actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    switch ($action) {
        case 'create':
            $newName   = sanitize($_POST['dept_name']);
            $newMgr    = $_POST['manager_id'] !== '' ? (int)$_POST['manager_id'] : null;
            $newPath   = trim($_POST['share_path'] ?? '');
            $deptId = $deptRepo->createDepartment($newName, $newMgr, $newPath);
            flashMessage(
                $deptId > 0 ? 'Department added.' : 'Error adding department.',
                $deptId > 0 ? 'success' : 'danger'
            );
            break;

        case 'update':
            $id        = (int)$_POST['dept_id'];
            $updName   = sanitize($_POST['dept_name']);
            $updMgr    = $_POST['manager_id'] !== '' ? (int)$_POST['manager_id'] : null;
            $updPath   = trim($_POST['share_path'] ?? '');
            $ok = $deptRepo->updateDepartment($id, $updName, $updMgr, $updPath);
            flashMessage(
                $ok ? 'Department updated.' : 'Error updating department.',
                $ok ? 'success' : 'danger'
            );
            break;

        case 'delete':
            $delId = (int)$_POST['dept_id'];
            $ok = $deptRepo->deleteDepartment($delId);
            flashMessage(
                $ok ? 'Department deleted.' : 'Error deleting department.',
                $ok ? 'success' : 'danger'
            );
            break;
    }
    // Redirect back to avoid resubmission
    redirect('departments.php');
}

// Fetch data for display
$departments = $deptRepo->fetchAllDepartments();
$managers    = $deptRepo->fetchAllManagers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Departments – SK‑PM</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Merriweather&family=Playfair+Display&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link rel="stylesheet" href="./assets/css/style.css">
  <link rel="icon" href="./assets/logo/sk-n.ico" type="image/x-icon">
</head>
<body class="font-serif bg-light text-dark">

<?php include __DIR__ . '/partials/navbar.php'; ?>

<div class="container py-4">
  <h1 class="mb-3 text-center">Department Management</h1>
  <p class="text-center text-muted mb-4">Add, edit or remove departments, assign managers, and set Plan folder paths.</p>

  <?= $GLOBALS['message_html'] ?? '' ?>

  <div class="text-end mb-3">
    <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#createModal">+ Add Department</button>
  </div>

  <div class="table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead class="table-dark">
        <tr>
          <th>Name</th>
          <th>Manager</th>
          <th>Plan Folder Path</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($departments as $d): ?>
          <tr>
            <td><?= htmlspecialchars($d['dept_name']) ?></td>
            <td><?= htmlspecialchars($d['manager_name'] ?? '-') ?></td>
            <td>
              <?= htmlspecialchars($d['share_path'] ?? '-') ?>
            </td>
            <td class="text-center">
              <button class="btn btn-sm btn-outline-secondary me-1" 
                      data-bs-toggle="modal" data-bs-target="#editModal<?= $d['dept_id'] ?>">
                Edit
              </button>
              <form method="POST" class="d-inline" onsubmit="return confirm('Delete this department?');">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="dept_id"  value="<?= $d['dept_id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Create Department Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
  <div class="modal-dialog">
    <form method="POST" class="modal-content">
      <input type="hidden" name="action" value="create">
      <div class="modal-header">
        <h5 class="modal-title">Add Department</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <label class="form-label">Department Name</label>
          <input type="text" name="dept_name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Assign Manager</label>
          <select name="manager_id" class="form-select">
            <option value="">None</option>
            <?php foreach ($managers as $m): ?>
              <option value="<?= $m['user_id'] ?>">
                <?= htmlspecialchars($m['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Plan Folder Path (Remark)</label>
          <input type="text" name="share_path" class="form-control" placeholder="\\192.168.10.252\\Plan\\Department">
          <div class="form-text text-muted">
            UNC path to the department’s Plan folder (e.g. \\192.168.10.252\\Plan\\HR Plans).
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-dark">Create</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Department Modals -->
<?php foreach ($departments as $d): ?>
  <div class="modal fade" id="editModal<?= $d['dept_id'] ?>" tabindex="-1">
    <div class="modal-dialog">
      <form method="POST" class="modal-content">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="dept_id"   value="<?= $d['dept_id'] ?>">
        <div class="modal-header">
          <h5 class="modal-title">Edit <?= htmlspecialchars($d['dept_name']) ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Department Name</label>
            <input type="text" name="dept_name" class="form-control"
                   value="<?= htmlspecialchars($d['dept_name']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Assign Manager</label>
            <select name="manager_id" class="form-select">
              <option value="">None</option>
              <?php foreach ($managers as $m): ?>
                <option value="<?= $m['user_id'] ?>" <?= $m['user_id'] === $d['manager_id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($m['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mb-3">
            <label class="form-label">Plan Folder Path (Remark)</label>
            <input type="text" name="share_path" class="form-control"
                   value="<?= htmlspecialchars($d['share_path'] ?? '') ?>">
            <div class="form-text text-muted">
              UNC path to the department’s Plan folder.
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-dark">Save</button>
        </div>
      </form>
    </div>
  </div>
<?php endforeach; ?>

<footer class="text-center py-3">
  <small>&copy; <?= date('Y') ?> SK‑PM Performance Management</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
