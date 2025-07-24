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

/** is the current date inside the user's rating window? */
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
$showSuccessModal = false;
$showErrorModal = false;
$modalMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($roleId, [ROLE_MANAGER, ROLE_EMPLOYEE], true)) {
    if (!isWindowOpen($user)) {
        $showErrorModal = true;
        $modalMessage = 'Rating window is closed.';
    } else {
        // Check if user has already submitted for this month
        if ($evalRepo->hasUserSubmittedForMonth($user['user_id'], $monthKey)) {
            $showErrorModal = true;
            $modalMessage = 'You have already submitted your evaluations for ' . date('F Y', strtotime($monthKey)) . '. You can only submit once per month.';
        } else {
            $ratings = $_POST['ratings'] ?? [];
            $notes   = $_POST['notes']   ?? [];

            try {
                $evalRepo->saveEvaluations($user['user_id'], $monthKey, $ratings, $notes);
                $showSuccessModal = true;
                $modalMessage = 'Your evaluations for ' . date('F Y', strtotime($monthKey)) . ' have been saved successfully. Thank you for your feedback!';
            } catch (Exception $e) {
                $showErrorModal = true;
                $modalMessage = 'Error saving evaluations: ' . $e->getMessage();
            }
        }
    }
}

/* -----------------------------------------------------------------------
 * DATA FOR VIEW
 * --------------------------------------------------------------------- */
$indShared   = $indRepo->fetchIndividualIndicators(true, 'individual');
$indManager  = $indRepo->fetchIndividualIndicators(true, 'manager');

// Check if user has already submitted for this month
$hasSubmitted = false;
if (in_array($roleId, [ROLE_MANAGER, ROLE_EMPLOYEE], true)) {
    $hasSubmitted = $evalRepo->hasUserSubmittedForMonth($user['user_id'], $monthKey);
}

if ($roleId === ROLE_ADMIN) {
    $deptFilter    = $_GET['dept'] ?? '';
    $allDepts      = $deptRepo->fetchAllDepartments();
    $allEvals      = $evalRepo->fetchIndividualEvaluations($monthKey, $deptFilter ?: null);
} elseif ($roleId === ROLE_EMPLOYEE) {
    $peers         = $evalRepo->fetchPeers($user['user_id']);
    $managerRow    = $evalRepo->fetchManager($user['user_id']);
    if (empty($managerRow)) {
      $stmt = $pdo->prepare("
        SELECT user_id, name
          FROM users
         WHERE dept_id = :dept
           AND role_id = :mgrRole
           AND active  = 1
         LIMIT 1
      ");
      $stmt->execute([
        ':dept'    => $user['dept_id'],
        ':mgrRole' => ROLE_MANAGER
      ]);
      $managerRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

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

  <style>
   .manager-badge{
      display:inline-block;
      width:8px;height:8px;
      border-radius:50%;     
      background:rgb(255, 255, 255);    
     margin-right:6px;
   }
  </style>
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
    <h1 class="mb-2"><?= $roleId===ROLE_EMPLOYEE? 'Peer Evaluations' : 'Team Evaluations' ?></h1>
    <p class="text-muted mb-4">
      <strong>Evaluation Period:</strong> <?= date('F Y', strtotime($monthKey)) ?>
    </p>

    <?php if ($hasSubmitted): ?>
      <div class="alert alert-success">
        <i class="fas fa-check-circle me-2"></i>
        You have already submitted your evaluations for <?= date('F Y', strtotime($monthKey)) ?>. 
        Thank you for your participation!
      </div>
    <?php endif; ?>

    <?php if (!$windowOpen): ?>
      <div class="alert alert-warning">
        Rating window is closed. It opens during the last 
        <?= ($user['rating_window_days'] ?? $globalDays) ?> 
        day<?= (($user['rating_window_days'] ?? $globalDays) > 1 ? 's' : '') ?> of each month.
      </div>
    <?php endif; ?>

    <?php if (!$hasSubmitted): ?>
    <form method="post" id="evaluationForm">
      <?php if ($roleId === ROLE_EMPLOYEE): /* ---- employee view ---- */ ?>
      <?php foreach ($peers as $p): ?>
          <?php
              $targetIsManager = false;     
              $indicatorSet    = $indShared; 
              include __DIR__ . '/partials/eval_target_card.php';
          ?>
        <?php endforeach; ?>
                <?php if (!empty($managerRow)): ?>
          <?php
              $targetIsManager = true;
              $indicatorSet    = $indManager;           // manager-specific KPIs
              $p               = $managerRow;
              // add a tiny blue circle before the manager’s name
              $p['name'] = '<span class="manager-badge"></span>' . htmlspecialchars($p['name']);
              include __DIR__ . '/partials/eval_target_card.php';
          ?>
        <?php endif; ?>

      <?php else: /* ---- manager view ---- */ ?>
        <?php foreach ($team as $p): ?>
          <?php $targetIsManager = false; include __DIR__ . '/partials/eval_target_card.php'; ?>
        <?php endforeach; ?>
      <?php endif; ?>

      <div class="text-center my-4">
        <button type="submit" class="btn btn-dark btn-lg"
                <?= $windowOpen? '' : 'disabled' ?>>Submit Evaluations</button>
      </div>
    </form>
    <?php endif; ?>
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

    // Handle indicator descriptions
    document.addEventListener('click', function (e) {
        if (e.target.classList.contains('toggle-desc')) {
            e.preventDefault();
            const wrap = e.target.closest('.indicator-desc');
            if (!wrap) return;
            const descText = wrap.querySelector('.desc-text');
            const full = wrap.dataset.full || '';
            const short = wrap.dataset.short || '';
            const expanded = wrap.classList.toggle('expanded');
            descText.textContent = expanded ? full : short;
            e.target.textContent = expanded ? '(show less)' : '(read more)';
        }
    });

    // Form validation before submit
    const form = document.getElementById('evaluationForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const ratings = form.querySelectorAll('input[name^="ratings"]');
            let hasEmptyRating = false;
            
            ratings.forEach(function(rating) {
                if (!rating.value || rating.value === '') {
                    hasEmptyRating = true;
                }
            });
            
            if (hasEmptyRating) {
                e.preventDefault();
                alert('Please complete all ratings before submitting.');
                return false;
            }
        });
    }
});
</script>

</body>
</html>