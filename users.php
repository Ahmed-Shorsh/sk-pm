<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/utils.php';
require_once __DIR__ . '/backend/user_controller.php';     
require_once __DIR__ . '/backend/department_controller.php';

secureSessionStart();
checkLogin();

if (($_SESSION['role_id'] ?? 0) !== 1) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied.');
}

global $pdo;

/* ---------- repositories --------------------------------------------- */
use Backend\DepartmentRepository;
$deptRepo = $deptRepo ?? new DepartmentRepository($pdo);

/* -----------------------------------------------------------------------
 * POST HANDLER
 * --------------------------------------------------------------------- */
/* -----------------------------------------------------------------------
 * POST HANDLER
 * --------------------------------------------------------------------- */
$showSuccessModal = false;
$showErrorModal = false;
$modalMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ---- CREATE ---- */
    if ($action === 'create') {
        $ok = createUser(
            sanitize($_POST['name']),
            sanitize($_POST['email']),
            $_POST['password'],
            (int)$_POST['role_id'],
            $_POST['dept_id'] !== '' ? (int)$_POST['dept_id'] : null,
            sanitize($_POST['phone']),
            sanitize($_POST['position']),
            $_POST['birth_date'],
            $_POST['hire_date']
        );
        $uid = $pdo->lastInsertId();

        if ($ok && $_POST['rating_window_days'] !== '') {
            $pdo->prepare('UPDATE users SET rating_window_days = ? WHERE user_id = ?')
                ->execute([(int)$_POST['rating_window_days'], $uid]);
        }
        
        if ($ok) {
            $showSuccessModal = true;
            $modalMessage = 'User created successfully!';
        } else {
            $showErrorModal = true;
            $modalMessage = 'Error creating user.';
        }

    /* ---- UPDATE ---- */
    } elseif ($action === 'update') {
        $uid = (int)$_POST['user_id'];
        $ok1 = updateUser(
            $uid,
            sanitize($_POST['name']),
            sanitize($_POST['email']),
            (int)$_POST['role_id'],
            $_POST['dept_id'] !== '' ? (int)$_POST['dept_id'] : null,
            isset($_POST['active']) ? 1 : 0,
            sanitize($_POST['phone']),
            sanitize($_POST['position']),
            $_POST['birth_date'],
            $_POST['hire_date']
        );
        $ok2 = true;
        if (!empty($_POST['password'])) {
            $ok2 = changeUserPassword($uid, $_POST['password']);
        }
        $pdo->prepare('UPDATE users SET rating_window_days = ? WHERE user_id = ?')
            ->execute([$_POST['rating_window_days'] !== '' ? (int)$_POST['rating_window_days'] : null, $uid]);

        if ($ok1 && $ok2) {
            $showSuccessModal = true;
            $modalMessage = 'User updated successfully!';
        } else {
            $showErrorModal = true;
            $modalMessage = 'Error updating user.';
        }

    /* ---- TOGGLE ACTIVE ---- */
    } elseif ($action === 'toggle_active') {
        $ok = deleteUser((int)$_POST['user_id']); // soft delete
        if ($ok) {
            $showSuccessModal = true;
            $modalMessage = 'User status toggled successfully!';
        } else {
            $showErrorModal = true;
            $modalMessage = 'Error toggling user status.';
        }
    }

    // Remove the redirect line - we want to stay on the same page
}

/* -----------------------------------------------------------------------
 * FETCH DATA FOR VIEW
 * --------------------------------------------------------------------- */
$users       = fetchAllUsers();
$roles       = fetchAllRoles();
$departments = $deptRepo->fetchAllDepartments();
include __DIR__ . '/partials/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>User Management – SK-PM</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="./assets/logo/sk-n.ico">
  <link href="https://fonts.googleapis.com/css2?family=Merriweather&family=Playfair+Display&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link rel="stylesheet" href="./assets/css/style.css">
</head>
<body class="bg-light font-serif">

<div class="container py-4">

  <?= $GLOBALS['message_html'] ?? '' ?>

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h1>User Management</h1>
    <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#createModal">+ Add User</button>
  </div>

  <!-- USERS TABLE -->
  <div class="table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead class="table-dark">
        <tr>
          <th>Name</th>
          <th>Email</th>
          <th>Role</th>
          <th>Dept</th>
          <th>Window&nbsp;(days)</th>
          <th>Active</th>
          <th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($users as $user): ?>
        <tr <?= $user['active'] ? '' : 'class="table-danger"' ?>>
          <td><?= htmlspecialchars($user['name']) ?></td>
          <td><?= htmlspecialchars($user['email']) ?></td>
          <td><?= htmlspecialchars($user['role_name']) ?></td>
          <td><?= htmlspecialchars($user['dept_name'] ?? '-') ?></td>
          <td><?= $user['rating_window_days'] === null ? 'Default' : $user['rating_window_days'] ?></td>
          <td><?= $user['active'] ? 'Yes' : 'No' ?></td>
          <td class="text-center">
            <button class="btn btn-sm btn-outline-secondary me-1"
                    data-bs-toggle="modal"
                    data-bs-target="#editModal<?= $user['user_id'] ?>">Edit</button>
            <form class="d-inline" method="post">
              <input type="hidden" name="action" value="toggle_active">
              <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
              <button class="btn btn-sm btn-outline-<?= $user['active'] ? 'danger' : 'success' ?>">
                <?= $user['active'] ? 'Deactivate' : 'Activate' ?>
              </button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- CREATE USER MODAL -->
<div class="modal fade" id="createModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable">
    <form class="modal-content" method="post">
      <input type="hidden" name="action" value="create">

      <div class="modal-header">
        <h5 class="modal-title">Add New User</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <?php 
        $formUser = [
          'name' => '',
          'email' => '',
          'phone' => '',
          'position' => '',
          'birth_date' => '',
          'hire_date' => '',
          'rating_window_days' => '',
          'role_id' => '',
          'dept_id' => '',
          'active' => 1,
        ];
        $isCreating = true;
        include __DIR__ . '/partials/user_form_fields.php'; 
        ?>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-dark">Create</button>
      </div>
    </form>
  </div>
</div>

<!-- EDIT USER MODALS -->
<?php foreach ($users as $user): ?>
<div class="modal fade" id="editModal<?= $user['user_id'] ?>" tabindex="-1">
  <div class="modal-dialog modal-dialog-scrollable">
    <form class="modal-content" method="post">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">

      <div class="modal-header">
        <h5 class="modal-title">Edit: <?= htmlspecialchars($user['name']) ?></h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <?php 
        $formUser = $user;
        $isCreating = false;
        include __DIR__ . '/partials/user_form_fields.php'; 
        ?>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="submit" class="btn btn-dark">Save</button>
      </div>
    </form>
  </div>
</div>
<?php endforeach; ?>

<!-- Success Modal -->
<div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 0; border: 1px solid #e0e0e0; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
      <div class="modal-header" style="background-color: #ffffff; color: #333; border-bottom: 1px solid #e0e0e0; border-radius: 0; padding: 20px;">
        <h5 class="modal-title mb-0 d-flex align-items-center" style="font-weight: 600;">
          <span class="me-2" style="width: 8px; height: 8px; background-color: #28a745; border-radius: 50%; display: inline-block;"></span>
          Success
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="padding: 30px; background-color: white; color: #333;">
        <p class="mb-0" style="font-size: 16px; line-height: 1.5;"><?= htmlspecialchars($modalMessage) ?></p>
      </div>
      <div class="modal-footer" style="background-color: #f8f9fa; border-top: 1px solid #e0e0e0; border-radius: 0; padding: 15px 30px;">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius: 0; padding: 8px 24px; font-weight: 500;">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Error Modal -->
<div class="modal fade" id="errorModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius: 0; border: 1px solid #e0e0e0; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
      <div class="modal-header" style="background-color: #ffffff; color: #333; border-bottom: 1px solid #e0e0e0; border-radius: 0; padding: 20px;">
        <h5 class="modal-title mb-0 d-flex align-items-center" style="font-weight: 600;">
          <span class="me-2" style="width: 8px; height: 8px; background-color: #dc3545; border-radius: 50%; display: inline-block;"></span>
          Error
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" style="padding: 30px; background-color: white; color: #333;">
        <p class="mb-0" style="font-size: 16px; line-height: 1.5;"><?= htmlspecialchars($modalMessage) ?></p>
      </div>
      <div class="modal-footer" style="background-color: #f8f9fa; border-top: 1px solid #e0e0e0; border-radius: 0; padding: 15px 30px;">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal" style="border-radius: 0; padding: 8px 24px; font-weight: 500;">Close</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show modals based on PHP conditions
    <?php if ($showSuccessModal): ?>
    const successModal = new bootstrap.Modal(document.getElementById('successModal'));
    successModal.show();
    <?php endif; ?>

    <?php if ($showErrorModal): ?>
    const errorModal = new bootstrap.Modal(document.getElementById('errorModal'));
    errorModal.show();
    <?php endif; ?>
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

<footer class="footer py-3 text-center">
  <small>&copy; <?= date('Y') ?> SK-PM Performance Management</small>
</footer>

</body>
</html>