<?php
require_once __DIR__.'/backend/auth.php';
require_once __DIR__.'/backend/utils.php';
require_once __DIR__.'/backend/department_audit_repo.php';

secureSessionStart();
checkLogin();
if (($_SESSION['role_id']??0)!==1){
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

$yearSel = (int)($_GET['year'] ?? date('Y'));
$monthSel = $_GET['month'] ?? '';
$deptSel  = $_GET['dept']  ?? '';
$viewType = $_GET['view']  ?? 'summary';

use Backend\DepartmentAuditRepository;
$repo   = new DepartmentAuditRepository($pdo);
$years  = $repo->availableYears();
$months = $repo->availableMonthsForYear($yearSel);

if ($monthSel){
    $depts = $monthSel==='all'
        ? $repo->getDepartmentsByYear($yearSel)
        : $repo->getDepartmentsByMonth($monthSel);
}else{
    $depts = $repo->allDepartments();
}

$evaluations   = [];
$summaries     = [];
$incompleteData= [];
$errorMessage  = '';

try{
    if ($monthSel && $deptSel){
        $evaluations = $repo->getDepartmentEvaluations(
            $monthSel==='all'?null:$monthSel,
            $deptSel==='all'?null:(int)$deptSel,
            $yearSel
        );
        $summaries = $repo->getDepartmentSummary(
            $monthSel==='all'?null:$monthSel,
            $deptSel==='all'?null:(int)$deptSel,
            $yearSel
        );
        if ($viewType==='incomplete'){
            $incompleteData = $repo->getIncompleteDepartmentPlans(
                $monthSel==='all'?null:$monthSel,
                $deptSel==='all'?null:(int)$deptSel,
                $yearSel
            );
        }
    }elseif($monthSel || $deptSel){
        $errorMessage = !$monthSel
            ? 'Please select a month to continue filtering.'
            : 'Please select a department to view results.';
    }
}catch(\Throwable $e){
    $errorMessage='An error occurred while loading the department audit data. Please try again.';
    error_log('Department audit error: '.$e->getMessage());
}

include __DIR__.'/partials/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Department Audit – SK-PM</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Merriweather&family=Playfair+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./assets/css/style.css">
  <style>
    .achievement-achieved{color:#198754;font-weight:600}
    .achievement-partial{color:#fd7e14;font-weight:600}
    .achievement-not-achieved{color:#dc3545;font-weight:600}
    .achievement-pending{color:#6c757d;font-weight:600}
    .progress-bar-achieved{background-color:#198754}
    .progress-bar-partial{background-color:#fd7e14}
    .progress-bar-not-achieved{background-color:#dc3545}
    .summary-card{border-left:4px solid #0d6efd;background:linear-gradient(135deg,#f8f9fa 0%,#e9ecef 100%)}
    .incomplete-indicator{color:#dc3545;font-weight:500}
  </style>
</head>
<body class="bg-light font-serif">
<div class="container py-4">
  <h1 class="mb-4">Department Performance Audit</h1>
  <?php if($errorMessage):?>
    <div class="alert alert-warning mb-4"><?=$errorMessage?></div>
  <?php endif;?>
  <div class="data-container">
    <div class="row g-3 mb-4">
      <div class="col-md-3">
        <label class="form-label">Year *</label>
        <select name="year" class="form-select" onchange="updateFilters()">
          <?php foreach($years as $y):?>
            <option value="<?=$y?>" <?=$y==$yearSel?'selected':''?>><?=$y?></option>
          <?php endforeach;?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Month *</label>
        <select name="month" class="form-select" onchange="updateFilters()">
          <option value="">Select Month</option>
          <?php if($months):?>
            <option value="all" <?=$monthSel==='all'?'selected':''?>>All Months</option>
            <?php foreach($months as $m):?>
              <option value="<?=$m?>" <?=$m===$monthSel?'selected':''?>><?=date('F Y',strtotime("$m-01"))?></option>
            <?php endforeach;?>
          <?php endif;?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Department *</label>
        <select name="dept" class="form-select" onchange="updateFilters()" <?=!$monthSel?'disabled':''?>>
          <option value="">Select Department</option>
          <?php if($depts):?>
            <option value="all" <?=$deptSel==='all'?'selected':''?>>All Departments</option>
            <?php foreach($depts as $d):?>
              <option value="<?=$d['dept_id']?>" <?=$deptSel==$d['dept_id']?'selected':''?>>
                <?=htmlspecialchars($d['dept_name'])?>
                <?php if(isset($d['completed_count'],$d['indicator_count'])):?>
                  (<?=$d['completed_count']?>/<?=$d['indicator_count']?> completed)
                <?php endif;?>
              </option>
            <?php endforeach;?>
          <?php endif;?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">View Type</label>
        <select name="view" class="form-select" onchange="updateFilters()" <?=!$deptSel?'disabled':''?>>
          <option value="summary" <?=$viewType==='summary'?'selected':''?>>Summary &amp; Details</option>
          <option value="incomplete" <?=$viewType==='incomplete'?'selected':''?>>Incomplete Plans Only</option>
        </select>
      </div>
    </div>

    <?php if (!empty($summaries) || !empty($incompleteData)): ?>
      <div class="alert alert-info mb-4">
        <i class="fas fa-info-circle me-2"></i>
        <?php if ($viewType === 'incomplete'): ?>
          Found <?= count($incompleteData) ?> department(s) with incomplete indicator plans.
        <?php else: ?>
          Found <?= count($summaries) ?> department performance summary(ies) and <?= count($evaluations) ?> detailed indicator evaluation(s).
        <?php endif; ?>
      </div>
      
      <?php if ($viewType === 'incomplete' && !empty($incompleteData)): ?>
        <div class="mb-4">
          <h4 class="text-danger mb-3">Incomplete Department Plans</h4>
          <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle">
              <thead class="table-danger">
                <tr>
                  <th>Month</th>
                  <th>Department</th>
                  <th>Total Indicators</th>
                  <th>Incomplete Count</th>
                  <th>Incomplete Indicators</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($incompleteData as $data): ?>
                  <tr>
                    <td><?= date('F Y', strtotime($data['month'] . '-01')) ?></td>
                    <td><?= htmlspecialchars($data['dept_name']) ?></td>
                    <td><?= $data['total_indicators'] ?></td>
                    <td>
                      <span class="badge bg-danger">
                        <?= $data['incomplete_indicators'] ?>
                      </span>
                    </td>
                    <td>
                      <span class="incomplete-indicator">
                        <?= htmlspecialchars($data['incomplete_indicator_names']) ?>
                      </span>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($viewType === 'summary' && !empty($summaries)): ?>
        <div class="mb-4">
          <h4 class="text-primary mb-3">Performance Summary</h4>
          <div class="row">
            <?php foreach ($summaries as $summary): ?>
              <div class="col-md-6 mb-3">
                <div class="card summary-card">
                  <div class="card-body">
                    <h5 class="card-title"><?= htmlspecialchars($summary['dept_name']) ?></h5>
                    <p class="card-text text-muted mb-2"><?= date('F Y', strtotime($summary['month'] . '-01')) ?></p>
                    
                    <div class="row text-center mb-3">
                      <div class="col">
                        <div class="h4 text-success"><?= $summary['achieved_indicators'] ?></div>
                        <small class="text-muted">Achieved</small>
                      </div>
                      <div class="col">
                        <div class="h4 text-warning"><?= $summary['partially_achieved_indicators'] ?></div>
                        <small class="text-muted">Partial</small>
                      </div>
                      <div class="col">
                        <div class="h4 text-danger"><?= $summary['not_achieved_indicators'] ?></div>
                        <small class="text-muted">Not Achieved</small>
                      </div>
                      <div class="col">
                        <div class="h4 text-secondary"><?= $summary['pending_indicators'] ?></div>
                        <small class="text-muted">Pending</small>
                      </div>
                    </div>
                    
                    <div class="progress mb-2" style="height: 8px;">
                      <?php 
                      $achievedPct = ($summary['achieved_indicators'] / $summary['total_indicators']) * 100;
                      $partialPct = ($summary['partially_achieved_indicators'] / $summary['total_indicators']) * 100;
                      $notAchievedPct = ($summary['not_achieved_indicators'] / $summary['total_indicators']) * 100;
                      $pendingPct = ($summary['pending_indicators'] / $summary['total_indicators']) * 100;
                      ?>
                      <div class="progress-bar progress-bar-achieved" style="width: <?= $achievedPct ?>%"></div>
                      <div class="progress-bar progress-bar-partial" style="width: <?= $partialPct ?>%"></div>
                      <div class="progress-bar progress-bar-not-achieved" style="width: <?= $notAchievedPct ?>%"></div>
                      <div class="progress-bar bg-secondary" style="width: <?= $pendingPct ?>%"></div>
                    </div>
                    
                    <div class="d-flex justify-content-between">
                      <small class="text-muted">Weighted Achievement: <strong><?= $summary['weighted_achievement_percentage'] ?? 0 ?>%</strong></small>
                      <small class="text-muted">Completion: <strong><?= $summary['completed_indicators'] ?>/<?= $summary['total_indicators'] ?></strong></small>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($viewType === 'summary' && !empty($evaluations)): ?>
        <div class="mb-4">
          <h4 class="text-info mb-3">Detailed Indicator Evaluations</h4>
          <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle">
              <thead class="table-info">
                <tr>
                  <th>Month</th>
                  <th>Department</th>
                  <th>Indicator</th>
                  <th>Target</th>
                  <th>Actual</th>
                  <th>Achievement</th>
                  <th>Status</th>
                  <th>Weight</th>
                  <th>Notes</th>
                  <th>Created By</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($evaluations as $eval): ?>
                  <tr>
                    <td><?= date('F Y', strtotime($eval['month'] . '-01')) ?></td>
                    <td><?= htmlspecialchars($eval['dept_name']) ?></td>
                    <td>
                      <?= htmlspecialchars($eval['indicator_name']) ?>
                      <?php if ($eval['is_custom']): ?>
                        <span class="badge bg-secondary ms-1">Custom</span>
                      <?php endif; ?>
                      <?php if ($eval['indicator_description']): ?>
                        <small class="text-muted d-block"><?= htmlspecialchars($eval['indicator_description']) ?></small>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?= number_format($eval['target_value'], 2) ?>
                      <?php if ($eval['unit_of_goal']): ?>
                        <small class="text-muted"><?= htmlspecialchars($eval['unit_of_goal']) ?></small>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?= $eval['actual_value'] !== null ? number_format($eval['actual_value'], 2) : 'N/A' ?>
                      <?php if ($eval['actual_value'] !== null && $eval['unit']): ?>
                        <small class="text-muted"><?= htmlspecialchars($eval['unit']) ?></small>
                      <?php endif; ?>
                    </td>
                    <td>
                      <?php if ($eval['achievement_percentage'] > 0): ?>
                        <div class="progress" style="height: 20px;">
                          <div class="progress-bar <?= $eval['achievement_percentage'] >= 100 ? 'progress-bar-achieved' : ($eval['achievement_percentage'] >= 80 ? 'progress-bar-partial' : 'progress-bar-not-achieved') ?>" 
                               style="width: <?= min($eval['achievement_percentage'], 100) ?>%">
                            <?= $eval['achievement_percentage'] ?>%
                          </div>
                        </div>
                      <?php else: ?>
                        <span class="text-muted">N/A</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <span class="achievement-<?= strtolower(str_replace(' ', '-', $eval['achievement_status'])) ?>">
                        <?= htmlspecialchars($eval['achievement_status']) ?>
                      </span>
                    </td>
                    <td>
                      <span class="badge bg-info"><?= $eval['weight'] ?>%</span>
                    </td>
                    <td><?= htmlspecialchars($eval['notes'] ?? 'No notes') ?></td>
                    <td>
                      <?= htmlspecialchars($eval['created_by_name'] ?? 'Unknown') ?>
                      <?php if ($eval['created_date']): ?>
                        <small class="text-muted d-block"><?= date('M j, Y', strtotime($eval['created_date'])) ?></small>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>
    <?php elseif ($monthSel && $deptSel): ?>
      <div class="alert alert-secondary text-center py-4">
        <i class="fas fa-search me-2"></i>
        No department evaluation records found for the selected criteria. Try adjusting your filters.
      </div>
    <?php endif; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function updateFilters(){
  const year=document.querySelector('select[name="year"]').value;
  const month=document.querySelector('select[name="month"]').value;
  const dept=document.querySelector('select[name="dept"]').value;
  const view=document.querySelector('select[name="view"]').value;
  const p=new URLSearchParams;
  if(year)p.set('year',year);
  if(month)p.set('month',month);
  if(dept)p.set('dept',dept);
  if(view)p.set('view',view);
  window.location.search=p.toString();
}
</script>
</body>
</html>