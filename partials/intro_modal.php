<?php
require_once __DIR__ . '/../backend/auth.php';
require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/settings_controller.php';   // NEW

secureSessionStart();

$userId = $_SESSION['user_id'] ?? null;
$roleId = $_SESSION['role_id'] ?? null;
$show   = false;

/* ---------- One-time “intro_seen” flag ------------------------------- */
if ($userId && in_array($roleId, [2, 3], true)) {
    $chk = $pdo->prepare(
        'SELECT 1 FROM user_flags
          WHERE user_id = :uid AND flag_name = "intro_seen" LIMIT 1'
    );
    $chk->execute([':uid' => $userId]);

    if (!$chk->fetchColumn()) {
        $show = true;
        $pdo->prepare(
            'INSERT INTO user_flags (user_id,flag_name,seen_at)
             VALUES (:uid,"intro_seen",NOW())'
        )->execute([':uid' => $userId]);
    }
}
if (!$show) return;               // bail if modal already shown

/* ---------- Fetch window-length (user override → global default) ----- */
$settingsRepo = new Backend\SettingsRepository($pdo);
$globalDays   = (int)($settingsRepo->getSetting('evaluation_deadline_days') ?? 2);

$winStmt = $pdo->prepare(
    'SELECT COALESCE(rating_window_days, :def) AS days
       FROM users
      WHERE user_id = :uid'
);
$winStmt->execute([':def' => $globalDays, ':uid' => $userId]);
$windowDays = (int)$winStmt->fetchColumn();

?>
<!-- ===== Intro Modal (auto-shown) ===== -->
<div class="modal fade" id="firstLoginModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content shadow-lg">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title">Welcome to SK-PM</h5>
        <button type="button" class="btn-close btn-close-white"
                data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <?php if ($roleId === 3): /* Employee */ ?>
          <p class="mb-2">
            You’ll <strong>rate your co-workers and your manager</strong>
            on the last <strong><?= $windowDays ?></strong>
            day<?= $windowDays > 1 ? 's' : '' ?> of every month.
          </p>
          <p class="mb-0">
            A reminder will pop up—and your Submit Ratings button will turn green—when the window opens.
          </p>
        <?php elseif ($roleId === 2): /* Manager */ ?>
          <p class="mb-2">
            <strong>At the start of each month</strong>, enter your
            department’s KPI plan (indicators + goals).
          </p>
          <p class="mb-2">
            <strong>At month-end</strong>, submit actuals for each KPI and rate your team members.
            You have <strong><?= $windowDays ?></strong>
            day<?= $windowDays > 1 ? 's' : '' ?> to complete the ratings.
          </p>
          <p class="mb-0">
            Your buttons will stay disabled until the window opens; we’ll remind you via Telegram.
          </p>
        <?php endif; ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-info" data-bs-dismiss="modal">
          Got it
        </button>
      </div>
    </div>
  </div>
</div>

<script><!-- bootstrap 5 auto-show -->
document.addEventListener('DOMContentLoaded', () => {
  const modalEl = document.getElementById('firstLoginModal');
  if (modalEl) {
    const modal = new bootstrap.Modal(modalEl, {backdrop: 'static'});
    modal.show();
  }
});
</script>
