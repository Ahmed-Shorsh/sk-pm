<?php
// File: settings.php (Admin – Global Settings)

require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/utils.php';
require_once __DIR__ . '/backend/settings_controller.php';

secureSessionStart();
checkLogin();
if (($_SESSION['role_id'] ?? 0) !== 1) {
    header('HTTP/1.1 403 Forbidden');
    exit('<h1>Access Denied</h1>');
}

// Load current settings
$settingsRepo     = new Backend\SettingsRepository($pdo);
$current          = $settingsRepo->getAllSettings();
$deadlineDays     = isset($current['evaluation_deadline_days'])
                    ? (int)$current['evaluation_deadline_days']
                    : 2;
$individualWeight = isset($current['individual_score_weight'])
                    ? (int)$current['individual_score_weight']
                    : 30;
$departmentWeight = isset($current['department_score_weight'])
                    ? (int)$current['department_score_weight']
                    : 70;
$telegramRequired = isset($current['telegram_signup_required'])
                    ? (bool)$current['telegram_signup_required']
                    : false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    $newDeadline     = max(0, (int)($_POST['evaluation_deadline_days'] ?? 0));
    $newIndWeight    = max(0, min(100, (int)($_POST['individual_score_weight'] ?? 0)));
    $newDeptWeight   = max(0, min(100, (int)($_POST['department_score_weight'] ?? 0)));
    $newTelegramReq  = isset($_POST['telegram_signup_required']) ? '1' : '0';

    if ($newIndWeight + $newDeptWeight !== 100) {
        flashMessage('Individual and department weights must sum to 100%.', 'danger');
    } else {
        $ok = $settingsRepo->updateSettings([
            'evaluation_deadline_days' => (string)$newDeadline,
            'individual_score_weight'  => (string)$newIndWeight,
            'department_score_weight'  => (string)$newDeptWeight,
            'telegram_signup_required' => $newTelegramReq
        ]);
        if ($ok) {
            flashMessage('Settings updated successfully.', 'success');
        } else {
            flashMessage('Error: Failed to update settings.', 'danger');
        }
        redirect('settings.php');
    }
}

// Include the admin navbar
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

    <?= $GLOBALS['message_html'] ?? '' ?>

    <form method="post">
      <input type="hidden" name="action" value="update">

      <div class="mb-3">
        <label class="form-label">Evaluation Lock Deadline (days before month-end)</label>
        <input
          type="number"
          name="evaluation_deadline_days"
          class="form-control"
          value="<?= htmlspecialchars($deadlineDays, ENT_QUOTES) ?>"
          min="0"
          required
        >
        <div class="form-text">
          Number of days before the end of the month when evaluations are locked.
          Use 0 for no lock (always open until month-end).
        </div>
      </div>

      <div class="mb-3">
        <label class="form-label">Individual Score Weight (%)</label>
        <input
          type="number"
          name="individual_score_weight"
          class="form-control"
          value="<?= htmlspecialchars($individualWeight, ENT_QUOTES) ?>"
          min="0"
          max="100"
          required
        >
      </div>

      <div class="mb-3">
        <label class="form-label">Department Score Weight (%)</label>
        <input
          type="number"
          name="department_score_weight"
          class="form-control"
          value="<?= htmlspecialchars($departmentWeight, ENT_QUOTES) ?>"
          min="0"
          max="100"
          required
        >
      </div>

      <div class="form-text mb-3">
        * The two weights must sum to 100%.
      </div>

      <div class="mb-3 form-check">
        <input
          type="checkbox"
          name="telegram_signup_required"
          id="chkTelegramReq"
          class="form-check-input"
          <?= $telegramRequired ? 'checked' : '' ?>
        >
        <label class="form-check-label" for="chkTelegramReq">
          Require Telegram verification during user signup
        </label>
      </div>

      <button type="submit" class="btn btn-primary">Save Settings</button>
    </form>
  </div>
</body>
</html>
