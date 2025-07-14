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
        flashMessage($ok ? 'User created.' : 'Error creating user.', $ok ? 'success' : 'danger');

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

        flashMessage(($ok1 && $ok2) ? 'User updated.' : 'Error updating user.',
                     ($ok1 && $ok2) ? 'success' : 'danger');

    /* ---- TOGGLE ACTIVE ---- */
    } elseif ($action === 'toggle_active') {
        $ok = deleteUser((int)$_POST['user_id']); // soft delete
        flashMessage($ok ? 'User status toggled.' : 'Error toggling user.', $ok ? 'info' : 'danger');
    }

    redirect('users.php');
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

<footer class="footer py-3 text-center">
  <small>&copy; <?= date('Y') ?> SK-PM Performance Management</small>
</footer>

</body>
</html>