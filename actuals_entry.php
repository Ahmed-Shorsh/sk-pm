<?php


require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/utils.php';
require_once __DIR__ . '/backend/user_controller.php';
require_once __DIR__ . '/backend/department_controller.php';
require_once __DIR__ . '/backend/settings_controller.php';

$settingsRepo = new Backend\SettingsRepository($pdo);
$globalDays = (int)($settingsRepo->getSetting('evaluation_deadline_days') ?? 2);


secureSessionStart();
checkLogin();

/* ----- role constants -------------------------------------------------- */
const ROLE_ADMIN   = 1;
const ROLE_MANAGER = 2;

/* ----- ACL ------------------------------------------------------------- */
if (!in_array($_SESSION['role_id'] ?? 0, [ROLE_ADMIN, ROLE_MANAGER], true)) {
    header('HTTP/1.1 403 Forbidden');
    echo '<h1>Access Denied</h1>';
    exit;
}


function isWindowOpen(array $user): bool {
  global $globalDays;
  $days = $user['rating_window_days'] ?? $globalDays;
  if ($days === 0) return true;
  $today      = new DateTime('today');
  $endOfMonth = (clone $today)->modify('last day of this month');
  $diff       = (int)$today->diff($endOfMonth)->format('%a');
  return $diff < $days;
}


/* ----- context --------------------------------------------------------- */
$user       = getUser($_SESSION['user_id']);
$roleId     = (int)$user['role_id'];
$deptId     = (int)$user['dept_id'];
$monthKey   = $_GET['month'] ?? date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-01$/', $monthKey)) {
    $monthKey = date('Y-m-01');
}

/* ----- repository ------------------------------------------------------ */
use Backend\DepartmentRepository;
$deptRepo = $deptRepo ?? new DepartmentRepository($pdo);

/* -----------------------------------------------------------------------
 * POST: save actual values
 * --------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if ($roleId === ROLE_MANAGER && !isWindowOpen($user)) {
        flashMessage('Actuals window is closed for you.', 'danger');
        redirect("actuals_entry.php?month=$monthKey");
    }

    $actuals = $_POST['actual_value'] ?? [];
    $notes   = $_POST['notes']        ?? [];

    try {
        $deptRepo->submitActuals($deptId, $monthKey, $actuals, $notes);
        flashMessage('Actuals saved.', 'success');
    } catch (Exception $e) {
        flashMessage('Error: '.$e->getMessage(), 'danger');
    }
    redirect("actuals_entry.php?month=$monthKey");
}

/* ----- fetch KPI snapshots -------------------------------------------- */
$snapshots = $deptRepo->fetchDepartmentSnapshots($deptId, $monthKey);

/* ----- UI helpers ------------------------------------------------------ */
function monthLink(string $base, int $offset): string
{
    $dt = new DateTime($base);
    $dt->modify(($offset >= 0 ? '+' : '').$offset.' month');
    return $dt->format('Y-m-01');
}

/* ----- window flag & button state ------------------------------------- */
$windowOpen = $roleId === ROLE_ADMIN ? true : isWindowOpen($user);

/* ----- HTML ------------------------------------------------------------ */
include __DIR__ . '/partials/navbar.php';
include __DIR__ . '/partials/intro_modal.php';  
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Department Actuals – SK-PM</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" href="./assets/logo/sk-n.ico">
  <link href="https://fonts.googleapis.com/css2?family=Merriweather&family=Playfair+Display&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link rel="stylesheet" href="./assets/css/style.css">
</head>
<body class="bg-light font-serif">

<div class="container py-4">

  <?= $GLOBALS['message_html'] ?? '' ?>

  <h1 class="mb-4">Department Actuals Entry</h1>

  <!-- month navigator -->
  <form class="d-flex align-items-center mb-4" method="get">
    <label class="me-2">Month</label>
    <input type="month" name="month" class="form-control w-auto"
           value="<?= date('Y-m',strtotime($monthKey)) ?>" onchange="this.form.submit()">
    <a class="btn btn-outline-secondary ms-3"
       href="actuals_entry.php?month=<?= monthLink($monthKey,-1) ?>">&laquo; Prev</a>
    <a class="btn btn-outline-secondary ms-2"
       href="actuals_entry.php?month=<?= monthLink($monthKey,+1) ?>">Next &raquo;</a>
  </form>

  <?php if (empty($snapshots)): ?>
    <div class="alert alert-warning">
      No KPI snapshot found for <?= date('F Y',strtotime($monthKey)) ?>.
      <a href="department_plan.php?month=<?= $monthKey ?>">Create your plan first.</a>
    </div>
  <?php else: ?>
    <?php if (!$windowOpen): ?>
  <div class="alert alert-info">
    Actuals entry will open during the last 
    <?= ($user['rating_window_days'] ?? $globalDays) ?> 
    day<?= (($user['rating_window_days'] ?? $globalDays) > 1 ? 's' : '') ?> of the month.
  </div>
<?php endif; ?>


    <form method="post">
      <input type="hidden" name="action" value="save">

      <div class="table-responsive mb-4">
        <table class="table table-bordered align-middle">
          <thead class="table-dark">
            <tr>
              <th>KPI</th>
              <th class="text-end">Target</th>
              <th class="text-end">Weight</th>
              <th class="text-end">Actual</th>
              <th>Notes</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($snapshots as $row): ?>
            <tr>
              <td>
                <?= htmlspecialchars($row['indicator_name'] ?: $row['custom_name']) ?>
                <?php if ($row['is_custom']): ?>
                  <span class="badge bg-info">Custom</span>
                <?php endif; ?>
                <?php if (!empty($row['indicator_description'])): ?>
                  <div class="small text-muted"><?= htmlspecialchars($row['indicator_description']) ?></div>
                <?php endif; ?>
              </td>
              <td class="text-end">
                <?= $row['target_value'] ?>&nbsp;<?= htmlspecialchars($row['unit'] ?? '') ?>
              </td>
              <td class="text-end"><?= $row['weight'] ?>%</td>
              <td class="text-end" style="width:11rem">
                <input type="number" step="0.01" min="0"
                       name="actual_value[<?= $row['snapshot_id'] ?>]"
                       value="<?= htmlspecialchars($row['actual_value'] ?? '') ?>"
                       class="form-control text-end"
                       <?= $windowOpen? '' : 'disabled' ?>>
              </td>
              <td style="width:16rem">
                <textarea class="form-control"
                          rows="2"
                          name="notes[<?= $row['snapshot_id'] ?>]"
                          placeholder="Optional notes"
                          <?= $windowOpen? '' : 'disabled' ?>><?= htmlspecialchars($row['notes'] ?? '') ?></textarea>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="text-center">
        <button class="btn btn-dark btn-lg"
                <?= $windowOpen? '' : 'disabled' ?>>Save Actuals</button>
      </div>
    </form>
  <?php endif; ?>

</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
