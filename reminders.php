<?php
declare(strict_types=1);

require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/utils.php';
require_once __DIR__ . '/backend/user_controller.php';
require_once __DIR__ . '/backend/department_controller.php';
require_once __DIR__ . '/scripts/telegram_reminder.php';
require_once __DIR__ . '/backend/settings_controller.php';
require_once __DIR__ . '/vendor/autoload.php';

use Backend\DepartmentRepository;

secureSessionStart();
checkLogin();
if (($_SESSION['role_id'] ?? 0) !== 1) {
    header('HTTP/1.1 403 Forbidden');
    exit;
}

$settingsRepo = new Backend\SettingsRepository($pdo);
$globalDays   = (int)($settingsRepo->getSetting('evaluation_deadline_days') ?? 2);

$deptRepo = new DepartmentRepository($pdo);

function isWindowOpen(array $u): bool {
    global $globalDays;
    $d = $u['rating_window_days'] ?? $globalDays;
    if ($d === 0) {
        return true;
    }
    $diff = (int)(new DateTime('today'))->diff(new DateTime('last day of'))->format('%a');
    return $diff < $d;
}

function defaultMsg(array $u): string {
    $d = $u['rating_window_days'] ?? 2;
    if ($u['role_id'] === 3) {
        return "Reminder from SK-PM:\nYou have exactly the last {$d} day" . ($d > 1 ? 's' : '') . " of the month to submit your individual ratings.";
    }
    return "Reminder from SK-PM:\nAt month-start: enter KPI plan.\nAt month-end: submit actuals and rate your team (window: {$d} day" . ($d > 1 ? 's' : '') . ").";
}

// Handle manual send
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send') {
    $uid = (int)$_POST['user_id'];
    $msg = trim($_POST['message']);
    $u   = getUser($uid);

    if (!$u) {
        flashMessage('User not found.', 'danger');
        redirect('reminders.php');
    }
    if (empty($u['telegram_chat_id'])) {
        flashMessage('Cannot send: user has not linked Telegram.', 'danger');
        redirect('reminders.php');
    }
    if (!isWindowOpen($u)) {
        flashMessage('Window closed for this user.', 'danger');
        redirect('reminders.php');
    }

    try {
        sendTelegramReminder($pdo, $uid, $msg);
        flashMessage('Reminder sent to ' . htmlspecialchars($u['name'], ENT_QUOTES) . '.', 'success');
    } catch (Exception $e) {
        flashMessage('Error: ' . $e->getMessage(), 'danger');
    }
    redirect('reminders.php');
}

// Fetch users
$departments = $deptRepo->fetchAllDepartments();
$deptFilter  = $_GET['dept_id'] ?? 'all';
$allUsers    = fetchAllUsers();  // should now include 'telegram_chat_id'
$users       = $deptFilter === 'all'
               ? $allUsers
               : array_filter($allUsers, fn($u) => $u['dept_id'] == $deptFilter);

include __DIR__ . '/partials/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Send Reminders – SK-PM</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Merriweather&family=Playfair+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./assets/css/style.css">
  <link rel="icon" href="./assets/logo/sk-n.ico">
  <style>
    .btn { opacity: 1 !important }
    .modal-content { background: #fff }
  </style>
</head>
<body class="bg-light font-serif">
  <div class="container py-4">
    <h1 class="mb-4">Telegram Reminders</h1>
    <?= $GLOBALS['message_html'] ?? '' ?>

    <form class="row g-3 mb-4" method="get">
      <div class="col-md-4">
        <label class="form-label">Department</label>
        <select class="form-select" name="dept_id" onchange="this.form.submit()">
          <option value="all" <?= $deptFilter === 'all' ? 'selected' : '' ?>>All</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= $d['dept_id'] ?>" <?= $d['dept_id'] == $deptFilter ? 'selected' : '' ?>>
              <?= htmlspecialchars($d['dept_name'], ENT_QUOTES) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>

    <div class="table-responsive">
      <table class="table table-bordered align-middle">
        <thead class="table-dark">
          <tr>
            <th>Name</th>
            <th>Role</th>
            <th>Window</th>
            <th>Telegram</th>
            <th class="text-center">Send</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): 
            $open      = isWindowOpen($u);
            $linked    = !empty($u['telegram_chat_id']);
            $windowVal = $u['rating_window_days'] === null
                         ? "Default ({$globalDays})"
                         : $u['rating_window_days'];
          ?>
            <tr <?= $open ? '' : 'class="table-warning"' ?>>
              <td><?= htmlspecialchars($u['name'], ENT_QUOTES) ?></td>
              <td><?= htmlspecialchars($u['role_name'], ENT_QUOTES) ?></td>
              <td><?= $windowVal ?></td>
              <td>
                <?= $linked
                   ? '<span class="text-success">Linked</span>'
                   : '<span class="text-danger">Not linked</span>' ?>
              </td>
              <td class="text-center">
                <button
                  class="btn btn-sm btn<?= $open && $linked ? '-primary' : '-secondary' ?>"
                  data-bs-toggle="modal"
                  data-bs-target="#msg<?= $u['user_id'] ?>"
                  <?= $open && $linked ? '' : 'disabled' ?>>
                  Message
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Modals -->
  <?php foreach ($users as $u):
    $open   = isWindowOpen($u);
    $linked = !empty($u['telegram_chat_id']);
    $default = defaultMsg($u);
  ?>
    <div class="modal fade" id="msg<?= $u['user_id'] ?>" tabindex="-1">
      <div class="modal-dialog">
        <form method="post" class="modal-content">
          <input type="hidden" name="action" value="send">
          <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
          <div class="modal-header">
            <h5 class="modal-title">Send Reminder — <?= htmlspecialchars($u['name'], ENT_QUOTES) ?></h5>
            <button class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <label class="form-label">Message</label>
            <textarea name="message" rows="6" class="form-control"><?= htmlspecialchars($default, ENT_QUOTES) ?></textarea>
            <?php if (!$linked): ?>
              <div class="alert alert-warning mt-3">
                Cannot send: user has not linked Telegram.
              </div>
            <?php elseif (!$open): ?>
              <div class="alert alert-warning mt-3">Window closed for this user.</div>
            <?php endif; ?>
          </div>
          <div class="modal-footer">
            <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary" <?= $open && $linked ? '' : 'disabled' ?>>Send</button>
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
