<?php
/* --------------------------------------------------------------------------
 * File: department_plan.php
 * Purpose: Managers (and Admins) build the department KPI plan for a given month.
 * -------------------------------------------------------------------------- */
declare(strict_types=1);

require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/utils.php';
require_once __DIR__ . '/backend/user_controller.php';
require_once __DIR__ . '/backend/indicator_controller.php';
require_once __DIR__ . '/backend/department_controller.php';

secureSessionStart();
checkLogin();

const ROLE_ADMIN   = 1;
const ROLE_MANAGER = 2;

if (!in_array($_SESSION['role_id'] ?? 0, [ROLE_ADMIN, ROLE_MANAGER], true)) {
    header('HTTP/1.1 403 Forbidden');
    echo '<h1>Access Denied</h1>';
    exit;
}

$user   = getUser($_SESSION['user_id']);
$roleId = (int)$user['role_id'];
$deptId = (int)$user['dept_id'];

$monthKey = $_GET['month'] ?? date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-01$/', $monthKey)) {
    $monthKey = date('Y-m-01');
}

use Backend\IndicatorRepository;
use Backend\DepartmentRepository;

$indRepo  = $indRepo  ?? new IndicatorRepository($pdo);
$deptRepo = $deptRepo ?? new DepartmentRepository($pdo);

$flash = ['msg' => '', 'type' => 'success'];

function monthLink(string $base, int $offset): string {
    $dt = new DateTime($base);
    $dt->modify(($offset >= 0 ? '+' : '') . $offset . ' month');
    return $dt->format('Y-m-01');
}

/* ----------------------------- POST Actions ----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $errors = [];

    if ($action === 'add') {
        $indicatorId = $_POST['indicator_id'] === '' ? null : (int)$_POST['indicator_id'];
        $customName  = trim($_POST['custom_name'] ?? '');
        $targetRaw   = $_POST['target_value'] ?? '';
        $weightRaw   = $_POST['weight'] ?? '';

        if ($indicatorId === null && $customName === '') $errors[] = 'Choose an indicator OR enter a custom KPI name.';
        if ($indicatorId !== null && $customName !== '') $errors[] = 'Do not provide both indicator and custom name.';
        if ($targetRaw === '' || !is_numeric($targetRaw)) $errors[] = 'Target must be numeric.';
        if ($weightRaw === '' || !ctype_digit(ltrim($weightRaw, '-'))) $errors[] = 'Weight must be an integer.';
        if (mb_strlen($customName) > 255) $errors[] = 'Custom KPI name too long (max 255).';
        if (isset($_POST['unit_of_goal']) && mb_strlen($_POST['unit_of_goal']) > 50) $errors[] = 'Unit of Goal too long (max 50).';

        if ($errors) {
            $flash = ['msg' => implode('<br>', $errors), 'type' => 'danger'];
        } else {
            $data = [
                'indicator_id'       => $indicatorId,
                'custom_name'        => $customName,
                'is_custom'          => $indicatorId === null,
                'target_value'       => (float)$targetRaw,
                'weight'             => (int)$weightRaw,
                'unit_of_goal'       => sanitize($_POST['unit_of_goal'] ?? ''),
                'unit'               => sanitize($_POST['unit'] ?? ''), // kept for snapshot schema compatibility
                'way_of_measurement' => sanitize($_POST['way_of_measurement'] ?? ''),
                'created_by'         => $user['user_id'],
            ];
            try {
                $deptRepo->addSnapshot($deptId, $monthKey, $data);
                $flash = ['msg' => 'KPI added to plan.', 'type' => 'success'];
            } catch (Exception $e) {
                $flash = ['msg' => 'Error: ' . htmlspecialchars($e->getMessage()), 'type' => 'danger'];
            }
        }
        redirect("department_plan.php?month=$monthKey");
    }

    if ($action === 'update') {
        $snapId     = (int)($_POST['snapshot_id'] ?? 0);
        $targetRaw  = $_POST['target_value'] ?? '';
        $weightRaw  = $_POST['weight'] ?? '';

        if ($targetRaw === '' || !is_numeric($targetRaw)) $errors[] = 'Target must be numeric.';
        if ($weightRaw === '' || !ctype_digit(ltrim($weightRaw, '-'))) $errors[] = 'Weight must be an integer.';
        if ($snapId <= 0) $errors[] = 'Missing snapshot id.';

        if ($errors) {
            $flash = ['msg' => implode('<br>', $errors), 'type' => 'danger'];
        } else {
            try {
                $deptRepo->updateSnapshot($snapId, (float)$targetRaw, (int)$weightRaw);
                $flash = ['msg' => 'Entry updated.', 'type' => 'success'];
            } catch (Exception $e) {
                $flash = ['msg' => 'Update failed: ' . htmlspecialchars($e->getMessage()), 'type' => 'danger'];
            }
        }
        redirect("department_plan.php?month=$monthKey");
    }

    if ($action === 'remove') {
        $snapId = (int)($_POST['snapshot_id'] ?? 0);
        if ($snapId <= 0) {
            $flash = ['msg' => 'Missing snapshot id.', 'type' => 'danger'];
            redirect("department_plan.php?month=$monthKey");
        }
        try {
            $deptRepo->removeSnapshot($snapId);
            $flash = ['msg' => 'Entry removed.', 'type' => 'info'];
        } catch (Exception $e) {
            $flash = ['msg' => 'Remove failed: ' . htmlspecialchars($e->getMessage()), 'type' => 'danger'];
        }
        redirect("department_plan.php?month=$monthKey");
    }
}

/* ------------------------------ Data Load ------------------------------- */
$deptIndicators = $indRepo->fetchDepartmentIndicators(true); // active only
$snapshots      = $deptRepo->fetchDepartmentSnapshots($deptId, $monthKey);

/* --------------------------- View / HTML Start ------------------------- */
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

<!-- Bootstrap CSS (correct SRI) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
      rel="stylesheet"
      integrity="sha384-9ndCyUa6mY2Hl2c53v9FRR0z0rsEkR3O89E+9aZ1OgGvJvH+0hZ5P2x0ZKKb4L1p"
      crossorigin="anonymous">

<link rel="stylesheet" href="./assets/css/style.css">
<style>
/* Safeguard to ensure opaque modal */
.modal-content { background:#fff !important; }
</style>
</head>
<body class="bg-light font-serif">

<div class="container py-4">

  <?php if ($flash['msg']) flashMessage($flash['msg'], $flash['type']); ?>

  <div class="d-flex align-items-center mb-4 flex-wrap gap-2">
    <h1 class="me-auto mb-0">Monthly Department Plan</h1>
    <form method="get" class="d-flex align-items-center gap-2">
      <input type="month"
             name="month"
             class="form-control"
             value="<?= htmlspecialchars(date('Y-m', strtotime($monthKey))) ?>"
             onchange="this.form.submit()">
      <a href="?month=<?= monthLink($monthKey, -1) ?>" class="btn btn-outline-secondary">&laquo;</a>
      <a href="?month=<?= monthLink($monthKey, +1) ?>" class="btn btn-outline-secondary">&raquo;</a>
    </form>
  </div>

  <!-- Add KPI -->
  <div class="card shadow-sm mb-5">
    <div class="card-body">
      <h5 class="card-title">Add KPI to Plan</h5>
      <form class="row g-3" method="post" novalidate>
        <input type="hidden" name="action" value="add">

        <div class="col-md-5">
          <label class="form-label">Choose Indicator</label>
          <select name="indicator_id" class="form-select">
            <option value="">-- select --</option>
            <?php foreach ($deptIndicators as $ind): ?>
              <option value="<?= $ind['indicator_id'] ?>">
                <?= htmlspecialchars($ind['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">Leave blank if adding a custom KPI.</div>
        </div>

        <div class="col-md-4">
          <label class="form-label">Or Custom KPI</label>
            <input type="text" name="custom_name" class="form-control" placeholder="Custom KPI name">
            <div class="form-text">Leave blank if selecting an indicator.</div>
        </div>

        <div class="col-md-3">
          <label class="form-label">Unit of Goal</label>
          <input type="text" name="unit_of_goal" class="form-control" placeholder="e.g. %, count">
        </div>

        <div class="col-md-2">
          <label class="form-label">Target<span class="text-danger">*</span></label>
          <input type="number" step="0.01" name="target_value" class="form-control" required>
        </div>
        <div class="col-md-2">
          <label class="form-label">Weight %<span class="text-danger">*</span></label>
          <input type="number" name="weight" class="form-control" required>
        </div>

        <div class="col-12 text-end">
          <button class="btn btn-dark">Add</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Plan Table -->
  <?php if (empty($snapshots)): ?>
    <div class="alert alert-info mb-5">
      No KPIs planned for <?= htmlspecialchars(date('F Y', strtotime($monthKey))) ?>.
    </div>
  <?php else: ?>
    <div class="table-responsive mb-5">
      <table class="table table-bordered align-middle">
        <thead class="table-dark">
          <tr>
            <th>KPI</th>
            <th>Target</th>
            <th>Weight</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php
          $modalBuffer = '';
          foreach ($snapshots as $row):
            $label = $row['indicator_name'] ?: $row['custom_name'];
        ?>
          <tr>
            <td>
              <?= htmlspecialchars($label) ?>
              <?php if ($row['is_custom']): ?><span class="badge bg-info">Custom</span><?php endif; ?>
            </td>
            <td><?= htmlspecialchars((string)$row['target_value']) ?> <?= htmlspecialchars($row['unit'] ?? '') ?></td>
            <td><?= htmlspecialchars((string)$row['weight']) ?>%</td>
            <td class="text-center">
              <button class="btn btn-sm btn-outline-secondary"
                      data-bs-toggle="modal"
                      data-bs-target="#editSnap<?= $row['snapshot_id'] ?>">Edit</button>
              <form class="d-inline" method="post" onsubmit="return confirm('Remove this KPI from the plan?');">
                <input type="hidden" name="action" value="remove">
                <input type="hidden" name="snapshot_id" value="<?= $row['snapshot_id'] ?>">
                <button class="btn btn-sm btn-outline-danger">Remove</button>
              </form>
            </td>
          </tr>
        <?php
          // Build modal HTML outside the table structure
          ob_start(); ?>
          <div class="modal fade" id="editSnap<?= $row['snapshot_id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
              <div class="modal-content">
                <form method="post" novalidate>
                  <input type="hidden" name="action" value="update">
                  <input type="hidden" name="snapshot_id" value="<?= $row['snapshot_id'] ?>">

                  <div class="modal-header">
                    <h5 class="modal-title">Edit KPI</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                  </div>

                  <div class="modal-body">
                    <div class="mb-3">
                      <label class="form-label">KPI</label>
                      <input type="text" class="form-control" disabled
                             value="<?= htmlspecialchars($label) ?>">
                    </div>
                    <div class="row">
                      <div class="col mb-3">
                        <label class="form-label">Target<span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="target_value"
                               class="form-control" required
                               value="<?= htmlspecialchars((string)$row['target_value']) ?>">
                      </div>
                      <div class="col mb-3">
                        <label class="form-label">Weight %<span class="text-danger">*</span></label>
                        <input type="number" name="weight" class="form-control" required
                               value="<?= htmlspecialchars((string)$row['weight']) ?>">
                      </div>
                    </div>
                  </div>

                  <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-dark">Save</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        <?php
          $modalBuffer .= ob_get_clean();
          endforeach;
        ?>
        </tbody>
      </table>
    </div>
  <?php
    // Output modals AFTER table
    echo $modalBuffer;
  endif; ?>

</div><!-- /container -->

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz"
        crossorigin="anonymous"></script>
<script>
if (typeof bootstrap === 'undefined') {
  console.warn('Bootstrap JS not detected: modals will not function.');
}
</script>
</body>
</html>
