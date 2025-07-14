<?php
/* --------------------------------------------------------------------------
 * File: department_plan.php
 * Purpose : Managers build the department KPI plan for a given month.
 *           Admins have read/write access too.
 * -------------------------------------------------------------------------- */

require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/utils.php';
require_once __DIR__ . '/backend/user_controller.php';
require_once __DIR__ . '/backend/indicator_controller.php';
require_once __DIR__ . '/backend/department_controller.php';

secureSessionStart();
checkLogin();

/* ----- roles ----------------------------------------------------------- */
const ROLE_ADMIN   = 1;
const ROLE_MANAGER = 2;

/* ----- ACL ------------------------------------------------------------- */
if (!in_array($_SESSION['role_id'] ?? 0, [ROLE_ADMIN, ROLE_MANAGER], true)) {
    header('HTTP/1.1 403 Forbidden');
    echo '<h1>Access Denied</h1>';
    exit;
}

/* ----- context --------------------------------------------------------- */
$user     = getUser($_SESSION['user_id']);
$roleId   = (int)$user['role_id'];
$deptId   = (int)$user['dept_id'];
$monthKey = $_GET['month'] ?? date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-01$/', $monthKey)) {
    $monthKey = date('Y-m-01');
}

/* ----- repositories ---------------------------------------------------- */
use Backend\IndicatorRepository;
use Backend\DepartmentRepository;

$indRepo  = $indRepo  ?? new IndicatorRepository($pdo);
$deptRepo = $deptRepo ?? new DepartmentRepository($pdo);

/* ----- Flash helper ---------------------------------------------------- */
$flash = ['msg'=>'', 'type'=>'success'];

/* -----------------------------------------------------------------------
 * POST actions: add / update / remove snapshot
 * --------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ---- add snapshot ---- */
    if ($action === 'add') {
        $data = [
            'indicator_id'         => $_POST['indicator_id'] ? (int)$_POST['indicator_id'] : null,
            'custom_name'          => trim($_POST['custom_name']),
            'is_custom'            => empty($_POST['indicator_id']),
            'target_value'         => (float)$_POST['target_value'],
            'weight'               => (int)$_POST['weight'],
            'unit_of_goal'         => sanitize($_POST['unit_of_goal'] ?? ''),
            'unit'                 => sanitize($_POST['unit'] ?? ''),
            'way_of_measurement'   => sanitize($_POST['way_of_measurement'] ?? ''),
            'created_by'           => $user['user_id'],
        ];
        try {
            $deptRepo->addSnapshot($deptId, $monthKey, $data);
            $flash = ['msg'=>'KPI added to plan.','type'=>'success'];
        } catch (Exception $e) {
            $flash = ['msg'=>'Error: '.$e->getMessage(),'type'=>'danger'];
        }
    }

    /* ---- update snapshot ---- */
    if ($action === 'update') {
        try {
            $deptRepo->updateSnapshot(
                (int)$_POST['snapshot_id'],
                (float)$_POST['target_value'],
                (int)$_POST['weight']
            );
            $flash = ['msg'=>'Entry updated.','type'=>'success'];
        } catch (Exception $e) {
            $flash = ['msg'=>'Update failed: '.$e->getMessage(),'type'=>'danger'];
        }
    }

    /* ---- remove snapshot ---- */
    if ($action === 'remove') {
        try {
            $deptRepo->removeSnapshot((int)$_POST['snapshot_id']);
            $flash = ['msg'=>'Entry removed.','type'=>'info'];
        } catch (Exception $e) {
            $flash = ['msg'=>'Remove failed: '.$e->getMessage(),'type'=>'danger'];
        }
    }

    redirect("department_plan.php?month=$monthKey");
}

/* ----- data for view --------------------------------------------------- */
$deptIndicators = $indRepo->fetchDepartmentIndicators(true);        // active only
$snapshots      = $deptRepo->fetchDepartmentSnapshots($deptId, $monthKey);

/* ----- month nav helper ------------------------------------------------ */
function monthLink(string $base, int $offset): string {
    $dt = new DateTime($base);
    $dt->modify(($offset>=0?'+':'').$offset.' month');
    return $dt->format('Y-m-01');
}

/* ----- HTML ------------------------------------------------------------ */
include __DIR__ . '/partials/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Department Plan – SK-PM</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" href="./assets/logo/sk-n.ico">
  <link href="https://fonts.googleapis.com/css2?family=Merriweather&family=Playfair+Display&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link rel="stylesheet" href="./assets/css/style.css">
</head>
<body class="bg-light font-serif">

<div class="container py-4">

  <?php if ($flash['msg']): flashMessage($flash['msg'],$flash['type']); endif; ?>

  <div class="d-flex align-items-center mb-4">
    <h1 class="me-auto">Monthly Department Plan</h1>
    <!-- month navigator -->
    <form method="get" class="d-flex align-items-center">
      <input type="month" name="month" class="form-control w-auto"
             value="<?= date('Y-m',strtotime($monthKey)) ?>" onchange="this.form.submit()">
      <a href="?month=<?= monthLink($monthKey,-1) ?>" class="btn btn-outline-secondary ms-3">&laquo;</a>
      <a href="?month=<?= monthLink($monthKey,+1) ?>" class="btn btn-outline-secondary ms-2">&raquo;</a>
    </form>
  </div>

  <!-- ADD KPI CARD -->
  <div class="card shadow-sm mb-5">
    <div class="card-body">
      <h5 class="card-title">Add KPI to Plan</h5>
      <form class="row g-3" method="post">
        <input type="hidden" name="action" value="add">

        <div class="col-md-5">
          <label class="form-label">Choose Indicator</label>
          <select name="indicator_id" class="form-select">
            <option value="">-- select --</option>
            <?php foreach ($deptIndicators as $ind): ?>
              <option value="<?= $ind['indicator_id'] ?>">
                <?= htmlspecialchars($ind['name']) ?> (<?= htmlspecialchars($ind['unit'] ?? '-') ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="col-md-4">
          <label class="form-label">Or Custom KPI</label>
          <input type="text" name="custom_name" class="form-control" placeholder="Custom KPI name">
        </div>

        <div class="col-md-3">
          <label class="form-label">Unit of Goal</label>
          <input type="text" name="unit_of_goal" class="form-control" placeholder="e.g. % or count">
        </div>

        <div class="col-md-2">
          <label class="form-label">Target</label>
          <input type="number" step="0.01" name="target_value" class="form-control" required>
        </div>
        <div class="col-md-2">
          <label class="form-label">Weight %</label>
          <input type="number" name="weight" class="form-control" required>
        </div>

        <div class="col-12 text-end">
          <button class="btn btn-dark">Add</button>
        </div>
      </form>
    </div>
  </div>

  <!-- PLAN TABLE -->
  <?php if (empty($snapshots)): ?>
    <div class="alert alert-info">No KPIs planned for <?= date('F Y',strtotime($monthKey)) ?>.</div>
  <?php else: ?>
    <div class="table-responsive mb-5">
      <table class="table table-bordered align-middle">
        <thead class="table-dark">
          <tr>
            <th>KPI</th><th>Target</th><th>Weight</th><th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($snapshots as $row): ?>
          <tr>
            <td>
              <?= htmlspecialchars($row['indicator_name'] ?: $row['custom_name']) ?>
              <?php if ($row['is_custom']): ?><span class="badge bg-info">Custom</span><?php endif; ?>
            </td>
            <td><?= $row['target_value'] ?> <?= htmlspecialchars($row['unit'] ?? '') ?></td>
            <td><?= $row['weight'] ?>%</td>
            <td class="text-center">
              <button class="btn btn-sm btn-outline-secondary"
                      data-bs-toggle="modal"
                      data-bs-target="#edit<?= $row['snapshot_id'] ?>">Edit</button>
              <form class="d-inline" method="post">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="snapshot_id" value="<?= $row['snapshot_id'] ?>">
                <button class="btn btn-sm btn-outline-danger">Remove</button>
              </form>
            </td>
          </tr>

          <!-- EDIT MODAL -->
          <div class="modal fade" id="edit<?= $row['snapshot_id'] ?>" tabindex="-1">
            <div class="modal-dialog">
              <form class="modal-content" method="post">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="snapshot_id" value="<?= $row['snapshot_id'] ?>">

                <div class="modal-header">
                  <h5 class="modal-title">Edit KPI</h5>
                  <button class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                  <div class="mb-3">
                    <label class="form-label">KPI</label>
                    <input type="text" class="form-control" disabled
                           value="<?= htmlspecialchars($row['indicator_name'] ?: $row['custom_name']) ?>">
                  </div>
                  <div class="row">
                    <div class="col mb-3">
                      <label class="form-label">Target</label>
                      <input type="number" step="0.01" name="target_value" class="form-control" required
                             value="<?= $row['target_value'] ?>">
                    </div>
                    <div class="col mb-3">
                      <label class="form-label">Weight %</label>
                      <input type="number" name="weight" class="form-control" required
                             value="<?= $row['weight'] ?>">
                    </div>
                  </div>
                </div>

                <div class="modal-footer">
                  <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button class="btn btn-dark">Save</button>
                </div>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
