<?php
/* --------------------------------------------------------------------------
 * File: evaluate.php
 * Peer / team evaluation interface
 * Roles:
 *   • Admin  – read-only view of all evaluations (with filters)
 *   • Manager – rate team members
 *   • Employee – rate peers + manager
 * -------------------------------------------------------------------------- */

require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/utils.php';
require_once __DIR__ . '/backend/user_controller.php';
require_once __DIR__ . '/backend/evaluation_controller.php';
require_once __DIR__ . '/backend/indicator_controller.php';
require_once __DIR__ . '/backend/department_controller.php';
require_once __DIR__ . '/backend/settings_controller.php';


$settingsRepo = new Backend\SettingsRepository($pdo);
$globalDays = (int)($settingsRepo->getSetting('evaluation_deadline_days') ?? 2);


secureSessionStart();
checkLogin();

/* -----------------------------------------------------------------------
 * CONSTANTS & HELPERS
 * --------------------------------------------------------------------- */
const ROLE_ADMIN    = 1;
const ROLE_MANAGER  = 2;
const ROLE_EMPLOYEE = 3;

/** is the current date inside the user’s rating window? */
function isWindowOpen(array $user): bool
{
    global $globalDays;

    $days = $user['rating_window_days'] ?? $globalDays;
    if ($days === 0) return true;

    $today      = new DateTimeImmutable('today');
    $endOfMonth = $today->modify('last day of this month');
    $diffDays   = (int)$today->diff($endOfMonth)->format('%a');

    return $diffDays < $days;
}

/* -----------------------------------------------------------------------
 * CONTEXT
 * --------------------------------------------------------------------- */
$user     = getUser($_SESSION['user_id']);
$roleId   = (int)$user['role_id'];
$deptId   = (int)$user['dept_id'];
$monthKey = $_GET['month'] ?? date('Y-m-01');

/* -----------------------------------------------------------------------
 * REPOSITORIES
 * --------------------------------------------------------------------- */
use Backend\IndicatorRepository;
use Backend\EvaluationRepository;
use Backend\DepartmentRepository;

$indRepo  = $indRepo  ?? new IndicatorRepository($pdo);
$evalRepo = $evalRepo ?? new EvaluationRepository($pdo);
$deptRepo = $deptRepo ?? new DepartmentRepository($pdo);

/* -----------------------------------------------------------------------
 * HANDLE SUBMIT (employees & managers only)
 * --------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($roleId,[ROLE_MANAGER,ROLE_EMPLOYEE],true)) {
  if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    in_array($roleId, [ROLE_MANAGER, ROLE_EMPLOYEE], true)
) {
    if (!isWindowOpen($user)) {
        flashMessage('Rating window is closed.', 'danger');
        redirect("evaluate.php?month=$monthKey", true);
    }

    $ratings = $_POST['ratings'] ?? [];
    $notes   = $_POST['notes']   ?? [];

    try {
        $evalRepo->saveEvaluations($user['user_id'], $monthKey, $ratings, $notes);
        flashMessage('Evaluations saved. Thank you!', 'success');
        redirect('dashboard.php', true);   // ← success takes you home
    } catch (Exception $e) {
        flashMessage('Error: ' . $e->getMessage(), 'danger');
        redirect("evaluate.php?month=$monthKey", true);
    }
}
}

/* -----------------------------------------------------------------------
 * DATA FOR VIEW
 * --------------------------------------------------------------------- */
$indShared   = $indRepo->fetchIndividualIndicators(true,'individual');
$indManager  = $indRepo->fetchIndividualIndicators(true,'manager');

if ($roleId === ROLE_ADMIN) {
    $deptFilter    = $_GET['dept'] ?? '';
    $allDepts      = $deptRepo->fetchAllDepartments();
    $allEvals      = $evalRepo->fetchIndividualEvaluations($monthKey, $deptFilter ?: null);
} elseif ($roleId === ROLE_EMPLOYEE) {
    $peers         = $evalRepo->fetchPeers($user['user_id']);
    $managerRow    = $evalRepo->fetchManager($user['user_id']);
} elseif ($roleId === ROLE_MANAGER) {
    $team          = $evalRepo->fetchTeamMembers($user['user_id']);
}

/* -----------------------------------------------------------------------
 * HTML
 * --------------------------------------------------------------------- */
include __DIR__ . '/partials/navbar.php';
include __DIR__ . '/partials/intro_modal.php';   // first-login popup
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Evaluations – SK-PM</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" href="./assets/logo/sk-n.ico">
  <link href="https://fonts.googleapis.com/css2?family=Merriweather&family=Playfair+Display&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link rel="stylesheet" href="./assets/css/style.css">



</head>
<body class="bg-light font-serif">

<div class="container py-4">

  <?= $GLOBALS['message_html'] ?? '' ?>

  <?php /* ================= ADMIN READ-ONLY ================= */ ?>
  <?php if ($roleId === ROLE_ADMIN): ?>
    <h1 class="mb-4">All Individual Evaluations</h1>
    <form class="row g-3 mb-4" method="get">
      <div class="col-md-3">
        <label class="form-label">Month</label>
        <input type="month" name="month" value="<?= date('Y-m',strtotime($monthKey)) ?>" class="form-control">
      </div>
      <div class="col-md-3">
        <label class="form-label">Department</label>
        <select name="dept" class="form-select">
          <option value="">All</option>
          <?php foreach ($allDepts as $d): ?>
            <option value="<?= $d['dept_id'] ?>" <?= $deptFilter==$d['dept_id']?'selected':'' ?>>
              <?= htmlspecialchars($d['dept_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-auto align-self-end">
        <button class="btn btn-dark">Filter</button>
      </div>
    </form>

    <div class="table-responsive mb-5">
      <table class="table table-striped table-bordered align-middle">
        <thead class="table-dark">
          <tr>
            <th>Month</th><th>Department</th><th>Evaluator</th><th>Target</th>
            <th>Indicator</th><th>Rating</th><th>Date</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($allEvals as $ev): ?>
          <tr>
            <td><?= $ev['month'] ?></td>
            <td><?= $ev['dept_name'] ?? '-' ?></td>
            <td><?= htmlspecialchars($ev['evaluator']) ?></td>
            <td><?= htmlspecialchars($ev['evaluatee']) ?></td>
            <td><?= htmlspecialchars($ev['indicator']) ?></td>
            <td><?= $ev['rating'] ?></td>
            <td><?= date('Y-m-d',strtotime($ev['date_submitted'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

  <?php /* ================= EMPLOYEE OR MANAGER FORM ================= */ ?>
  <?php else:
        $windowOpen = isWindowOpen($user);
  ?>
    <h1 class="mb-4"><?= $roleId===ROLE_EMPLOYEE? 'Peer Evaluations' : 'Team Evaluations' ?></h1>


    <?php if (!$windowOpen): ?>
  <div class="alert alert-warning">
    Rating window is closed. It opens during the last 
    <?= ($user['rating_window_days'] ?? $globalDays) ?> 
    day<?= (($user['rating_window_days'] ?? $globalDays) > 1 ? 's' : '') ?> of each month.
  </div>
<?php endif; ?>

    

    <form method="post">
      <?php if ($roleId === ROLE_EMPLOYEE): /* ---- employee view ---- */ ?>
        <?php foreach ($peers as $p): ?>
          <?php include __DIR__ . '/partials/eval_target_card.php'; ?>
        <?php endforeach; ?>

        <?php if (!empty($managerRow)): ?>
  <?php $targetIsManager = true; $p = $managerRow; include __DIR__ . '/partials/eval_target_card.php'; ?>
<?php endif; ?>


      <?php else: /* ---- manager view ---- */ ?>
        <?php foreach ($team as $p): ?>
          <?php $targetIsManager = false; include __DIR__ . '/partials/eval_target_card.php'; ?>
        <?php endforeach; ?>
      <?php endif; ?>

      <div class="text-center my-4">
        <button class="btn btn-dark btn-lg"
                <?= $windowOpen? '' : 'disabled' ?>>Submit Evaluations</button>
      </div>
    </form>
  <?php endif; ?>

</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
