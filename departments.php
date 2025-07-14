<?php
// File: public/departments.php

require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/utils.php';
require_once __DIR__ . '/backend/department_controller.php';

secureSessionStart();
checkLogin();
if ($_SESSION['role_id'] !== 1) {
    header('HTTP/1.1 403 Forbidden');
    echo '<h1>Access Denied</h1>';
    exit();
}

// Initialize the repository
$deptRepo = new Backend\DepartmentRepository($pdo);

// Handle create/update/delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $deptId = $deptRepo->createDepartment(
            sanitize($_POST['dept_name']),
            ($_POST['manager_id'] !== '' ? (int)$_POST['manager_id'] : null)
        );
        flashMessage($deptId > 0 ? 'Dept added.' : 'Error adding dept.', $deptId > 0 ? 'success' : 'danger');
    }
    elseif ($action === 'update') {
        $ok = $deptRepo->updateDepartment(
            (int)$_POST['dept_id'],
            sanitize($_POST['dept_name']),
            ($_POST['manager_id'] !== '' ? (int)$_POST['manager_id'] : null)
        );
        flashMessage($ok ? 'Dept updated.' : 'Error updating dept.', $ok ? 'success' : 'danger');
    }
    elseif ($action === 'delete') {
        $ok = $deptRepo->deleteDepartment((int)$_POST['dept_id']);
        flashMessage($ok ? 'Dept deleted.' : 'Error deleting dept.', $ok ? 'success' : 'danger');
    }
}

// Fetch for display
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

<header class="data-container text-center py-4">
  <h1>Department Management</h1>
  <p class="text-muted">Add, edit or remove departments, and assign a manager.</p>
</header>

<main class="data-container mb-5">
  <?php if (!empty($GLOBALS['message_html'])): ?>
    <div class="mb-4"><?= $GLOBALS['message_html'] ?></div>
  <?php endif; ?>

  <div class="text-end mb-3">
    <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#createModal">+ Add Dept</button>
  </div>

  <div class="table-responsive">
    <table class="table table-striped table-bordered">
      <thead class="table-dark">
        <tr><th>Name</th><th>Manager</th><th class="text-center">Actions</th></tr>
      </thead>
      <tbody>
      <?php foreach ($departments as $d): ?>
        <tr>
          <td><?= htmlspecialchars($d['dept_name']) ?></td>
          <td><?= htmlspecialchars($d['manager_name'] ?? '-') ?></td>
          <td class="text-center">
            <button class="btn btn-sm btn-outline-secondary me-1"
                    data-bs-toggle="modal"
                    data-bs-target="#editModal<?= $d['dept_id'] ?>">
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
</main>

<!-- Create Modal -->
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
                <?php if (!empty($m['dept_name'])): ?>
                  (Currently in: <?= htmlspecialchars($m['dept_name']) ?>)
                <?php endif; ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-dark">Create</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit Modals -->
<?php foreach ($departments as $d): ?>
  <div class="modal fade" id="editModal<?= $d['dept_id'] ?>" tabindex="-1">
    <div class="modal-dialog">
      <form method="POST" class="modal-content">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="dept_id" value="<?= $d['dept_id'] ?>">
        <div class="modal-header">
          <h5 class="modal-title">Edit <?= htmlspecialchars($d['dept_name']) ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label">Department Name</label>
            <input
              type="text"
              name="dept_name"
              class="form-control"
              value="<?= htmlspecialchars($d['dept_name']) ?>"
              required
            >
          </div>
          <div class="mb-3">
            <label class="form-label">Assign Manager</label>
            <select name="manager_id" class="form-select">
              <option value="">None</option>
              <?php foreach ($managers as $m): ?>
                <option
                  value="<?= $m['user_id'] ?>"
                  <?= $m['user_id'] === $d['manager_id'] ? 'selected' : '' ?>
                >
                  <?= htmlspecialchars($m['name']) ?>
                  <?php if (!empty($m['dept_name'])): ?>
                    (Currently in: <?= htmlspecialchars($m['dept_name']) ?>)
                  <?php endif; ?>
                </option>
              <?php endforeach; ?>
            </select>
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

<footer class="footer py-3 text-center">
    <small>&copy; <?= date('Y') ?> SK‑PM Performance Management</small>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>