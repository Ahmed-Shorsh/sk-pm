<?php
// File: actuals_entry.php  (Department managers enter KPI actual values)

require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/utils.php';
require_once __DIR__ . '/backend/user_controller.php';
require_once __DIR__ . '/backend/department_controller.php';
require_once __DIR__ . '/backend/settings_controller.php';

/*--------------------------------------------------------------
 | Global settings
 *-------------------------------------------------------------*/
$settingsRepo = new Backend\SettingsRepository($pdo);

/** -- Default to 2 if admin never configured it */
$globalActualsDays = (int)($settingsRepo->getSetting('actuals_entry_deadline_days')
                     ?? $settingsRepo->getSetting('evaluation_deadline_days')
                     ?? 2);

/*--------------------------------------------------------------
 | Auth & ACL
 *-------------------------------------------------------------*/
secureSessionStart();
checkLogin();

const ROLE_ADMIN   = 1;
const ROLE_MANAGER = 2;

if (!in_array($_SESSION['role_id'] ?? 0, [ROLE_ADMIN, ROLE_MANAGER], true)) {
    header('HTTP/1.1 403 Forbidden');
    exit('<h1>Access Denied</h1>');
}

/*--------------------------------------------------------------
 | Helper: is the entry window open for this user?
 *-------------------------------------------------------------*/
function isWindowOpen(array $user): bool
{
    global $globalActualsDays;

    // Per-user override (rating_window_days) or fall back to globalActualsDays
    $days = $user['rating_window_days'] ?? $globalActualsDays;

    if ($days === 0) {
        // 0 means "never lock"
        return true;
    }

    $today      = new DateTime('today');
    $endOfMonth = (clone $today)->modify('last day of this month');
    $remaining  = (int)$today->diff($endOfMonth)->format('%a');  // days left incl. today

    // Window is open only during the *last N days* of the month
    return $remaining < $days;
}

/*--------------------------------------------------------------
 | Context: current user, dept, month being edited
 *-------------------------------------------------------------*/
$user     = getUser($_SESSION['user_id']);
$roleId   = (int)$user['role_id'];
$deptId   = (int)$user['dept_id'];

$monthKey = $_GET['month'] ?? date('Y-m-01');
if (!preg_match('/^\d{4}-\d{2}-01$/', $monthKey)) {
    $monthKey = date('Y-m-01');
}

/*--------------------------------------------------------------
 | Repositories
 *-------------------------------------------------------------*/
use Backend\DepartmentRepository;
$deptRepo = $deptRepo ?? new DepartmentRepository($pdo);

/*--------------------------------------------------------------
 | Modal state variables
 *-------------------------------------------------------------*/
$showSuccessModal = false;
$showErrorModal = false;
$modalMessage = '';

/*--------------------------------------------------------------
 | POST: Save actuals
 *-------------------------------------------------------------*/
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {

    // Managers must respect window; admins bypass
    if ($roleId === ROLE_MANAGER && !isWindowOpen($user)) {
        $showErrorModal = true;
        $modalMessage = 'Actuals entry window is closed for you.';
    } else {
        $actuals = $_POST['actual_value'] ?? [];
        $notes   = $_POST['notes']        ?? [];
        $paths   = $_POST['task_file_path'] ?? [];  

        $hasError = false;
        foreach ($paths as $sid => $p) {
            $p = trim($p);
            if ($p !== '' && !preg_match('/^\\\\\\\\/', $p)) {
                $showErrorModal = true;
                $modalMessage = 'File path must start with \\\\ (network share path). Please correct the path format.';
                $hasError = true;
                break;
            }
            // (We trim and keep the sanitized path back in the array)
            $paths[$sid] = $p;
        }

        if (!$hasError) {
            try {
                $deptRepo->submitActuals($deptId, $monthKey, $actuals, $notes, $paths);
                $showSuccessModal = true;
                $modalMessage = 'Actuals for ' . date('F Y', strtotime($monthKey)) . ' have been saved successfully.';
            } catch (Exception $e) {
                $showErrorModal = true;
                $modalMessage = 'Error saving actuals: ' . $e->getMessage();
            }
        }
    }
}

/*--------------------------------------------------------------
 | Fetch KPI snapshots for the month
 *-------------------------------------------------------------*/
$snapshots = $deptRepo->fetchDepartmentSnapshots($deptId, $monthKey);

/*--------------------------------------------------------------
 | UI helpers
 *-------------------------------------------------------------*/
function monthLink(string $base, int $offset): string
{
    $dt = new DateTime($base);
    $dt->modify(($offset >= 0 ? '+' : '') . $offset . ' month');
    return $dt->format('Y-m-01');
}

$windowOpen = $roleId === ROLE_ADMIN ? true : isWindowOpen($user);

/*--------------------------------------------------------------
 | HTML
 *-------------------------------------------------------------*/
include __DIR__ . '/partials/navbar.php';
include __DIR__ . '/partials/intro_modal.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Department Actuals – SK-PM</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="./assets/logo/sk-n.ico">
  <link href="https://fonts.googleapis.com/css2?family=Merriweather&family=Playfair+Display&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link rel="stylesheet" href="./assets/css/style.css">
</head>
<body class="bg-light font-serif">

<div class="container py-4">

  <?= $GLOBALS['message_html'] ?? '' ?>

  <h1 class="mb-4">Department Actuals Entry</h1>

  <!-- Month navigator -->
  <form class="d-flex align-items-center mb-4" method="get">
    <label class="me-2">Month</label>
    <input type="month" name="month" class="form-control w-auto"
           value="<?= date('Y-m', strtotime($monthKey)) ?>" onchange="this.form.submit()">
    <a class="btn btn-outline-secondary ms-3"
       href="actuals_entry.php?month=<?= monthLink($monthKey, -1) ?>">&laquo; Prev</a>
    <a class="btn btn-outline-secondary ms-2"
       href="actuals_entry.php?month=<?= monthLink($monthKey, +1) ?>">Next &raquo;</a>
  </form>

  <?php if (empty($snapshots)): ?>
    <div class="alert alert-warning">
      No KPI snapshot found for <?= date('F Y', strtotime($monthKey)) ?>.
      <a href="department_plan.php?month=<?= $monthKey ?>">Create your plan first.</a>
    </div>
  <?php else: ?>
    <?php if (!$windowOpen): ?>
      <div class="alert alert-info">
        Actuals entry opens only during the last
        <?= ($user['rating_window_days'] ?? $globalActualsDays) ?>
        day<?= (($user['rating_window_days'] ?? $globalActualsDays) > 1 ? 's' : '') ?> of each month.
      </div>
    <?php endif; ?>

    <form method="post" id="actualsForm">
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
            <th>Task File Path</th> 
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
                       <?= $windowOpen ? '' : 'disabled' ?>>
              </td>
              <td style="width:16rem">
                <textarea class="form-control" rows="2"
                          name="notes[<?= $row['snapshot_id'] ?>]"
                          placeholder="Optional notes"
                          <?= $windowOpen ? '' : 'disabled' ?>><?= htmlspecialchars($row['notes'] ?? '') ?></textarea>
              </td>
         
              <td style="width:20rem">  
                <input type="text"
                       name="task_file_path[<?= $row['snapshot_id'] ?>]"
                       value="<?= htmlspecialchars($row['task_file_path'] ?? '') ?>" 
                       class="form-control"
                       placeholder="Write in your Share Folder path"
                       <?= $windowOpen ? '' : 'disabled' ?>>
                <div class="form-text">
                    For Example: <code>\\192.168.10.252\\Plan\\{Your Department Name}\\Folder\\MyFile.png</code>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div class="text-center">
        <button class="btn btn-dark btn-lg" <?= $windowOpen ? '' : 'disabled' ?>>
          Save Actuals
        </button>
      </div>
    </form>
  <?php endif; ?>

</div><!-- /container -->

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

    // Form validation before submit
    const form = document.getElementById('actualsForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const actualInputs = form.querySelectorAll('input[name^="actual_value"]');
            let hasEmptyActual = false;
            
            actualInputs.forEach(function(input) {
                if (!input.disabled && (!input.value || input.value.trim() === '')) {
                    hasEmptyActual = true;
                }
            });
            
            if (hasEmptyActual) {
                e.preventDefault();
                alert('Please enter actual values for all KPIs before submitting.');
                return false;
            }
        });
    }
});
</script>

</body>
</html>