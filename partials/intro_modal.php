<?php
require_once __DIR__ . '/../backend/auth.php';
require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/settings_controller.php';   // NEW

secureSessionStart();

$userId = $_SESSION['user_id'] ?? null;
$roleId = $_SESSION['role_id'] ?? null;
$show   = false;

/* ---------- One-time "intro_seen" flag ------------------------------- */
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

<style>
/* Modal specific styling to match your design system */
.intro-modal .modal-content {
    border: 2px solid #000000 !important;
    border-radius: 0 !important;
    background: #ffffff;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.intro-modal .modal-header {
    background: #000000;
    color: #ffffff;
    border: none !important;
    padding: 1.5rem 2rem;
    border-radius: 0 !important;
    position: relative;
}

.intro-modal .modal-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.5rem;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.intro-modal .role-indicator {
    width: 12px;
    height: 12px;
    border-radius: 50%;
    flex-shrink: 0;
}

.intro-modal .role-indicator.employee {
    background: #28a745;
}

.intro-modal .role-indicator.manager {
    background: #007bff;
}

.intro-modal .btn-close {
    background: none;
    border: none;
    color: #ffffff;
    font-size: 1.5rem;
    opacity: 0.8;
    transition: opacity 0.2s ease;
}

.intro-modal .btn-close:hover {
    opacity: 1;
    color: #ffffff;
}

.intro-modal .modal-body {
    padding: 2rem;
    font-family: 'Merriweather', serif;
    line-height: 1.6;
    color: #000000;
    background: #ffffff;
}

.intro-modal .welcome-section {
    text-align: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1.5rem;
    border-bottom: 1px solid #e0e0e0;
}

.intro-modal .welcome-icon {
    width: 60px;
    height: 60px;
    background: #f8f9fa;
    border: 2px solid #000000;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 1rem auto;
    font-size: 1.5rem;
}

.intro-modal .role-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    background: #f8f9fa;
    border: 1px solid #000000;
    padding: 0.5rem 1rem;
    font-weight: 600;
    text-transform: uppercase;
    font-size: 0.875rem;
    letter-spacing: 0.5px;
}

.intro-modal .role-badge .badge-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.intro-modal .role-badge.employee .badge-dot {
    background: #28a745;
}

.intro-modal .role-badge.manager .badge-dot {
    background: #007bff;
}

.intro-modal .instructions {
    margin-bottom: 1.5rem;
}

.intro-modal .instruction-item {
    display: flex;
    align-items: flex-start;
    gap: 1rem;
    margin-bottom: 1rem;
    padding: 1rem;
    background: #f8f9fa;
    border-left: 4px solid transparent;
}

.intro-modal .instruction-item.primary {
    border-left-color: #007bff;
}

.intro-modal .instruction-item.success {
    border-left-color: #28a745;
}

.intro-modal .instruction-number {
    background: #000000;
    color: #ffffff;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    font-size: 0.875rem;
    flex-shrink: 0;
    margin-top: 0.125rem;
}

.intro-modal .instruction-text {
    flex: 1;
}

.intro-modal .instruction-text strong {
    color: #000000;
}

.intro-modal .highlight-box {
    background: #ffffff;
    border: 2px solid #000000;
    padding: 1rem;
    margin: 1rem 0;
    position: relative;
}

.intro-modal .highlight-box::before {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    height: 4px;
    background: linear-gradient(90deg, #007bff, #28a745);
}

.intro-modal .window-days {
    font-size: 1.25rem;
    font-weight: 700;
    color: #000000;
}

.intro-modal .modal-footer {
    background: #f8f9fa;
    border: none !important;
    padding: 1.5rem 2rem;
    text-align: center;
}

.intro-modal .btn-primary {
    background: #000000;
    border: 2px solid #000000;
    color: #ffffff;
    padding: 0.75rem 2rem;
    font-family: 'Merriweather', serif;
    font-weight: 600;
    border-radius: 0;
    transition: all 0.2s ease;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-size: 0.875rem;
}

.intro-modal .btn-primary:hover {
    background: #ffffff;
    color: #000000;
    border-color: #000000;
}

.intro-modal .footer-note {
    margin-top: 1rem;
    font-size: 0.875rem;
    color: #666666;
    font-style: italic;
}
</style>

<!-- ===== Intro Modal (auto-shown) ===== -->
<div class="modal fade intro-modal" id="firstLoginModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">
          <div class="role-indicator <?= $roleId === 3 ? 'employee' : 'manager' ?>"></div>
          Welcome to SK-PM
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">×</button>
      </div>
      
      <div class="modal-body">
        <div class="welcome-section">
          <div class="welcome-icon">
            <?= $roleId === 3 ? '👤' : '👥' ?>
          </div>
          <div class="role-badge <?= $roleId === 3 ? 'employee' : 'manager' ?>">
            <div class="badge-dot"></div>
            <?= $roleId === 3 ? 'Employee' : 'Manager' ?>
          </div>
        </div>

        <div class="instructions">
          <?php if ($roleId === 3): /* Employee */ ?>
            <div class="instruction-item primary">
              <div class="instruction-number">1</div>
              <div class="instruction-text">
                You'll <strong>rate your co-workers and your manager</strong> during the evaluation window.
              </div>
            </div>
            
            <div class="instruction-item success">
              <div class="instruction-number">2</div>
              <div class="instruction-text">
                The rating window opens on the last 
                <strong class="window-days"><?= $windowDays ?></strong>
                day<?= $windowDays > 1 ? 's' : '' ?> of every month.
              </div>
            </div>

            <div class="highlight-box">
              <strong>📅 How you'll know:</strong>
              <br>
              A reminder will pop up and your <strong>Submit Ratings</strong> button will turn green when the window opens.
            </div>

          <?php elseif ($roleId === 2): /* Manager */ ?>
            <div class="instruction-item primary">
              <div class="instruction-number">1</div>
              <div class="instruction-text">
                <strong>At the start of each month</strong>, enter your department's KPI plan (indicators + goals).
              </div>
            </div>
            
            <div class="instruction-item success">
              <div class="instruction-number">2</div>
              <div class="instruction-text">
                <strong>At month-end</strong>, submit actuals for each KPI and rate your team members.
              </div>
            </div>

            <div class="highlight-box">
              <strong>⏰ Rating Window:</strong>
              <br>
              You have <strong class="window-days"><?= $windowDays ?></strong>
              day<?= $windowDays > 1 ? 's' : '' ?> to complete the ratings.
              <br><br>
              <strong>📱 Notifications:</strong>
              <br>
              Your buttons stay disabled until the window opens. We'll remind you via Telegram.
            </div>
          <?php endif; ?>
        </div>
      </div>
      
      <div class="modal-footer">
        <button type="button" class="btn btn-primary" data-bs-dismiss="modal">
          Got it, Let's Start
        </button>
        <div class="footer-note">
          This message will only appear once
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const modalEl = document.getElementById('firstLoginModal');
  if (modalEl) {
    const modal = new bootstrap.Modal(modalEl, {backdrop: 'static', keyboard: false});
    modal.show();
  }
});
</script>