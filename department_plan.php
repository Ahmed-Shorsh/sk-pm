<?php
/* --------------------------------------------------------------------------
 * File: department_plan.php
 * Purpose: Managers (and Admins) build the department KPI plan for a given month.
 * Weight rule: Sum of all KPI weights per month must NOT exceed 100%.
 * -------------------------------------------------------------------------- */
declare(strict_types=1);

require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/utils.php';
require_once __DIR__ . '/backend/user_controller.php';
require_once __DIR__ . '/backend/indicator_controller.php';
require_once __DIR__ . '/backend/department_controller.php';

secureSessionStart();
checkLogin();

const ROLE_ADMIN     = 1;
const ROLE_MANAGER   = 2;
const MAX_WEIGHT_PCT = 100;

if (!in_array($_SESSION['role_id'] ?? 0, [ROLE_ADMIN, ROLE_MANAGER], true)) {
    header('HTTP/1.1 403 Forbidden');
    echo '<h1>Access Denied</h1>';
    exit;
}

$user       = getUser($_SESSION['user_id']);
$roleId     = (int)$user['role_id'];
$deptId     = (int)$user['dept_id'];


$monthKey   = $_GET['month'] ?? date('Y-m-01');

if (preg_match('/^\d{4}-\d{2}$/', $monthKey)) {
    $monthKey .= '-01';
}
if (!preg_match('/^\d{4}-\d{2}-01$/', $monthKey)) {
    $monthKey = date('Y-m-01');    
}


use Backend\IndicatorRepository;
use Backend\DepartmentRepository;

$indRepo  = $indRepo  ?? new IndicatorRepository($pdo);
$deptRepo = $deptRepo ?? new DepartmentRepository($pdo);

$flash = ['msg' => '', 'type' => 'success'];

/* ------------------------------------------------------------------
 *  Helper functions
 * ------------------------------------------------------------------ */

/** Link helper for < and > buttons */
function monthLink(string $base, int $offset): string {
    $dt = new DateTime($base);
    $dt->modify(($offset >= 0 ? '+' : '') . $offset . ' month');
    return $dt->format('Y-m-01');
}

/** ACL filter: keep only allowed snapshots (or custom) */
function filterAllowedSnapshots(array $rows, int $deptId): array {
    return array_values(array_filter($rows, static function(array $r) use ($deptId): bool {
        if (empty($r['indicator_id']))  return true;                 // custom KPI
        if ((int)$r['indicator_active'] !== 1) return false;         // inactive
        if (empty($r['responsible_departments'])) return false;      // no list
        $list = array_map('intval', explode(',', $r['responsible_departments']));
        return in_array($deptId, $list, true);
    }));
}

/** Sum of weights, optional exclude id (for edits) */
function computeWeightSum(array $snapshots, ?int $excludeId = null): int {
    $sum = 0;
    foreach ($snapshots as $s) {
        if ($excludeId !== null && (int)$s['snapshot_id'] === $excludeId) continue;
        $sum += (int)$s['weight'];
    }
    return $sum;
}

/** Fetch active KPIs assigned to this department */
function loadDeptIndicators(IndicatorRepository $repo, int $deptId): array {
    $all = $repo->fetchDepartmentIndicators(true); // active only
    return array_values(array_filter($all, static function(array $r) use ($deptId): bool {
        if (empty($r['responsible_departments'])) return false;
        $ids = array_map('intval', explode(',', $r['responsible_departments']));
        return in_array($deptId, $ids, true);
    }));
}

/* ----------------------------- PRE-LOAD ----------------------------- */
/* These vars must exist BEFORE any POST logic to avoid null errors. */
$deptIndicators = loadDeptIndicators($indRepo, $deptId);   // ACL-filtered indicator list

/* ----------------------------- POST Actions ----------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $errors = [];

    /* Current plan for weight / duplicate checks */
    $currentSnapshots = $deptRepo->fetchDepartmentSnapshots($deptId, $monthKey);
    $currentSnapshots = filterAllowedSnapshots($currentSnapshots, $deptId);
    $currentTotal     = computeWeightSum($currentSnapshots);

    /* ============== ADD  ============== */
    if ($action === 'add') {
        $indicatorId = $_POST['indicator_id'] === '' ? null : (int)$_POST['indicator_id'];
        $customName  = trim($_POST['custom_name'] ?? '');
        $targetRaw   = $_POST['target_value'] ?? '';
        $weightRaw   = $_POST['weight'] ?? '';

        /* Basic validation */
        if ($indicatorId === null && $customName === '') $errors[] = 'Choose an indicator OR enter a custom KPI name.';
        if ($indicatorId !== null && $customName !== '') $errors[] = 'Do not provide both indicator and custom name.';
        if ($indicatorId !== null && !array_filter($deptIndicators, fn($r) => (int)$r['indicator_id'] === $indicatorId)) {
            $errors[] = 'Selected KPI is not assigned to your department.';
        }
        if ($targetRaw === '' || !is_numeric($targetRaw)) $errors[] = 'Target must be numeric.';
        if ($weightRaw === '' || !ctype_digit(ltrim($weightRaw, '-'))) $errors[] = 'Weight must be a positive integer.';
        if ($weightRaw !== '' && (int)$weightRaw <= 0) $errors[] = 'Weight must be greater than zero.';
        if (mb_strlen($customName) > 255) $errors[] = 'Custom KPI name too long (max 255).';
        if (isset($_POST['unit_of_goal']) && mb_strlen($_POST['unit_of_goal']) > 50) $errors[] = 'Unit of Goal too long (max 50).';

        /* Duplicate guard */
        foreach ($currentSnapshots as $s) {
            if ($indicatorId !== null && (int)$s['indicator_id'] === $indicatorId) {
                $errors[] = 'This KPI is already in your plan for this month.';
                break;
            }
            if ($indicatorId === null && strtolower($s['custom_name']) === strtolower($customName)) {
                $errors[] = 'You already added a custom KPI with this name this month.';
                break;
            }
        }

        /* Weight capacity */
        $newWeight      = (int)($weightRaw === '' ? 0 : $weightRaw);
        $proposedTotal  = $currentTotal + $newWeight;
        if (!$errors && $proposedTotal > MAX_WEIGHT_PCT) {
            $remaining = MAX_WEIGHT_PCT - $currentTotal;
            $errors[]  = sprintf(
                'Cannot add weight %d%%: remaining capacity is %d%%.',
                $newWeight,
                max(0, $remaining)
            );
        }

        if ($errors) {
            $flash = ['msg' => implode('<br>', $errors), 'type' => 'danger'];
        } else {
            $data = [
                'indicator_id'       => $indicatorId,
                'custom_name'        => $customName,
                'is_custom'          => $indicatorId === null,
                'target_value'       => (float)$targetRaw,
                'weight'             => $newWeight,
                'unit_of_goal'       => sanitize($_POST['unit_of_goal'] ?? ''),
                'unit'               => sanitize($_POST['unit'] ?? ''),
                'way_of_measurement' => sanitize($_POST['way_of_measurement'] ?? ''),
                'created_by'         => $user['user_id'],
            ];
            try {
                $deptRepo->addSnapshot($deptId, $monthKey, $data);
                redirect("department_plan.php?month=$monthKey&added=1");
            } catch (Exception $e) {
                $flash = ['msg' => 'Error: ' . htmlspecialchars($e->getMessage()), 'type' => 'danger'];
            }
        }
    }


/* ============== UPDATE  ============== */
if ($action === 'update') {
  $snapId     = (int)($_POST['snapshot_id'] ?? 0);
  $targetRaw  = $_POST['target_value'] ?? '';
  $weightRaw  = $_POST['weight'] ?? '';

  if ($targetRaw === '' || !is_numeric($targetRaw)) $errors[] = 'Target must be numeric.';
  if ($weightRaw === '' || !ctype_digit(ltrim($weightRaw, '-'))) $errors[] = 'Weight must be a positive integer.';
  if ((int)$weightRaw <= 0) $errors[] = 'Weight must be greater than zero.';
  if ($snapId <= 0)        $errors[] = 'Missing snapshot id.';

  /* Find the existing snapshot */
  $oldSnap = null;
  foreach ($currentSnapshots as $s) {
      if ((int)$s['snapshot_id'] === $snapId) { $oldSnap = $s; break; }
  }
  if (!$oldSnap) $errors[] = 'Snapshot not found or not allowed.';

  /* Weight capacity check */
  $newWeight = (int)$weightRaw;
  if ($oldSnap) {
      $sumWithout   = computeWeightSum($currentSnapshots, $snapId);
      $proposedTotal = $sumWithout + $newWeight;
      if ($proposedTotal > MAX_WEIGHT_PCT) {
          $remaining = MAX_WEIGHT_PCT - $sumWithout;
          $errors[]  = sprintf(
              'Cannot set weight to %d%%: remaining capacity is %d%%.',
              $newWeight,
              max(0, $remaining)
          );
      }
  }

  if ($errors) {
      $flash = ['msg' => implode('<br>', $errors), 'type' => 'danger'];
  } else {
      try {
          $deptRepo->updateSnapshot($snapId, (float)$targetRaw, $newWeight);
          redirect("department_plan.php?month=$monthKey&updated=1");
      } catch (Exception $e) {
          $flash = ['msg' => 'Update failed: '.htmlspecialchars($e->getMessage()), 'type' => 'danger'];
      }
  }
}


    /* ============== REMOVE  ============== */
    if ($action === 'remove') {
      $snapId = (int)($_POST['snapshot_id'] ?? 0);
      if ($snapId <= 0) {
          $flash = ['msg' => 'Missing snapshot id.', 'type' => 'danger'];
      } else {
          try {
              $deptRepo->removeSnapshot($snapId);
              $flash = ['msg' => 'Entry removed.', 'type' => 'info'];
          } catch (Exception $e) {
              $flash = ['msg' => 'Remove failed: '.htmlspecialchars($e->getMessage()), 'type' => 'danger'];
          }
      }
      redirect("department_plan.php?month=$monthKey");
  }
}

/* ------------------------------ Data Load ------------------------------- */
/* 1️⃣ ACL-filtered indicators already loaded → $deptIndicators */

/* 2️⃣ Current plan */
$snapshotsRaw  = $deptRepo->fetchDepartmentSnapshots($deptId, $monthKey);
$snapshots     = filterAllowedSnapshots($snapshotsRaw, $deptId);

/* 3️⃣ Hide KPIs already planned from dropdown */
$usedIds = array_column(
    array_filter($snapshots, fn($s) => !empty($s['indicator_id'])),
    'indicator_id'
);
$availableIndicators = array_values(array_filter(
    $deptIndicators,
    fn($row) => !in_array((int)$row['indicator_id'], $usedIds, true)
));

$currentTotalWeight = computeWeightSum($snapshots);
$remainingWeight    = MAX_WEIGHT_PCT - $currentTotalWeight;

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
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
      rel="stylesheet"
      integrity="sha384-9ndCyUa6mY2Hl2c53v9FRR0z0rsEkR3O89E+9aZ1OgGvJvH+0hZ5P2x0ZKKb4L1p"
      crossorigin="anonymous">
<link rel="stylesheet" href="./assets/css/style.css">
<style>
.modal-content { background:#fff !important; }
.weight-warning { font-weight:600; }
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

  <div class="alert <?= $currentTotalWeight > MAX_WEIGHT_PCT ? 'alert-danger' : 'alert-secondary'; ?> py-2 mb-4">
    <strong>Weight Summary:</strong>
    Current total = <span id="currentTotal"><?= $currentTotalWeight ?></span>%,
    Remaining = <span id="remainingDisplay"><?= max(0, $remainingWeight) ?></span>%,
    Maximum = <?= MAX_WEIGHT_PCT ?>%.
    <?php if ($currentTotalWeight > MAX_WEIGHT_PCT): ?>
      <br><span class="text-danger weight-warning">Total exceeds 100%. Adjust weights until total ≤ 100%.</span>
    <?php else: ?>
      <br><span class="text-muted">Do not exceed 100% across all KPIs for the month.</span>
    <?php endif; ?>
  </div>

  <!-- Add KPI -->
  <div class="card shadow-sm mb-5">
    <div class="card-body">
      <h5 class="card-title">Add KPI to Plan</h5>
      <?php if ($remainingWeight <= 0): ?>
        <div class="alert alert-warning mb-3">
          You have reached the 100% weight limit. Edit existing KPIs or remove one to free capacity before adding another.
        </div>
      <?php endif; ?>
      <form class="row g-3" method="post" novalidate id="addKpiForm">
        <input type="hidden" name="action" value="add">

        <div class="col-md-5">
          <label class="form-label">Choose Indicator</label>
          <select name="indicator_id" class="form-select">
            <option value="">-- select --</option>
            <?php foreach ($availableIndicators as $ind): ?>
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
          <input type="number"
                 name="weight"
                 class="form-control"
                 required
                 min="1"
                 <?= $remainingWeight > 0 ? 'max="'.$remainingWeight.'"' : '' ?>
                 <?= $remainingWeight <= 0 ? 'disabled' : '' ?>>
          <div class="form-text">
            Remaining capacity: <span id="remainingInline"><?= max(0, $remainingWeight) ?></span>%.
          </div>
        </div>

        <div class="col-12 text-end">
          <button class="btn btn-dark" <?= $remainingWeight <= 0 ? 'disabled' : '' ?>>Add</button>
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
          ob_start(); ?>
          <div class="modal fade" id="editSnap<?= $row['snapshot_id'] ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
              <div class="modal-content">
                <form method="post" novalidate class="edit-weight-form" data-original-weight="<?= (int)$row['weight'] ?>">
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
                        <input type="number" name="weight" class="form-control edit-weight-input" required
                               min="1"
                               value="<?= htmlspecialchars((string)$row['weight']) ?>">
                        <div class="form-text small text-muted">
                          Adjust carefully: total must stay ≤ 100%.
                        </div>
                      </div>
                    </div>
                    <div class="alert alert-warning d-none weight-error mb-0 p-2"></div>
                  </div>

                  <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-dark save-edit-btn">Save</button>
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
    echo $modalBuffer;
  endif; ?>

</div><!-- /container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz"
        crossorigin="anonymous"></script>
<script>
(function() {
  const MAX = <?= MAX_WEIGHT_PCT ?>;
  const currentTotalEl = document.getElementById('currentTotal');
  const remainingDisplay = document.getElementById('remainingDisplay');
  const remainingInline = document.getElementById('remainingInline');
  const addForm = document.getElementById('addKpiForm');
  if (addForm) {
    const addWeightInput = addForm.querySelector('input[name="weight"]');
    if (addWeightInput) {
      addWeightInput.addEventListener('input', () => {
        const entered = parseInt(addWeightInput.value || '0', 10);
        const currentTotal = parseInt(currentTotalEl.textContent || '0', 10);
        const proposed = currentTotal + (isNaN(entered) ? 0 : entered);
        const remaining = MAX - currentTotal;
        remainingInline.textContent = remaining < 0 ? 0 : remaining;
        if (proposed > MAX) {
            addWeightInput.classList.add('is-invalid');
            if (!addWeightInput.nextElementSibling || !addWeightInput.nextElementSibling.classList.contains('invalid-feedback')) {
                const fb = document.createElement('div');
                fb.className = 'invalid-feedback';
                fb.innerHTML = `Adding this weight would exceed 100%. Remaining capacity is ${remaining < 0 ? 0 : remaining}%.`;
                addWeightInput.parentNode.appendChild(fb);
            } else {
                addWeightInput.nextElementSibling.innerHTML = `Adding this weight would exceed 100%. Remaining capacity is ${remaining < 0 ? 0 : remaining}%.`;
            }
        } else {
            addWeightInput.classList.remove('is-invalid');
        }
      });
    }
  }

  // Edit modal client-side pre-check (not authoritative; server enforces)
  document.querySelectorAll('.edit-weight-form').forEach(form => {
    const input = form.querySelector('.edit-weight-input');
    const original = parseInt(form.getAttribute('data-original-weight'), 10);
    const errorBox = form.querySelector('.weight-error');
    const saveBtn = form.querySelector('.save-edit-btn');
    input.addEventListener('input', () => {
      const newVal = parseInt(input.value || '0', 10);
      const currentTotal = parseInt(currentTotalEl.textContent || '0', 10);
      const sumMinusOriginal = currentTotal - original;
      const proposedTotal = sumMinusOriginal + (isNaN(newVal) ? 0 : newVal);
      if (proposedTotal > MAX) {
        errorBox.classList.remove('d-none');
        errorBox.textContent = `This change would push total weight to ${proposedTotal}%, exceeding the 100% limit. Allowed max for this KPI is ${(MAX - sumMinusOriginal)}%.`;
        input.classList.add('is-invalid');
        saveBtn.disabled = true;
      } else {
        errorBox.classList.add('d-none');
        input.classList.remove('is-invalid');
        saveBtn.disabled = false;
      }
    });
  });
})();
if (typeof bootstrap === 'undefined') {
  console.warn('Bootstrap JS not detected: modals will not function.');
}
</script>
</body>
</html>
