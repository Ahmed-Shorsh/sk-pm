<?php
// File: settings.php  (Admin – Global Settings)

require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/utils.php';
require_once __DIR__ . '/backend/settings_controller.php';

secureSessionStart();
checkLogin();
if (($_SESSION['role_id'] ?? 0) !== 1) {
    header('HTTP/1.1 403 Forbidden');
    exit('<h1>Access Denied</h1>');
}

/*--------------------------------------------------------------
 | Load current settings
 *-------------------------------------------------------------*/
$settingsRepo = new Backend\SettingsRepository($pdo);
$current      = $settingsRepo->getAllSettings();

$evalDeadlineDays     = isset($current['evaluation_deadline_days'])
                      ? (int)$current['evaluation_deadline_days']
                      : 2;           // Self-evaluation window

$actualsDeadlineDays  = isset($current['actuals_entry_deadline_days'])
                      ? (int)$current['actuals_entry_deadline_days']
                      : 2;           // Managers’ actuals-entry window

$individualWeight     = isset($current['individual_score_weight'])
                      ? (int)$current['individual_score_weight']
                      : 30;

$departmentWeight     = isset($current['department_score_weight'])
                      ? (int)$current['department_score_weight']
                      : 70;

$telegramRequired     = !empty($current['telegram_signup_required']);

/*--------------------------------------------------------------
 | Handle form submission
 *-------------------------------------------------------------*/
$showSuccessModal = false;
$showErrorModal = false;
$modalMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {

    /** Deadlines (days) */
    $newEvalDays    = max(0, (int)($_POST['evaluation_deadline_days']      ?? 0));
    $newActualsDays = max(0, (int)($_POST['actuals_entry_deadline_days']   ?? 0));

    /** Weights */
    $newIndWeight  = max(0, min(100, (int)($_POST['individual_score_weight']  ?? 0)));
    $newDeptWeight = max(0, min(100, (int)($_POST['department_score_weight']  ?? 0)));

    /** Telegram signup requirement */
    $newTelegramReq = isset($_POST['telegram_signup_required']) ? '1' : '0';

    if ($newIndWeight + $newDeptWeight !== 100) {
        $showErrorModal = true;
        $modalMessage = 'Individual and department weights must sum to 100%.';
    } else {
        $ok = $settingsRepo->updateSettings([
            'evaluation_deadline_days'      => (string)$newEvalDays,
            'actuals_entry_deadline_days'   => (string)$newActualsDays,
            'individual_score_weight'       => (string)$newIndWeight,
            'department_score_weight'       => (string)$newDeptWeight,
            'telegram_signup_required'      => $newTelegramReq
        ]);

        if ($ok) {
            $showSuccessModal = true;
            $modalMessage = 'Settings updated successfully!';
        } else {
            $showErrorModal = true;
            $modalMessage = 'Error: Failed to update settings.';
        }
    }
}

/*--------------------------------------------------------------
 | Render page
 *-------------------------------------------------------------*/
include __DIR__ . '/partials/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Global Settings – Performance System</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Merriweather&family=Playfair+Display&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link rel="stylesheet" href="./assets/css/style.css">
  <link rel="icon" href="./assets/logo/sk-n.ico" type="image/x-icon">
</head>
<body class="font-serif bg-light">
  <div class="container py-4">
    <h1 class="mb-4">Global Settings</h1>

    <form method="post">
      <input type="hidden" name="action" value="update">

      <!-- Self-evaluation lock -->
      <div class="mb-3">
        <label class="form-label">Evaluation Lock Deadline (days before month-end)</label>
        <input
          type="number"
          name="evaluation_deadline_days"
          class="form-control"
          value="<?= htmlspecialchars($evalDeadlineDays, ENT_QUOTES) ?>"
          min="0"
          required>
        <div class="form-text">
          Number of days before the end of the month when <strong>individual self-evaluations</strong> are locked.
          Use 0 for no lock (open until the very last day).
        </div>
      </div>

      <!-- Managers’ actuals-entry lock -->
      <div class="mb-3">
        <label class="form-label">Actuals Entry Lock Deadline (days before month-end)</label>
        <input
          type="number"
          name="actuals_entry_deadline_days"
          class="form-control"
          value="<?= htmlspecialchars($actualsDeadlineDays, ENT_QUOTES) ?>"
          min="0"
          required>
        <div class="form-text">
          Number of days before the end of the month when <strong>department managers</strong> can no longer enter
          KPI actual values. Use 0 for no lock.
        </div>
      </div>

      <!-- Weight settings -->
      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label">Individual Score Weight (%)</label>
          <input
            type="number"
            name="individual_score_weight"
            class="form-control"
            value="<?= htmlspecialchars($individualWeight, ENT_QUOTES) ?>"
            min="0" max="100" required>
        </div>
        <div class="col-md-6">
          <label class="form-label">Department Score Weight (%)</label>
          <input
            type="number"
            name="department_score_weight"
            class="form-control"
            value="<?= htmlspecialchars($departmentWeight, ENT_QUOTES) ?>"
            min="0" max="100" required>
        </div>
        <div class="form-text">
          * The two weights must sum to&nbsp;100 %.
        </div>
      </div>

      <!-- Telegram signup requirement -->
      <div class="mb-4 form-check">
        <input
          type="checkbox"
          name="telegram_signup_required"
          id="chkTelegramReq"
          class="form-check-input"
          <?= $telegramRequired ? 'checked' : '' ?>>
        <label class="form-check-label" for="chkTelegramReq">
          Require Telegram verification during user signup
        </label>
      </div>

      <button type="submit" class="btn btn-primary">Save Settings</button>
    </form>
  </div>


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
</body>
</html>
