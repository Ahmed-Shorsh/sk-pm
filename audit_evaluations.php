<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/utils.php';
require_once __DIR__ . '/backend/evaluation_audit_repo.php';

secureSessionStart();
checkLogin();
if (($_SESSION['role_id'] ?? 0) !== 1) {
    header('HTTP/1.1 403 Forbidden'); 
    exit('Access denied');
}

$monthSel = $_GET['month'] ?? '';
$deptSel = $_GET['dept'] ?? '';
$evaluatorSel = $_GET['evaluator'] ?? '';
$statusSel = $_GET['status'] ?? '';

use Backend\EvaluationAuditRepository;
$repo = new EvaluationAuditRepository($pdo);

$months = [];
$depts = [];
$employees = [];
$auditRows = [];
$missingSubmissions = [];
$errorMessage = '';

try {
    $months = $repo->availableMonths();
    
    if ($monthSel) {
        $depts = $repo->getAllDepartmentsByMonth($monthSel);
    } else {
        $depts = $repo->allDepartments();
    }
    
    if ($deptSel && $monthSel) {
        if ($deptSel === 'all') {
            $employees = $repo->getAllEmployeesForMonth($monthSel);
        } else {
            $employees = $repo->getEmployeesForDepartmentAndMonth((int)$deptSel, $monthSel);
        }
    }
    
    if ($monthSel && $deptSel && $evaluatorSel && $statusSel) {
        if ($statusSel === 'submitted') {
            $auditRows = $repo->getEvaluatorAuditRows(
                $monthSel === 'all' ? null : $monthSel,
                $deptSel === 'all' ? null : (int)$deptSel,
                $evaluatorSel === 'all' ? null : (int)$evaluatorSel
            );
        } elseif ($statusSel === 'missing') {
            $missingSubmissions = $repo->getMissingSubmissions(
                $monthSel,
                $deptSel === 'all' ? null : (int)$deptSel,
                $evaluatorSel === 'all' ? null : (int)$evaluatorSel
            );
        } elseif ($statusSel === 'all') {
            $auditRows = $repo->getEvaluatorAuditRows(
                $monthSel === 'all' ? null : $monthSel,
                $deptSel === 'all' ? null : (int)$deptSel,
                $evaluatorSel === 'all' ? null : (int)$evaluatorSel
            );
            $missingSubmissions = $repo->getMissingSubmissions(
                $monthSel,
                $deptSel === 'all' ? null : (int)$deptSel,
                $evaluatorSel === 'all' ? null : (int)$evaluatorSel
            );
        }
    } elseif ($monthSel || $deptSel || $evaluatorSel || $statusSel) {
        if (!$monthSel) {
            $errorMessage = 'Please select a month to continue filtering.';
        } elseif (!$deptSel) {
            $errorMessage = 'Please select a department to continue filtering.';
        } elseif (!$evaluatorSel) {
            $errorMessage = 'Please select an employee to continue filtering.';
        } elseif (!$statusSel) {
            $errorMessage = 'Please select a status to view results.';
        }
    }
} catch (Exception $e) {
    $errorMessage = 'An error occurred while loading the audit data. Please try again.';
    error_log("Audit evaluations error: " . $e->getMessage());
}

include __DIR__ . '/partials/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Evaluation Audit – SK-PM</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Merriweather&family=Playfair+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./assets/css/style.css">
  <style>
    .missing-employee {
      color: #dc3545 !important;
      text-decoration: underline;
      font-weight: 500;
    }
    .submitted-employee {
      color: #198754;
    }
  </style>
</head>
<body class="bg-light font-serif">

<div class="container py-4">
  <h1 class="mb-4">Evaluation Audit</h1>
  
  <?php if ($errorMessage): ?>
    <div class="alert alert-warning mb-4" role="alert">
      <i class="fas fa-exclamation-triangle me-2"></i>
      <?= htmlspecialchars($errorMessage) ?>
    </div>
  <?php endif; ?>

  <div class="data-container">
    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <label class="form-label">Month *</label>
        <select name="month" class="form-select" onchange="updateFilters()">
          <option value="">Select Month</option>
          <?php if (!empty($months)): ?>
            <option value="all" <?= $monthSel==='all'?'selected':'' ?>>All Months</option>
            <?php foreach ($months as $m): ?>
              <option value="<?= $m ?>" <?= $m===$monthSel?'selected':'' ?>>
                <?= date('F Y', strtotime($m)) ?>
              </option>
            <?php endforeach; ?>
          <?php endif; ?>
        </select>
        <?php if (empty($months)): ?>
          <small class="text-muted">No evaluation data available</small>
        <?php endif; ?>
      </div>

      <div class="col-md-3">
        <label class="form-label">Department *</label>
        <select name="dept" class="form-select" onchange="updateFilters()" <?= !$monthSel ? 'disabled' : '' ?>>
          <option value="">Select Department</option>
          <?php if (!empty($depts)): ?>
            <option value="all" <?= $deptSel==='all'?'selected':'' ?>>All Departments</option>
            <?php foreach ($depts as $d): ?>
              <option value="<?= $d['dept_id'] ?>" <?= $deptSel==$d['dept_id']?'selected':'' ?>>
                <?= htmlspecialchars($d['dept_name']) ?>
                <?php if (isset($d['has_submissions'])): ?>
                  <?= $d['has_submissions'] ? '✓' : '⚠' ?>
                <?php endif; ?>
              </option>
            <?php endforeach; ?>
          <?php endif; ?>
        </select>
        <?php if (!$monthSel): ?>
          <small class="text-muted">Select a month first</small>
        <?php elseif (empty($depts)): ?>
          <small class="text-muted">No departments available for selected month</small>
        <?php endif; ?>
      </div>

      <div class="col-md-3">
        <label class="form-label">Employee (Evaluator) *</label>
        <select name="evaluator" class="form-select" onchange="updateFilters()" <?= !$deptSel ? 'disabled' : '' ?>>
          <option value="">Select Employee</option>
          <?php if (!empty($employees)): ?>
            <option value="all" <?= $evaluatorSel==='all'?'selected':'' ?>>All Employees</option>
            <?php foreach ($employees as $emp): ?>
              <option value="<?= $emp['user_id'] ?>" <?= $evaluatorSel==$emp['user_id']?'selected':'' ?>>
                <?= htmlspecialchars($emp['name']) ?>
                <?php if (isset($emp['dept_name'])): ?>
                  (<?= htmlspecialchars($emp['dept_name']) ?>)
                <?php endif; ?>
                <?= $emp['has_submitted'] ? ' ✓' : ' ⚠' ?>
              </option>
            <?php endforeach; ?>
          <?php endif; ?>
        </select>
        <?php if (!$deptSel): ?>
          <small class="text-muted">Select a department first</small>
        <?php elseif (empty($employees)): ?>
          <small class="text-muted">No employees available for selected criteria</small>
        <?php endif; ?>
      </div>

      <div class="col-md-3">
        <label class="form-label">Status *</label>
        <select name="status" class="form-select" onchange="updateFilters()" <?= !$evaluatorSel ? 'disabled' : '' ?>>
          <option value="">Select Status</option>
          <option value="submitted" <?= $statusSel==='submitted'?'selected':'' ?>>Submitted Only</option>
          <option value="missing" <?= $statusSel==='missing'?'selected':'' ?>>Missing Only</option>
          <option value="all" <?= $statusSel==='all'?'selected':'' ?>>All (Submitted + Missing)</option>
        </select>
        <?php if (!$evaluatorSel): ?>
          <small class="text-muted">Select an employee first</small>
        <?php endif; ?>
      </div>
    </div>

    <?php if (!empty($auditRows) || !empty($missingSubmissions)): ?>
      <div class="alert alert-info mb-3">
        <i class="fas fa-info-circle me-2"></i>
        Found <?= count($auditRows) ?> submitted evaluation(s) and <?= count($missingSubmissions) ?> missing submission(s).
      </div>
      
      <?php if (!empty($auditRows)): ?>
        <div class="mb-4">
          <h4 class="text-success mb-3">Submitted Evaluations</h4>
          <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle">
              <thead class="table-success">
                <tr>
                  <th>Month</th>
                  <th>Department</th>
                  <th>Evaluator</th>
                  <th>Evaluatee</th>
                  <th>Indicator</th>
                  <th>Rating</th>
                  <th>Comments</th>
                  <th>Date Submitted</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($auditRows as $r): ?>
                  <tr>
                    <td><?= htmlspecialchars($r['month']) ?></td>
                    <td><?= htmlspecialchars($r['dept_name']) ?></td>
                    <td class="submitted-employee"><?= htmlspecialchars($r['evaluator']) ?></td>
                    <td><?= htmlspecialchars($r['evaluatee']) ?></td>
                    <td><?= htmlspecialchars($r['indicator']) ?></td>
                    <td>
                      <span class="badge bg-<?= $r['rating'] >= 4 ? 'success' : ($r['rating'] >= 3 ? 'warning' : 'danger') ?>">
                        <?= $r['rating'] ?>
                      </span>
                    </td>
                    <td><?= htmlspecialchars($r['comments'] ?? 'No comments') ?></td>
                    <td><?= htmlspecialchars($r['date_submitted']) ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

      <?php if (!empty($missingSubmissions)): ?>
        <div class="mb-4">
          <h4 class="text-danger mb-3">Missing Submissions</h4>
          <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle">
              <thead class="table-danger">
                <tr>
                  <th>Month</th>
                  <th>Department</th>
                  <th>Employee (Should Evaluate)</th>
                  <th>Expected Evaluatees</th>
                  <th>Missing Count</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($missingSubmissions as $m): ?>
                  <tr>
                    <td><?= htmlspecialchars($m['month']) ?></td>
                    <td><?= htmlspecialchars($m['dept_name']) ?></td>
                    <td class="missing-employee"><?= htmlspecialchars($m['evaluator_name']) ?></td>
                    <td><?= htmlspecialchars($m['expected_evaluatees']) ?></td>
                    <td>
                      <span class="badge bg-danger">
                        <?= $m['missing_count'] ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>
    <?php elseif ($monthSel && $deptSel && $evaluatorSel && $statusSel): ?>
      <div class="alert alert-secondary text-center py-4">
        <i class="fas fa-search me-2"></i>
        No records found for the selected criteria. Try adjusting your filters.
      </div>
    <?php endif; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function updateFilters() {
  const month = document.querySelector('select[name="month"]').value;
  const dept = document.querySelector('select[name="dept"]').value;
  const evaluator = document.querySelector('select[name="evaluator"]').value;
  const status = document.querySelector('select[name="status"]').value;
  
  const params = new URLSearchParams();
  if (month) params.set('month', month);
  if (dept) params.set('dept', dept);
  if (evaluator) params.set('evaluator', evaluator);
  if (status) params.set('status', status);
  
  window.location.search = params.toString();
}
</script>
</body>
</html>