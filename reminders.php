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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send') {
    $uid = (int)($_POST['user_id'] ?? 0);
    $msg = trim($_POST['message'] ?? '');

    try {
        // message null → buildDefaultMessage() will be used
        sendTelegramReminder($pdo, $uid, $msg !== '' ? $msg : null);
        $GLOBALS['message_html'] = '<div class="alert alert-success">Telegram message sent successfully.</div>';
    } catch (Throwable $e) {
        $GLOBALS['message_html'] = '<div class="alert alert-danger">' .
            htmlspecialchars($e->getMessage(), ENT_QUOTES) .
            '</div>';
    }
      header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}


$settingsRepo = new Backend\SettingsRepository($pdo);
$globalDays   = (int)($settingsRepo->getSetting('evaluation_deadline_days') ?? 2);
$globalActualsDays = (int)($settingsRepo->getSetting('actuals_entry_deadline_days') ?? $globalDays);

$deptRepo = new DepartmentRepository($pdo);

// Parse filter inputs for department, year, and month
$deptFilter  = $_GET['dept_id'] ?? 'all';
$yearFilter  = (int)($_GET['year'] ?? date('Y'));
$monthFilter = $_GET['month'] ?? date('m');
$monthFilter = str_pad((string)(int)$monthFilter, 2, '0', STR_PAD_LEFT);
$periodKey   = "{$yearFilter}-{$monthFilter}-01";

// Fetch all users and filter by department if needed
$departments = $deptRepo->fetchAllDepartments();
$allUsers    = fetchAllUsers();
$users       = ($deptFilter === 'all')
             ? $allUsers
             : array_filter($allUsers, fn($u) => $u['dept_id'] == $deptFilter);

// Pre-compute lateness data for the selected period
$today     = new DateTime('today');
$lastDay   = (new DateTime($periodKey))->modify('last day of this month');
$postPeriod = $today > $lastDay;
$daysLate  = $postPeriod ? (int)$lastDay->diff($today)->format('%a') : 0;

// Fetch evaluation completion counts for the period (distinct evaluatees per evaluator)
$stmtEval = $pdo->prepare(
    "SELECT evaluator_id, COUNT(DISTINCT evaluatee_id) AS cnt
     FROM individual_evaluations
     WHERE month = :m
     GROUP BY evaluator_id"
);
$stmtEval->execute(['m' => $periodKey]);
$evalCounts = $stmtEval->fetchAll(PDO::FETCH_KEY_PAIR);

// Fetch department actuals completion stats for managers
$stmtAct = $pdo->prepare(
    "SELECT dept_id,
            SUM(CASE WHEN actual_value IS NOT NULL THEN 1 ELSE 0 END) AS filled,
            COUNT(*) AS total
     FROM department_indicator_monthly
     WHERE month = :m
     GROUP BY dept_id"
);
$stmtAct->execute(['m' => $periodKey]);
$deptStats = [];
foreach ($stmtAct->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $deptStats[(int)$row['dept_id']] = [
        'filled' => (int)$row['filled'],
        'total'  => (int)$row['total']
    ];
}

// Compute department member counts (total active users and employees) for expected evaluations
$deptMemberCount   = [];
$deptEmployeeCount = [];
foreach ($allUsers as $usr) {
    $dId = (int)$usr['dept_id'];
    if (!isset($deptMemberCount[$dId])) {
        $deptMemberCount[$dId] = 0;
        $deptEmployeeCount[$dId] = 0;
    }
    if ((int)$usr['role_id'] !== 1) {
        // count all non-admin members in dept
        $deptMemberCount[$dId]++;
    }
    if ((int)$usr['role_id'] === 3) {
        $deptEmployeeCount[$dId]++;  // count employees in dept
    }
}
// Map of department managers for adding boss in expected targets
$deptManagerMap = [];
foreach ($departments as $dep) {
    $deptManagerMap[(int)$dep['dept_id']] = (int)($dep['manager_id'] ?? 0);
}

// window
function isWindowOpen(array $user): bool {
  global $globalDays;

  $d = $user['rating_window_days'] ?? $globalDays;
  if ($d === 0) return true;

  $today    = new DateTime('today');
  $lastDay  = new DateTime('last day of this month');
  $windowStart = (clone $lastDay)->modify("-{$d} days");

  return $today >= $windowStart && $today <= $lastDay;
}


function defaultMsg(array $u): string {
  global $globalDays;
  $days = $u['rating_window_days'] ?? $globalDays;

  if ((int)$u['role_id'] === 3) {
      return "Reminder from SK-PM:\nYou have exactly the last {$days} day" . ($days > 1 ? 's' : '') . " of the month to submit your individual ratings.";
  }

  // For managers/admins
  return "Reminder from SK-PM:\n• Month-start — enter KPI plan.\n• Month-end — submit actuals and rate your team (window: {$days} day" . ($days > 1 ? 's' : '') . ").";
}

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
    .btn-primary, .btn-secondary { border: 1px solid #000 !important; }
  </style>


</head>
<body class="bg-light font-serif">
  <div class="container py-4">
    <h1 class="mb-4">Telegram Reminders</h1>

    <form class="row g-3 mb-4" method="get">
      <div class="col-md-4">
        <label class="form-label">Department</label>
        <select class="form-select" name="dept_id">
          <option value="all" <?= $deptFilter === 'all' ? 'selected' : '' ?>>All</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= $d['dept_id'] ?>" <?= $d['dept_id'] == $deptFilter ? 'selected' : '' ?>>
              <?= htmlspecialchars($d['dept_name'], ENT_QUOTES) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Year</label>
        <select class="form-select" name="year">
          <?php $currentYear = (int)date('Y'); for ($y = $currentYear; $y >= $currentYear-1; $y--): ?>
            <option value="<?= $y ?>" <?= $y === $yearFilter ? 'selected' : '' ?>><?= $y ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Month</label>
        <select class="form-select" name="month">
          <?php for ($m = 1; $m <= 12; $m++):
            $mVal = str_pad((string)$m, 2, '0', STR_PAD_LEFT);
            $mName = date('F', mktime(0,0,0,$m,1));
          ?>
            <option value="<?= $mVal ?>" <?= $mVal === $monthFilter ? 'selected' : '' ?>><?= $mName ?></option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-auto align-self-end">
        <button class="btn btn-dark">Filter</button>
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
            <th>Lateness</th>
            <th class="text-center">Send</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): 
            // Determine lateness text for this user
            $lateText = '';
            if ((int)$u['role_id'] === 1) {
                $lateText = 'N/A';
            } else {
                $userId = (int)$u['user_id'];
                $roleId = (int)$u['role_id'];
                $deptId = (int)$u['dept_id'];
                // Individual evaluation completion status
                $completedTargets = isset($evalCounts[$userId]) ? (int)$evalCounts[$userId] : 0;
                $expectedTargets = 0;
                $indivDone = true;
                if ($roleId === 2) {
                    // Manager: expected targets = number of employees in dept
                    $expectedTargets = $deptEmployeeCount[$deptId] ?? 0;
                    $indivDone = ($expectedTargets === 0) ? true : ($completedTargets >= $expectedTargets);
                } elseif ($roleId === 3) {
                    // Employee: expected = (dept employees - 1 self) + 1 manager
                    $expectedTargets = (($deptEmployeeCount[$deptId] ?? 0) > 0 ? ($deptEmployeeCount[$deptId] - 1) : 0);
                    $expectedTargets += (!empty($deptManagerMap[$deptId]) ? 1 : 0);
                    $indivDone = ($expectedTargets === 0) ? true : ($completedTargets >= $expectedTargets);
                }
                // Individual lateness text
                if (!$postPeriod) {
                    $indivText = 'on time';
                } else {
                    $indivText = $indivDone ? '0 days behind' : ($daysLate . ' days behind');
                }
                if ($roleId === 2) {
                    // Department lateness for manager
                    $deptDone = true;
                    if (isset($deptStats[$deptId])) {
                        $filled = $deptStats[$deptId]['filled'];
                        $total  = $deptStats[$deptId]['total'];
                        if ($total > 0 && $filled < $total) {
                            $deptDone = false;
                        }
                    }
                    $deptText = !$postPeriod ? 'on time'
                              : ($deptDone ? '0 days behind' : ($daysLate . ' days behind'));
                    $lateText = $indivText . ' for individual / ' . $deptText . ' for department';
                } else {
                    $lateText = $indivText;
                }
            }
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
              <td><?= htmlspecialchars($lateText, ENT_QUOTES) ?></td>
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

  <?php if (!empty($GLOBALS['message_html'])):
    $msgHtml    = $GLOBALS['message_html'];
    $isSuccess  = strpos($msgHtml, 'alert-success') !== false;
    $modalTitle = $isSuccess ? 'Success' : 'Error';
    $plainText  = strip_tags($msgHtml);
  ?>
  <div class="modal fade" id="alertModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><?= $modalTitle ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p><?= htmlspecialchars($plainText, ENT_QUOTES) ?></p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-primary" data-bs-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      var alertM = new bootstrap.Modal(document.getElementById('alertModal'));
      alertM.show();
    });
  </script>
  <?php endif; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
