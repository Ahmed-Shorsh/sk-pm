<?php

declare(strict_types=1);

require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/utils.php';
require_once __DIR__ . '/backend/user_controller.php';
require_once __DIR__ . '/backend/report_controller.php';
require_once __DIR__ . '/backend/department_controller.php';
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/backend/settings_controller.php';

use Dompdf\Dompdf;
use Backend\ReportRepository;
use Backend\DepartmentRepository;

secureSessionStart();
checkLogin();

const ROLE_ADMIN   = 1;
const ROLE_MANAGER = 2;

$roleId = (int)($_SESSION['role_id'] ?? 0);
if (!in_array($roleId, [ROLE_ADMIN, ROLE_MANAGER], true)) {
  header('HTTP/1.1 403 Forbidden');
  exit;
}

$settingsRepo   = new Backend\SettingsRepository($pdo);
$deptWeightPerc = (int)($settingsRepo->getSetting('department_score_weight') ?? 70);
$indWeightPerc  = (int)($settingsRepo->getSetting('individual_score_weight')  ?? 30);

$reportRepo     = new ReportRepository($pdo);
$deptRepo       = new DepartmentRepository($pdo);
$viewer         = getUser($_SESSION['user_id']);

$months         = $reportRepo->getScoreMonths();
$allDepts       = $deptRepo->fetchAllDepartments();
$departments    = $roleId === ROLE_ADMIN
  ? $allDepts
  : [$deptRepo->getDepartmentById((int)$viewer['dept_id']) ?? ['dept_id' => $viewer['dept_id'], 'dept_name' => '']];

$selectedDept   = (int)($_GET['dept_id']  ?? $departments[0]['dept_id']);
$selectedMonth  = $_GET['month']          ?? ($months[0] ?? date('Y-m-01'));
$selectedUser   = isset($_GET['user_id']) && $_GET['user_id'] !== ''
  ? (int)$_GET['user_id']
  : null;
$viewMode       = ($_GET['view'] ?? 'mgr') === 'dept' ? 'dept' : 'mgr';

if ($roleId === ROLE_ADMIN && $selectedUser && isset($_GET['export_pdf'])) {
  $sum  = $reportRepo->userCompositeReport($selectedUser, $selectedMonth);
  $indi = $reportRepo->individualIndicatorBreakdown($selectedUser, $selectedMonth);
  ob_start();
?>
  <html>

  <head>
    <style>
      body {
        font-family: DejaVu Sans, sans-serif;
      }

      h2,
      h3 {
        margin: .5em 0;
      }

      table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 1em;
      }

      th,
      td {
        border: 1px solid #666;
        padding: 6px;
        font-size: 12px;
      }
    </style>
  </head>

  <body>
    <h2>Performance Report — <?= htmlspecialchars($sum['name']) ?></h2>
    <p><strong>Month:</strong> <?= date('F Y', strtotime($selectedMonth)) ?></p>
    <h3>1. Department-Plan Completion</h3>
    <table>
      <thead>
        <tr>
          <th>Target</th>
          <th>Achieved</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>100 %</td>
          <td><?= $sum['dept_score'] ?> %</td>
        </tr>
      </tbody>
    </table>
    <h3>2. Individual Indicators</h3>
    <table>
      <thead>
        <tr>
          <th>Indicator</th>
          <th>Goal</th>
          <th>Avg</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($indi as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['name']) ?></td>
            <td><?= $r['default_goal'] ?></td>
            <td><?= $r['avg_rating'] ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <h3>3. Weighted Breakdown (<?= $deptWeightPerc ?>% / <?= $indWeightPerc ?>%)</h3>
    <table>
      <thead>
        <tr>
          <th>Block</th>
          <th>Weight</th>
          <th>Contrib.</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>Dept Plan</td>
          <td><?= $deptWeightPerc ?> %</td>
          <td><?= round($sum['dept_score'] * ($deptWeightPerc / 100), 2) ?> %</td>
        </tr>
        <tr>
          <td>Individual</td>
          <td><?= $indWeightPerc ?> %</td>
          <td><?= round($sum['individual_score'] * ($indWeightPerc / 100), 2) ?> %</td>
        </tr>
        <tr>
          <th colspan="2">Final</th>
          <th><?= $sum['final_score'] ?> %</th>
        </tr>
      </tbody>
    </table>
  </body>

  </html>
<?php
  $dom = new Dompdf();
  $dom->loadHtml(ob_get_clean(), 'UTF-8');
  $dom->setPaper('A4');
  $dom->render();
  $dom->stream(
    sprintf(
      'sk-pm_%s_%s.pdf',
      preg_replace('/\s+/', '_', $sum['name']),
      str_replace('-', '', $selectedMonth)
    ),
    ['Attachment' => true]
  );
  exit;
}

if ($selectedUser) {
  $u    = $reportRepo->userCompositeReport($selectedUser, $selectedMonth);
  $rows = [[
    'name' => $u['name'],
    'dept' => $u['dept_score'],
    'ind'  => $u['individual_score'],
    'mgr'  => '-',
    'fin'  => $u['final_score']
  ]];
} else {
  if ($viewMode === 'dept') {
    $tmp  = $reportRepo->individualDeptSummary($selectedDept, $selectedMonth);
    $rows = array_map(fn($r) => [
      'name' => $r['name'],
      'dept' => $r['dept_score'],
      'ind'  => $r['individual_score'],
      'mgr'  => '-',
      'fin'  => $r['final_score']
    ], $tmp);
  } else {
    $tmp  = $reportRepo->deptEvalSummary($selectedDept, $selectedMonth);
    $rows = array_map(fn($r) => [
      'name' => $r['name'],
      'dept' => '-',
      'ind'  => $r['ind_avg'],
      'mgr'  => $r['mgr_avg'],
      'fin'  => $r['final_score']
    ], $tmp);
  }
}

if ($selectedUser) {
  [$avgDept, $avgInd, $avgMgr, $avgFin] = [
    $rows[0]['dept'],
    $rows[0]['ind'],
    $rows[0]['mgr'],
    $rows[0]['fin']
  ];
} else {
  $avgInd  = round(array_sum(array_column($rows, 'ind'))  / max(count($rows), 1), 2);
  $avgMgr  = $viewMode === 'mgr'
    ? round(array_sum(array_column($rows, 'mgr')) / max(count($rows), 1), 2)
    : '-';
  $avgDept = $viewMode === 'dept'
    ? $reportRepo->deptPlanScore($selectedDept, $selectedMonth)
    : '-';
  $avgFin  = round(array_sum(array_column($rows, 'fin'))  / max(count($rows), 1), 2);
}

$jLabels = json_encode(array_column($rows, 'name'));
$jDept   = json_encode(array_map(fn($r) => is_numeric($r['dept']) ? $r['dept'] : null, $rows));
$jInd    = json_encode(array_column($rows, 'ind'));
$jMgr    = json_encode(array_map(fn($r) => is_numeric($r['mgr']) ? $r['mgr'] : null, $rows));
$jFin    = json_encode(array_column($rows, 'fin'));

include __DIR__ . '/partials/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <title>Reports · SK-PM</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Merriweather&family=Playfair+Display&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link rel="stylesheet" href="./assets/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-light font-serif">
  <div class="container py-4">
    <h1 class="mb-4">Performance Reports</h1>
    <form class="row g-3 align-items-end mb-4" method="get">
      <?php if ($roleId === ROLE_ADMIN): ?>
        <div class="col-md-3">
          <label class="form-label">Department</label>
          <select name="dept_id" class="form-select" onchange="this.form.submit()">
            <?php foreach ($departments as $d): ?>
              <option value="<?= $d['dept_id'] ?>" <?= $d['dept_id'] === $selectedDept ? 'selected' : '' ?>>
                <?= htmlspecialchars($d['dept_name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <div class="col-md-3">
        <label class="form-label">Month</label>
        <select name="month" class="form-select" onchange="this.form.submit()">
          <?php foreach ($months as $m): ?>
            <option value="<?= $m ?>" <?= $m === $selectedMonth ? 'selected' : '' ?>>
              <?= date('F Y', strtotime($m)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">User</label>
        <select name="user_id" class="form-select" onchange="this.form.submit()">
          <option value="">All</option>
          <?php foreach (($deptRepo->fetchDepartmentMembers($selectedDept) ?? []) as $mem): ?>
            <option value="<?= $mem['user_id'] ?>" <?= $mem['user_id'] === $selectedUser ? 'selected' : '' ?>>
              <?= htmlspecialchars($mem['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">View</label>
        <select name="view" class="form-select" onchange="this.form.submit()">
          <option value="mgr" <?= $viewMode === 'mgr'  ? 'selected' : '' ?>>Manager</option>
          <option value="dept" <?= $viewMode === 'dept' ? 'selected' : '' ?>>Department</option>
        </select>
      </div>
      <?php if ($roleId === ROLE_ADMIN && $selectedUser): ?>
        <div class="col-auto">
          <button class="btn btn-secondary" name="export_pdf" value="1">Export PDF</button>
        </div>
      <?php endif; ?>
    </form>

    <div class="row mb-4">
      <div class="col-md-3">
        <div class="card text-center shadow-sm">
          <div class="card-body">
            <h6>Dept Score</h6>
            <p class="display-6"><?= is_numeric($avgDept) ? $avgDept . '%' : '-' ?></p>
          </div>
        </div>
      </div>
      <div class="col-md-3">
        <div class="card text-center shadow-sm">
          <div class="card-body">
            <h6>Avg Individual</h6>
            <p class="display-6"><?= $avgInd ?>%</p>
          </div>
        </div>
      </div>
      <?php if ($viewMode === 'mgr'): ?>
        <div class="col-md-3">
          <div class="card text-center shadow-sm">
            <div class="card-body">
              <h6>Avg Manager</h6>
              <p class="display-6"><?= is_numeric($avgMgr) ? $avgMgr . '%' : '-' ?></p>
            </div>
          </div>
        </div>
      <?php endif; ?>
      <div class="col-md-3">
        <div class="card text-center shadow-sm">
          <div class="card-body">
            <h6>Avg Final</h6>
            <p class="display-6"><?= $avgFin ?>%</p>
          </div>
        </div>
      </div>
    </div>

    <div class="row mb-5">
      <div class="col-lg-6 mb-4">
        <div class="card shadow-sm">
          <div class="card-header">Comparison</div>
          <div class="card-body"><canvas id="cmpChart"></canvas></div>
        </div>
      </div>
      <div class="col-lg-6 mb-4">
        <div class="card shadow-sm">
          <div class="card-header">Final Scores</div>
          <div class="card-body"><canvas id="finChart"></canvas></div>
        </div>
      </div>
    </div>

    <div class="table-responsive mb-5">
      <table class="table table-striped table-bordered align-middle">
        <thead class="table-dark">
          <tr>
            <th>Name</th>
            <th>Dept</th>
            <th>Ind</th>
            <?php if ($viewMode === 'mgr'): ?><th>Mgr</th><?php endif; ?>
            <th>Final</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= htmlspecialchars($r['name']) ?></td>
              <td><?= is_numeric($r['dept']) ? $r['dept'] . '%' : '-' ?></td>
              <td><?= $r['ind'] ?>%</td>
              <?php if ($viewMode === 'mgr'): ?>
                <td><?= is_numeric($r['mgr']) ? $r['mgr'] . '%' : '-' ?></td>
              <?php endif; ?>
              <td><?= $r['fin'] ?>%</td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    const labels = <?= $jLabels ?>;
    const dataDept = <?= $jDept ?>;
    const dataInd = <?= $jInd ?>;
    const dataMgr = <?= $jMgr ?>;
    const dataFin = <?= $jFin ?>;

    new Chart(document.getElementById('cmpChart'), {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
            label: 'Dept',
            data: dataDept,
            borderWidth: 1
          },
          {
            label: 'Ind',
            data: dataInd,
            borderWidth: 1
          },
          <?php if ($viewMode === 'mgr'): ?> {
              label: 'Mgr',
              data: dataMgr,
              borderWidth: 1
            }
          <?php endif; ?>
        ]
      },
      options: {
        scales: {
          y: {
            beginAtZero: true,
            max: 100
          }
        }
      }
    });

    new Chart(document.getElementById('finChart'), {
      type: 'line',
      data: {
        labels: labels,
        datasets: [{
          label: 'Final',
          data: dataFin,
          fill: true,
          tension: 0.3,
          borderWidth: 2
        }]
      },
      options: {
        scales: {
          y: {
            beginAtZero: true,
            max: 100
          }
        }
      }
    });
  </script>
</body>

</html>