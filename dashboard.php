<?php
declare(strict_types=1);

/*
 |------------------------------------------------------------------
 |  dashboard.php — FULL OVERRIDE 24-Jul-2025
 |------------------------------------------------------------------
 |  Implements:
 |    • Role-aware views (admin / manager / employee)
 |    • Year / month filters with “All” options
 |    • Employee-only self-history & evaluation submissions
 |    • Manager: top-performing depts, dept employees, company employees
 |    • Admin: top dept, top company employee, top employee by dept
 |    • Summary cards + Chart.js visualisations
 */

require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/utils.php';
require_once __DIR__ . '/backend/user_controller.php';
require_once __DIR__ . '/backend/department_controller.php';
require_once __DIR__ . '/backend/report_controller.php';

use Backend\ReportRepository;
use Backend\DepartmentRepository;

secureSessionStart();
checkLogin();

const ROLE_ADMIN    = 1;
const ROLE_MANAGER  = 2;
const ROLE_EMPLOYEE = 3;

$user = getUser((int)($_SESSION['user_id'] ?? 0));
if (!$user) {
    logoutUser();
    redirect('login.php');
}

$roleId     = (int)$user['role_id'];
$deptId     = (int)($user['dept_id'] ?? 0);
$cycleMonth = date('Y-m-01');

global $pdo;
$reportRepo = new ReportRepository($pdo);
$deptRepo = new DepartmentRepository($pdo);


function safeRound($value, int $precision = 2): float
{
    return round((float)($value ?? 0), $precision);
}

function getAvailableMonthsGrouped(ReportRepository $repo): array
{
    $scoreMonths = $repo->getScoreMonths();
    $grouped     = [];

    foreach ($scoreMonths as $m) {
        $y = date('Y', strtotime($m));
        $grouped[$y][] = [
            'name'     => date('F', strtotime($m)),
            'value'    => date('Y-m-01', strtotime($m)),
            'has_data' => true,
        ];
    }

    $currentYear = date('Y');
    $grouped[$currentYear] ??= [];

    foreach ($grouped as $y => &$months) {
        $existing = array_column($months, 'value');
        for ($m = 1; $m <= 12; $m++) {
            $v = "$y-" . sprintf('%02d', $m) . '-01';
            if (!in_array($v, $existing, true)) {
                $months[] = [
                    'name'     => date('F', mktime(0, 0, 0, $m, 1)),
                    'value'    => $v,
                    'has_data' => false,
                ];
            }
        }
        usort($months, fn($a, $b) => strtotime($a['value']) <=> strtotime($b['value']));
    }
    krsort($grouped);
    return $grouped;
}

$availableMonths = getAvailableMonthsGrouped($reportRepo);
$selectedYear    = $_GET['year']  ?? date('Y');
$selectedMonth   = $_GET['month'] ?? date('m');
$selMonth        = "$selectedYear-" . sprintf('%02d', (int)$selectedMonth) . '-01';

$scoreMonths = $reportRepo->getScoreMonths();
if (!in_array($selMonth, $scoreMonths, true) && !empty($scoreMonths)) {
    $selMonth        = $scoreMonths[0];
    $selectedYear    = date('Y', strtotime($selMonth));
    $selectedMonth   = date('m', strtotime($selMonth));
}

$totalUsers     = $reportRepo->totalActiveUsers();
$totalDepts     = $reportRepo->totalDepartments();
$activeDeptsNow = $reportRepo->activeDeptCountForMonth($cycleMonth);
$pendingPlans   = 0;
$pendingActuals = 0;
$pendingEvals   = 0;

if ($roleId === ROLE_MANAGER) {
    $pendingPlans   = $reportRepo->pendingPlanEntries($deptId, $cycleMonth);
    $pendingActuals = $reportRepo->pendingActuals($deptId, $cycleMonth);
} elseif ($roleId === ROLE_EMPLOYEE) {
    $pendingEvals = $reportRepo->pendingEvaluations((int)$user['user_id'], $cycleMonth);
}

include __DIR__ . '/partials/navbar.php';
include __DIR__ . '/partials/intro_modal.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard – SK-PM</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" href="./assets/logo/sk-n.ico">
  <link href="https://fonts.googleapis.com/css2?family=Merriweather&family=Playfair+Display&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="./assets/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .month-option-disabled { color:#6c757d!important; font-style:italic }
    .month-option-enabled { font-weight:500 }
  </style>
</head>
<body class="bg-light font-serif">
<header class="container text-center my-4">
  <h1 class="display-5 fw-bold">Dashboard</h1>
  <p class="lead mb-0">Overview for <?= date('F Y', strtotime($selMonth)) ?></p>
</header>

<div class="container mb-5">
  <div class="row g-4">
    <div class="col-md-3">
      <div class="card shadow-sm text-center h-100"><div class="card-body">
        <h6 class="text-uppercase small">Total Users</h6>
        <p class="display-6"><?= $totalUsers ?></p>
      </div></div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm text-center h-100"><div class="card-body">
        <h6 class="text-uppercase small">Departments</h6>
        <p class="display-6"><?= $totalDepts ?></p>
      </div></div>
    </div>
    <?php if ($roleId === ROLE_ADMIN): ?>
      <div class="col-md-3">
        <div class="card shadow-sm text-center h-100"><div class="card-body">
          <h6 class="text-uppercase small">Active Depts<br>(<?= date('M') ?>)</h6>
          <p class="display-6"><?= $activeDeptsNow ?></p>
        </div></div>
      </div>
      <div class="col-md-3"></div>
    <?php elseif ($roleId === ROLE_MANAGER): ?>
      <div class="col-md-3">
        <div class="card shadow-sm text-center h-100"><div class="card-body">
          <h6 class="text-uppercase small">Pending Plans</h6>
          <p class="display-6"><?= $pendingPlans ?></p>
        </div></div>
      </div>
      <div class="col-md-3">
        <div class="card shadow-sm text-center h-100"><div class="card-body">
          <h6 class="text-uppercase small">Pending Actuals</h6>
          <p class="display-6"><?= $pendingActuals ?></p>
        </div></div>
      </div>
    <?php else: ?>
      <div class="col-md-3">
        <div class="card shadow-sm text-center h-100"><div class="card-body">
          <h6 class="text-uppercase small">Pending Evals</h6>
          <p class="display-6"><?= $pendingEvals ?></p>
        </div></div>
      </div>
      <div class="col-md-3"></div>
    <?php endif; ?>
  </div>
</div>

<div class="container pb-5">

<?php if ($roleId === ROLE_ADMIN): ?>
<?php
/* ── 1. normalise filters ───────────────────────────────────────────── */
$selYear   = $_GET['year']  ?? date('Y');
$selMonth  = $_GET['month'] ?? date('m');
$selDept   = $_GET['dept']  ?? 'all';
$wantYear  = ($selYear  === 'all');
$wantMonth = ($selMonth === 'all');

// Determine if we're showing year data or month data
$showYearData = $wantMonth; // If month is "all", show year data
$monthSQL = null;
$yearSQL = null;

if (!$wantMonth && !$wantYear) {
    // Specific month and year
    $monthSQL = sprintf('%04d-%02d-01', (int)$selYear, (int)$selMonth);
} elseif ($wantMonth && !$wantYear) {
    // All months for specific year
    $yearSQL = (int)$selYear;
}

/* ── 2. Get ALL department scores with filtering ─────────────────────── */
$deptScores = [];
if ($monthSQL) {
    // Specific month - get actual department scores
    $deptFilter = '';
    $deptParams = [':m' => $monthSQL];
    if ($selDept !== 'all') {
        $deptFilter = 'AND d.dept_id = :dept_filter';
        $deptParams[':dept_filter'] = (int)$selDept;
    }
    
    $allDepts = $pdo->prepare("
        SELECT d.dept_id, d.dept_name
        FROM departments d
        WHERE 1=1 $deptFilter
        ORDER BY d.dept_name
    ");
    $allDepts->execute($selDept !== 'all' ? [':dept_filter' => (int)$selDept] : []);
    $allDeptsData = $allDepts->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($allDeptsData as $dept) {
        $score = $reportRepo->deptPlanScore($dept['dept_id'], $monthSQL);
        if ($score > 100) $score = 100;
        
        // Check if department has any data for this month
        $hasData = $pdo->prepare("
            SELECT COUNT(*) FROM department_indicator_monthly 
            WHERE dept_id = :d AND month = :m
        ");
        $hasData->execute([':d' => $dept['dept_id'], ':m' => $monthSQL]);
        
        $deptScores[] = [
            'dept_id' => $dept['dept_id'],
            'dept_name' => $dept['dept_name'],
            'dept_score' => $score,
            'has_data' => $hasData->fetchColumn() > 0
        ];
    }
    usort($deptScores, fn($a, $b) => $b['dept_score'] <=> $a['dept_score']);
    
} elseif ($yearSQL) {
    // Year data - average department scores across all months in the year
    $deptFilter = '';
    $deptParams = [':year' => $yearSQL];
    if ($selDept !== 'all') {
        $deptFilter = 'AND d.dept_id = :dept_filter';
        $deptParams[':dept_filter'] = (int)$selDept;
    }
    
    $deptYearQuery = $pdo->prepare("
        SELECT d.dept_id, d.dept_name, 
               AVG(
                   COALESCE(
                       SUM(
                           CASE WHEN dim.audit_score IS NOT NULL
                               THEN (dim.audit_score / 5) * dim.weight
                               ELSE 0
                           END
                       ), 0
                   )
               ) AS avg_dept_score
        FROM departments d
        JOIN department_indicator_monthly dim ON d.dept_id = dim.dept_id
        WHERE YEAR(dim.month) = :year $deptFilter
        GROUP BY d.dept_id, d.dept_name, dim.month
        HAVING AVG(
            COALESCE(
                SUM(
                    CASE WHEN dim.audit_score IS NOT NULL
                        THEN (dim.audit_score / 5) * dim.weight
                        ELSE 0
                    END
                ), 0
            )
        ) IS NOT NULL
    ");
    $deptYearQuery->execute($deptParams);
    $yearDeptData = $deptYearQuery->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by department and calculate true average
    $deptYearScores = [];
    foreach ($yearDeptData as $row) {
        $deptId = $row['dept_id'];
        $deptName = $row['dept_name'];
        if (!isset($deptYearScores[$deptId])) {
            $deptYearScores[$deptId] = [
                'dept_name' => $deptName,
                'scores' => []
            ];
        }
        $score = min(100, $row['avg_dept_score']); // Cap at 100%
        $deptYearScores[$deptId]['scores'][] = $score;
    }
    
    foreach ($deptYearScores as $deptId => $data) {
        $avgScore = array_sum($data['scores']) / count($data['scores']);
        $deptScores[] = [
            'dept_id' => $deptId,
            'dept_name' => $data['dept_name'],
            'dept_score' => round($avgScore, 2),
            'has_data' => count($data['scores']) > 0
        ];
    }
    usort($deptScores, fn($a, $b) => $b['dept_score'] <=> $a['dept_score']);
}

/* ── 3. Get ALL employees with filtering ────────────────────────────── */
$allEmployees = [];

if ($monthSQL) {
    // Specific month - get all employees with scores, filtered by department
    $deptFilter = '';
    $deptParam = [];
    if ($selDept !== 'all') {
        $deptFilter = 'AND u.dept_id = :dept_filter';
        $deptParam[':dept_filter'] = (int)$selDept;
    }
    
    $allUsers = $pdo->prepare("
        SELECT u.user_id, u.name, u.dept_id, u.role_id, d.dept_name
        FROM users u
        JOIN departments d ON u.dept_id = d.dept_id
        WHERE u.active = 1 $deptFilter
        ORDER BY u.name
    ");
    $allUsers->execute($deptParam);
    $allUsersData = $allUsers->fetchAll(PDO::FETCH_ASSOC);
    
    $settingsRepo = new Backend\SettingsRepository($pdo);
    $deptWeightPerc = (int)($settingsRepo->getSetting('department_score_weight') ?? 70);
    $indWeightPerc = (int)($settingsRepo->getSetting('individual_score_weight') ?? 30);
    $deptWeight = $deptWeightPerc / 100.0;
    $indWeight = $indWeightPerc / 100.0;
    
    foreach ($allUsersData as $user) {
        // Get department score
        $deptScore = $reportRepo->deptPlanScore($user['dept_id'], $monthSQL);
        if ($deptScore > 100) $deptScore = 100;
        
        // Get individual performance
        $cond = ($user['role_id'] == 2) 
                ? "ind.category IN ('individual','manager')" 
                : "ind.category = 'individual'";
        
        $indRows = $pdo->prepare("
            SELECT ind.default_goal, AVG(ev.rating) AS avg_rating
            FROM individual_evaluations ev
            JOIN individual_indicators ind ON ev.indicator_id = ind.indicator_id
            WHERE ev.evaluatee_id = :uid AND ev.month = :m AND $cond
            GROUP BY ind.indicator_id
        ");
        $indRows->execute([':uid' => $user['user_id'], ':m' => $monthSQL]);
        $indData = $indRows->fetchAll(PDO::FETCH_ASSOC);
        
        $indPerf = 0.0;
        if (count($indData) > 0) {
            $totalPerc = 0.0;
            foreach ($indData as $r2) {
                $totalPerc += ($r2['avg_rating'] / $r2['default_goal']) * 100;
            }
            $indPerf = $totalPerc / count($indData);
        }
        if ($indPerf > 100) $indPerf = 100;
        
        // Calculate final score
        $finalScore = round($deptScore * $deptWeight + $indPerf * $indWeight, 2);
        if ($finalScore > 100) $finalScore = 100;
        
        // Include all users, even those without evaluation data (they get 0 score)
        $allEmployees[] = [
            'user_id' => $user['user_id'],
            'name' => $user['name'],
            'dept_name' => $user['dept_name'],
            'dept_id' => $user['dept_id'],
            'final_score' => $finalScore,
            'has_data' => count($indData) > 0
        ];
    }
    
    // Sort all employees by final score (descending)
    usort($allEmployees, fn($a, $b) => $b['final_score'] <=> $a['final_score']);
    
} elseif ($yearSQL) {
    // Year data - get average scores for the year, filtered by department
    $deptFilter = '';
    $deptParam = [':year' => $yearSQL];
    if ($selDept !== 'all') {
        $deptFilter = 'AND u.dept_id = :dept_filter';
        $deptParam[':dept_filter'] = (int)$selDept;
    }
    
    $yearEmployeeQuery = $pdo->prepare("
        SELECT u.user_id, u.name, d.dept_name, d.dept_id, AVG(s.final_score) as avg_final_score
        FROM scores s
        JOIN users u ON s.user_id = u.user_id
        JOIN departments d ON u.dept_id = d.dept_id
        WHERE YEAR(s.month) = :year AND u.active = 1 $deptFilter
        GROUP BY u.user_id
        HAVING COUNT(s.score_id) > 0
        ORDER BY avg_final_score DESC
    ");
    $yearEmployeeQuery->execute($deptParam);
    $yearEmployeeData = $yearEmployeeQuery->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($yearEmployeeData as $emp) {
        $finalScore = min(100, round($emp['avg_final_score'], 2));
        $allEmployees[] = [
            'user_id' => $emp['user_id'],
            'name' => $emp['name'],
            'dept_name' => $emp['dept_name'],
            'dept_id' => $emp['dept_id'],
            'final_score' => $finalScore,
            'has_data' => true
        ];
    }
}

// Helper functions
$rankCircle = fn(int $i) => ['①', '②', '③'][$i] ?? '';
$getRankDisplay = function(int $rank) {
    if ($rank <= 3) {
        return ['①', '②', '③'][$rank - 1];
    }
    return $rank;
};
?>

<!-- ── 3. filters form (year|month|dept) ────────────────────────────── -->
<form class="row mb-4" method="get">
  <div class="col-md-3">
    <label class="form-label">Year</label>
    <select name="year" class="form-select" onchange="updateMonthOptions()">
      <option value="all" <?= $wantYear ? 'selected' : '' ?>>All</option>
      <?php foreach (array_keys($availableMonths) as $y): ?>
        <option value="<?= $y ?>" <?= $y == $selYear ? 'selected' : '' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3">
    <label class="form-label">Month</label>
    <select id="month-select" name="month" class="form-select"></select>
  </div>
  <div class="col-md-3">
    <label class="form-label">Department</label>
    <select name="dept" class="form-select">
      <option value="all">All Departments</option>
      <?php foreach ($deptRepo->fetchAllDepartments() as $d): ?>
        <option value="<?= $d['dept_id'] ?>" <?= $selDept == $d['dept_id'] ? 'selected' : '' ?>><?= htmlspecialchars($d['dept_name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3 d-flex align-items-end">
    <button class="btn btn-primary w-100">Update</button>
  </div>
</form>

<?php if (empty($deptScores) && empty($allEmployees)): ?>
  <div class="alert alert-info text-center">No data available for the selected period.</div>
<?php else: ?>

  <!-- ALL Departments Ranked -->
  <div class="card shadow-sm mb-4">
    <div class="card-header">
      All Departments Ranked – 
      <?= $showYearData ? "Year $selYear" : date('F Y', strtotime($monthSQL)) ?>
      <?php if ($selDept !== 'all'): ?>
        <?php 
        $selectedDeptName = '';
        foreach ($deptRepo->fetchAllDepartments() as $d) {
            if ($d['dept_id'] == $selDept) {
                $selectedDeptName = $d['dept_name'];
                break;
            }
        }
        ?>
        (<?= htmlspecialchars($selectedDeptName) ?> Only)
      <?php endif; ?>
    </div>
    <div class="card-body">
      <?php if (empty($deptScores)): ?>
        <div class="alert alert-info text-center">No department data for this period.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Rank</th>
                <th>Department</th>
                <th class="text-center">Score</th>
                <th class="text-center">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($deptScores as $index => $dept): ?>
                <?php $rank = $index + 1; ?>
                <tr <?= $rank <= 3 ? 'class="table-success"' : '' ?>>
                  <td>
                    <strong><?= $getRankDisplay($rank) ?></strong>
                    <?php if ($rank <= 3): ?>
                      <small class="text-muted">(Top <?= $rank ?>)</small>
                    <?php endif; ?>
                  </td>
                  <td>
                    <strong><?= htmlspecialchars($dept['dept_name']) ?></strong>
                  </td>
                  <td class="text-center">
                    <strong><?= safeRound($dept['dept_score']) ?>%</strong>
                  </td>
                  <td class="text-center">
                    <?php if ($dept['has_data']): ?>
                      <span class="badge bg-success">Has Data</span>
                    <?php else: ?>
                      <span class="badge bg-secondary">No Data</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        
        <!-- Department Summary stats -->
        <?php if (count($deptScores) > 0): ?>
          <div class="row mt-3">
            <div class="col-md-4">
              <small class="text-muted">
                <strong>Total Departments:</strong> <?= count($deptScores) ?>
              </small>
            </div>
            <div class="col-md-4">
              <small class="text-muted">
                <strong>Average Score:</strong> 
                <?php 
                $validDepts = array_filter($deptScores, fn($d) => $d['has_data']);
                if (count($validDepts) > 0) {
                    echo safeRound(array_sum(array_column($validDepts, 'dept_score')) / count($validDepts));
                } else {
                    echo '0';
                }
                ?>%
              </small>
            </div>
            <div class="col-md-4">
              <small class="text-muted">
                <strong>Top Performer:</strong> 
                <?= safeRound($deptScores[0]['dept_score']) ?>%
              </small>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Top 3 Company-wide Employees -->
  <div class="card shadow-sm mb-4">
    <div class="card-header">
      Top 3 Employees (Company-wide) – 
      <?= $showYearData ? "Year $selYear" : date('F Y', strtotime($monthSQL)) ?>
    </div>
    <div class="card-body table-responsive">
      <?php if (empty($allEmployees)): ?>
        <div class="alert alert-info text-center">No employee data for this period.</div>
      <?php else: ?>
        <table class="table">
          <thead>
            <tr><th>Rank</th><th>Name</th><th>Department</th><th class="text-center">Score</th></tr>
          </thead>
          <tbody>
            <?php foreach (array_slice($allEmployees, 0, 3) as $i => $r): ?>
              <tr class="table-warning">
                <td><?= $rankCircle($i) ?></td>
                <td><?= htmlspecialchars($r['name']) ?></td>
                <td><?= htmlspecialchars($r['dept_name']) ?></td>
                <td class="text-center"><?= safeRound($r['final_score']) ?>%</td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- ALL Employees Ranked -->
  <div class="card shadow-sm mb-4">
    <div class="card-header">
      All Employees Ranked – 
      <?= $showYearData ? "Year $selYear" : date('F Y', strtotime($monthSQL)) ?>
      <?php if ($selDept !== 'all'): ?>
        <?php 
        $selectedDeptName = '';
        foreach ($deptRepo->fetchAllDepartments() as $d) {
            if ($d['dept_id'] == $selDept) {
                $selectedDeptName = $d['dept_name'];
                break;
            }
        }
        ?>
        (<?= htmlspecialchars($selectedDeptName) ?> Only)
      <?php endif; ?>
    </div>
    <div class="card-body">
      <?php if (empty($allEmployees)): ?>
        <div class="alert alert-info text-center">No employee data for this period.</div>
      <?php else: ?>
        <div class="table-responsive">
          <table class="table">
            <thead>
              <tr>
                <th>Rank</th>
                <th>Name</th>
                <th>Department</th>
                <th class="text-center">Score</th>
                <th class="text-center">Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($allEmployees as $index => $emp): ?>
                <?php $rank = $index + 1; ?>
                <tr <?= $rank <= 3 ? 'class="table-warning"' : '' ?>>
                  <td>
                    <strong><?= $getRankDisplay($rank) ?></strong>
                    <?php if ($rank <= 3): ?>
                      <small class="text-muted">(Top <?= $rank ?>)</small>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($emp['name']) ?></td>
                  <td><?= htmlspecialchars($emp['dept_name']) ?></td>
                  <td class="text-center">
                    <strong><?= safeRound($emp['final_score']) ?>%</strong>
                  </td>
                  <td class="text-center">
                    <?php if ($emp['has_data']): ?>
                      <span class="badge bg-success">Has Data</span>
                    <?php else: ?>
                      <span class="badge bg-secondary">No Data</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        
        <!-- Employee Summary stats -->
        <div class="row mt-3">
          <div class="col-md-4">
            <small class="text-muted">
              <strong>Total Employees:</strong> <?= count($allEmployees) ?>
            </small>
          </div>
          <div class="col-md-4">
            <small class="text-muted">
              <strong>Average Score:</strong> 
              <?php 
              $validEmps = array_filter($allEmployees, fn($e) => $e['has_data']);
              if (count($validEmps) > 0) {
                  echo safeRound(array_sum(array_column($validEmps, 'final_score')) / count($validEmps));
              } else {
                  echo '0';
              }
              ?>%
            </small>
          </div>
          <div class="col-md-4">
            <small class="text-muted">
              <strong>Top Performer:</strong> 
              <?= count($allEmployees) > 0 ? safeRound($allEmployees[0]['final_score']) : '0' ?>%
            </small>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </div>

<?php endif; /* data-available */ ?>

<script>
const monthsByYear = <?= json_encode($availableMonths) ?>;
function updateMonthOptions() {
  const y = document.querySelector('select[name="year"]');
  const m = document.getElementById('month-select');
  m.innerHTML = '';
  if (y.value === 'all') {
    m.disabled = true;
    m.add(new Option('All', 'all', true, true));
    return;
  }
  m.disabled = false;
  m.add(new Option('All', 'all'));
  (monthsByYear[y.value] || []).forEach(x => {
    const o = new Option(x.name, x.value.substr(5, 2));
    o.className = x.has_data ? 'month-option-enabled' : 'month-option-disabled';
    if (!x.has_data) o.text += ' (No Data)';
    m.add(o);
  });
  m.value = '<?= $selMonth ?>';
}
document.addEventListener('DOMContentLoaded', updateMonthOptions);
</script>
<?php elseif ($roleId === ROLE_MANAGER): ?>
    <?php
        /* ── 1. Manager Filter Processing ─────────────────────────────── */
        $selectedYear  = $_GET['year']  ?? date('Y');
        $selectedMonth = $_GET['month'] ?? date('m');
        $wantAllMonths = ($selectedMonth === 'all');
        
        // Determine time period for queries
        if ($wantAllMonths) {
            // Show year data
            $timeFilter = "YEAR(s.month) = :year";
            $timeParams = [':year' => (int)$selectedYear];
            $timeLabel = "Year $selectedYear";
        } else {
            // Show specific month data
            $selMonth = sprintf('%04d-%02d-01', (int)$selectedYear, (int)$selectedMonth);
            $timeFilter = "s.month = :month";
            $timeParams = [':month' => $selMonth];
            $timeLabel = date('F Y', strtotime($selMonth));
        }

        /* ── 2. Calculate Department Performance ──────────────────────── */
        if ($wantAllMonths) {
            // Year average for department
            $deptScoreQuery = $pdo->prepare("
                SELECT AVG(dept_score) as avg_dept_score
                FROM scores 
                WHERE dept_id = :dept_id AND YEAR(month) = :year
            ");
            $deptScoreQuery->execute([':dept_id' => $deptId, ':year' => (int)$selectedYear]);
            $deptScoreResult = $deptScoreQuery->fetch(PDO::FETCH_ASSOC);
            $deptScore = $deptScoreResult ? min(100, round($deptScoreResult['avg_dept_score'], 2)) : 0;
        } else {
            // Specific month department score
            $deptScore = $reportRepo->deptPlanScore($deptId, $selMonth);
            if ($deptScore > 100) $deptScore = 100;
        }

        /* ── 3. Get Team Members Performance ──────────────────────────── */
        $teamPerformance = [];
        if ($wantAllMonths) {
            // Year data - get average scores for team members
            $teamQuery = $pdo->prepare("
                SELECT u.user_id, u.name, u.role_id,
                       AVG(s.dept_score) as avg_dept_score,
                       AVG(s.individual_score) as avg_individual_score,
                       AVG(s.final_score) as avg_final_score,
                       COUNT(s.score_id) as data_points
                FROM users u
                LEFT JOIN scores s ON u.user_id = s.user_id AND YEAR(s.month) = :year
                WHERE u.dept_id = :dept_id AND u.active = 1 AND u.user_id != :manager_id
                GROUP BY u.user_id
                ORDER BY avg_final_score DESC
            ");
            $teamQuery->execute([
                ':dept_id' => $deptId, 
                ':year' => (int)$selectedYear,
                ':manager_id' => $user['user_id']
            ]);
            $teamData = $teamQuery->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($teamData as $member) {
                $teamPerformance[] = [
                    'user_id' => $member['user_id'],
                    'name' => $member['name'],
                    'dept_score' => $member['avg_dept_score'] ? min(100, round($member['avg_dept_score'], 2)) : 0,
                    'individual_score' => $member['avg_individual_score'] ? min(100, round($member['avg_individual_score'], 2)) : 0,
                    'final_score' => $member['avg_final_score'] ? min(100, round($member['avg_final_score'], 2)) : 0,
                    'has_data' => $member['data_points'] > 0
                ];
            }
        } else {
            // Specific month data
            $teamStats = $reportRepo->getIndividualDeptSummary($deptId, $selMonth);
            foreach ($teamStats as $member) {
                if ($member['user_id'] !== $user['user_id']) { // Exclude manager
                    $teamPerformance[] = [
                        'user_id' => $member['user_id'],
                        'name' => $member['name'],
                        'dept_score' => min(100, round($member['dept_score'], 2)),
                        'individual_score' => min(100, round($member['individual_score'], 2)),
                        'final_score' => min(100, round($member['final_score'], 2)),
                        'has_data' => $member['individual_score'] > 0
                    ];
                }
            }
        }

        /* ── 4. Get All Departments Performance (for comparison) ────── */
        $allDeptPerformance = [];
        if ($wantAllMonths) {
            // Year data for all departments
            $allDeptsQuery = $pdo->prepare("
                SELECT d.dept_id, d.dept_name, AVG(s.dept_score) as avg_dept_score
                FROM departments d
                LEFT JOIN scores s ON d.dept_id = s.dept_id AND YEAR(s.month) = :year
                GROUP BY d.dept_id
                HAVING AVG(s.dept_score) IS NOT NULL
                ORDER BY avg_dept_score DESC
            ");
            $allDeptsQuery->execute([':year' => (int)$selectedYear]);
            $allDeptsData = $allDeptsQuery->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($allDeptsData as $dept) {
                $allDeptPerformance[] = [
                    'dept_id' => $dept['dept_id'],
                    'dept_name' => $dept['dept_name'],
                    'dept_score' => min(100, round($dept['avg_dept_score'], 2)),
                    'is_current' => $dept['dept_id'] == $deptId
                ];
            }
        } else {
            // Specific month data for all departments
            $allDepts = $deptRepo->fetchAllDepartments();
            foreach ($allDepts as $dept) {
                $score = $reportRepo->deptPlanScore($dept['dept_id'], $selMonth);
                if ($score > 100) $score = 100;
                
                // Check if department has data
                $hasData = $pdo->prepare("
                    SELECT COUNT(*) FROM department_indicator_monthly 
                    WHERE dept_id = :d AND month = :m
                ");
                $hasData->execute([':d' => $dept['dept_id'], ':m' => $selMonth]);
                
                if ($hasData->fetchColumn() > 0) {
                    $allDeptPerformance[] = [
                        'dept_id' => $dept['dept_id'],
                        'dept_name' => $dept['dept_name'],
                        'dept_score' => $score,
                        'is_current' => $dept['dept_id'] == $deptId
                    ];
                }
            }
            usort($allDeptPerformance, fn($a, $b) => $b['dept_score'] <=> $a['dept_score']);
        }

        /* ── 5. Get Company-wide Top Performers ──────────────────────── */
        $companyTopPerformers = [];
        if ($wantAllMonths) {
            // Year data for all employees
            $companyQuery = $pdo->prepare("
                SELECT u.user_id, u.name, d.dept_name, AVG(s.final_score) as avg_final_score
                FROM users u
                JOIN departments d ON u.dept_id = d.dept_id
                LEFT JOIN scores s ON u.user_id = s.user_id AND YEAR(s.month) = :year
                WHERE u.active = 1
                GROUP BY u.user_id
                HAVING AVG(s.final_score) IS NOT NULL
                ORDER BY avg_final_score DESC
                LIMIT 20
            ");
            $companyQuery->execute([':year' => (int)$selectedYear]);
            $companyData = $companyQuery->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($companyData as $emp) {
                $companyTopPerformers[] = [
                    'user_id' => $emp['user_id'],
                    'name' => $emp['name'],
                    'dept_name' => $emp['dept_name'],
                    'final_score' => min(100, round($emp['avg_final_score'], 2)),
                    'is_team_member' => false // Will be updated below
                ];
            }
        } else {
            // Specific month data - get top performers using same logic as admin
            $allUsers = $pdo->prepare("
                SELECT u.user_id, u.name, u.dept_id, u.role_id, d.dept_name
                FROM users u
                JOIN departments d ON u.dept_id = d.dept_id
                WHERE u.active = 1
                ORDER BY u.name
            ");
            $allUsers->execute();
            $allUsersData = $allUsers->fetchAll(PDO::FETCH_ASSOC);
            
            $settingsRepo = new Backend\SettingsRepository($pdo);
            $deptWeightPerc = (int)($settingsRepo->getSetting('department_score_weight') ?? 70);
            $indWeightPerc = (int)($settingsRepo->getSetting('individual_score_weight') ?? 30);
            $deptWeight = $deptWeightPerc / 100.0;
            $indWeight = $indWeightPerc / 100.0;
            
            foreach ($allUsersData as $emp) {
                // Get department score
                $empDeptScore = $reportRepo->deptPlanScore($emp['dept_id'], $selMonth);
                if ($empDeptScore > 100) $empDeptScore = 100;
                
                // Get individual performance
                $cond = ($emp['role_id'] == 2) 
                        ? "ind.category IN ('individual','manager')" 
                        : "ind.category = 'individual'";
                
                $indRows = $pdo->prepare("
                    SELECT ind.default_goal, AVG(ev.rating) AS avg_rating
                    FROM individual_evaluations ev
                    JOIN individual_indicators ind ON ev.indicator_id = ind.indicator_id
                    WHERE ev.evaluatee_id = :uid AND ev.month = :m AND $cond
                    GROUP BY ind.indicator_id
                ");
                $indRows->execute([':uid' => $emp['user_id'], ':m' => $selMonth]);
                $indData = $indRows->fetchAll(PDO::FETCH_ASSOC);
                
                $indPerf = 0.0;
                if (count($indData) > 0) {
                    $totalPerc = 0.0;
                    foreach ($indData as $r2) {
                        $totalPerc += ($r2['avg_rating'] / $r2['default_goal']) * 100;
                    }
                    $indPerf = $totalPerc / count($indData);
                }
                if ($indPerf > 100) $indPerf = 100;
                
                // Calculate final score
                $finalScore = round($empDeptScore * $deptWeight + $indPerf * $indWeight, 2);
                if ($finalScore > 100) $finalScore = 100;
                
                // Only include users who have evaluation data
                if (count($indData) > 0) {
                    $companyTopPerformers[] = [
                        'user_id' => $emp['user_id'],
                        'name' => $emp['name'],
                        'dept_name' => $emp['dept_name'],
                        'final_score' => $finalScore,
                        'is_team_member' => ($emp['dept_id'] == $deptId && $emp['user_id'] != $user['user_id'])
                    ];
                }
            }
            
            // Sort and limit to top performers
            usort($companyTopPerformers, fn($a, $b) => $b['final_score'] <=> $a['final_score']);
            $companyTopPerformers = array_slice($companyTopPerformers, 0, 20);
        }

        // Mark team members in company list
        $teamMemberIds = array_column($teamPerformance, 'user_id');
        foreach ($companyTopPerformers as &$performer) {
            if (in_array($performer['user_id'], $teamMemberIds)) {
                $performer['is_team_member'] = true;
            }
        }
        unset($performer);

        // Get KPI contributions for chart (only for specific month)
        $kpiSlices = [];
        if (!$wantAllMonths) {
            $kpiSlices = $reportRepo->kpiContributions($deptId, $selMonth);
        }

        $deptInfo = $deptRepo->getDepartmentById($deptId);
        
        // Helper functions
        $getRankDisplay = function(int $rank) {
            if ($rank <= 3) {
                return ['①', '②', '③'][$rank - 1];
            }
            return $rank;
        };
    ?>

    <!-- Manager Filters -->
    <form method="get" class="row mb-4">
      <div class="col-md-3">
        <label for="year-select" class="form-label">Year</label>
        <select id="year-select" name="year" class="form-select" onchange="updateMonthOptions()">
          <?php foreach (array_keys($availableMonths) as $y): ?>
            <option value="<?= $y ?>" <?= $y == $selectedYear ? 'selected' : '' ?>><?= $y ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-3">
        <label for="month-select" class="form-label">Month</label>
        <select id="month-select" name="month" class="form-select"></select>
      </div>
      <div class="col-md-3 d-flex align-items-end">
        <button class="btn btn-primary" type="submit">Update</button>
      </div>
    </form>

    <!-- Key Metrics Cards -->
    <div class="row g-4 mb-4">
      <div class="col-lg-4">
        <div class="card h-100 text-center shadow-sm">
          <div class="card-body">
            <h6 class="text-uppercase small">Department Score</h6>
            <p class="display-5 mb-2"><?= safeRound($deptScore) ?>%</p>
            <small class="text-muted"><?= htmlspecialchars($deptInfo['dept_name'] ?? 'Your Department') ?></small>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="card h-100 text-center shadow-sm">
          <div class="card-body">
            <h6 class="text-uppercase small">Team Members</h6>
            <p class="display-5 mb-2"><?= count($teamPerformance) ?></p>
            <small class="text-muted">Direct Reports</small>
          </div>
        </div>
      </div>
      <div class="col-lg-4">
        <div class="card h-100 text-center shadow-sm">
          <div class="card-body">
            <h6 class="text-uppercase small">Period</h6>
            <p class="display-6 mb-2"><?= $timeLabel ?></p>
            <small class="text-muted">Performance Data</small>
          </div>
        </div>
      </div>
    </div>

    <!-- Charts Row (only for specific month) -->
    <?php if (!$wantAllMonths && !empty($kpiSlices)): ?>
    <div class="row g-4 mb-4">
      <div class="col-lg-6">
        <div class="card shadow-sm">
          <div class="card-header">KPI Contributions</div>
          <div class="card-body">
            <canvas id="chartKpi"></canvas>
          </div>
        </div>
      </div>
      <div class="col-lg-6">
        <div class="card shadow-sm">
          <div class="card-header">Team Performance</div>
          <div class="card-body">
            <canvas id="chartTeam"></canvas>
          </div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <!-- All Departments Ranked -->
    <div class="card shadow-sm mb-4">
      <div class="card-header">
        All Departments Ranked – <?= $timeLabel ?>
      </div>
      <div class="card-body">
        <?php if (empty($allDeptPerformance)): ?>
          <div class="alert alert-info text-center">No department data for this period.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Rank</th>
                  <th>Department</th>
                  <th class="text-center">Score</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($allDeptPerformance as $index => $dept): ?>
                  <?php $rank = $index + 1; ?>
                  <tr <?= $rank <= 3 ? 'class="table-success"' : '' ?> <?= $dept['is_current'] ? 'style="border-left: 4px solid #0d6efd;"' : '' ?>>
                    <td>
                      <strong><?= $getRankDisplay($rank) ?></strong>
                      <?php if ($rank <= 3): ?>
                        <small class="text-muted">(Top <?= $rank ?>)</small>
                      <?php endif; ?>
                      <?php if ($dept['is_current']): ?>
                        <span class="badge bg-primary ms-2">Your Dept</span>
                      <?php endif; ?>
                    </td>
                    <td>
                      <strong><?= htmlspecialchars($dept['dept_name']) ?></strong>
                    </td>
                    <td class="text-center">
                      <strong><?= safeRound($dept['dept_score']) ?>%</strong>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          
          <!-- Department Summary -->
          <div class="row mt-3">
            <div class="col-md-4">
              <small class="text-muted">
                <strong>Total Departments:</strong> <?= count($allDeptPerformance) ?>
              </small>
            </div>
            <div class="col-md-4">
              <small class="text-muted">
                <strong>Average Score:</strong> 
                <?= safeRound(array_sum(array_column($allDeptPerformance, 'dept_score')) / count($allDeptPerformance)) ?>%
              </small>
            </div>
            <div class="col-md-4">
              <small class="text-muted">
                <strong>Your Rank:</strong> 
                <?php 
                foreach ($allDeptPerformance as $index => $dept) {
                    if ($dept['is_current']) {
                        echo ($index + 1) . ' of ' . count($allDeptPerformance);
                        break;
                    }
                }
                ?>
              </small>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Your Team Performance -->
    <div class="card shadow-sm mb-4">
      <div class="card-header">
        Your Team Performance – <?= $timeLabel ?>
      </div>
      <div class="card-body">
        <?php if (empty($teamPerformance)): ?>
          <div class="alert alert-info text-center">No team member data for this period.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Rank</th>
                  <th>Name</th>
                  <th class="text-center">Dept Score</th>
                  <th class="text-center">Individual Score</th>
                  <th class="text-center">Final Score</th>
                  <th class="text-center">Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($teamPerformance as $index => $member): ?>
                  <?php $rank = $index + 1; ?>
                  <tr <?= $rank <= 3 ? 'class="table-warning"' : '' ?>>
                    <td>
                      <strong><?= $getRankDisplay($rank) ?></strong>
                      <?php if ($rank <= 3): ?>
                        <small class="text-muted">(Top <?= $rank ?>)</small>
                      <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($member['name']) ?></td>
                    <td class="text-center"><?= safeRound($member['dept_score']) ?>%</td>
                    <td class="text-center"><?= safeRound($member['individual_score']) ?>%</td>
                    <td class="text-center">
                      <strong><?= safeRound($member['final_score']) ?>%</strong>
                    </td>
                    <td class="text-center">
                      <?php if ($member['has_data']): ?>
                        <span class="badge bg-success">Has Data</span>
                      <?php else: ?>
                        <span class="badge bg-secondary">No Data</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          
          <!-- Team Summary -->
          <div class="row mt-3">
            <div class="col-md-4">
              <small class="text-muted">
                <strong>Team Size:</strong> <?= count($teamPerformance) ?>
              </small>
            </div>
            <div class="col-md-4">
              <small class="text-muted">
                <strong>Team Average:</strong> 
                <?php 
                $validMembers = array_filter($teamPerformance, fn($m) => $m['has_data']);
                if (count($validMembers) > 0) {
                    echo safeRound(array_sum(array_column($validMembers, 'final_score')) / count($validMembers));
                } else {
                    echo '0';
                }
                ?>%
              </small>
            </div>
            <div class="col-md-4">
              <small class="text-muted">
                <strong>Top Performer:</strong> 
                <?= count($teamPerformance) > 0 ? safeRound($teamPerformance[0]['final_score']) : '0' ?>%
              </small>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Company-wide Top Performers -->
    <div class="card shadow-sm mb-4">
      <div class="card-header">
        Company-wide Top Performers – <?= $timeLabel ?>
      </div>
      <div class="card-body">
        <?php if (empty($companyTopPerformers)): ?>
          <div class="alert alert-info text-center">No company performance data for this period.</div>
        <?php else: ?>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Rank</th>
                  <th>Name</th>
                  <th>Department</th>
                  <th class="text-center">Score</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($companyTopPerformers as $index => $performer): ?>
                  <?php $rank = $index + 1; ?>
                  <tr <?= $rank <= 3 ? 'class="table-success"' : '' ?> <?= $performer['is_team_member'] ? 'style="border-left: 4px solid #198754;"' : '' ?>>
                    <td>
                      <strong><?= $getRankDisplay($rank) ?></strong>
                      <?php if ($rank <= 3): ?>
                        <small class="text-muted">(Top <?= $rank ?>)</small>
                      <?php endif; ?>
                      <?php if ($performer['is_team_member']): ?>
                        <span class="badge bg-success ms-2">Your Team</span>
                      <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($performer['name']) ?></td>
                    <td><?= htmlspecialchars($performer['dept_name']) ?></td>
                    <td class="text-center">
                      <strong><?= safeRound($performer['final_score']) ?>%</strong>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          
          <!-- Company Summary -->
          <div class="row mt-3">
            <div class="col-md-4">
              <small class="text-muted">
                <strong>Showing Top:</strong> <?= count($companyTopPerformers) ?> employees
              </small>
            </div>
            <div class="col-md-4">
              <small class="text-muted">
                <strong>Your Team Members:</strong> 
                <?= count(array_filter($companyTopPerformers, fn($p) => $p['is_team_member'])) ?> in top performers
              </small>
            </div>
            <div class="col-md-4">
              <small class="text-muted">
                <strong>Company Best:</strong> 
                <?= safeRound($companyTopPerformers[0]['final_score']) ?>%
              </small>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <script>
      const availableMonths = <?= json_encode($availableMonths) ?>;
      const selectedMonth   = <?= json_encode($selectedMonth) ?>;
      
      function updateMonthOptions() {
        const y = document.getElementById('year-select'),
              m = document.getElementById('month-select');
        m.innerHTML = '';
        m.add(new Option('All', 'all'));
        (availableMonths[y.value] || []).forEach(mm => {
          const o = new Option(mm.name, mm.value.split('-')[1]);
          o.className = mm.has_data ? 'month-option-enabled' : 'month-option-disabled';
          if (!mm.has_data) o.text += ' (No Data)';
          m.add(o);
        });
        m.value = selectedMonth;
      }
      
      document.addEventListener('DOMContentLoaded', () => {
        updateMonthOptions();
        
        <?php if (!$wantAllMonths && !empty($kpiSlices)): ?>
        // KPI Pie Chart
        new Chart(document.getElementById('chartKpi'), {
          type: 'pie',
          data: {
            labels: <?= json_encode(array_column($kpiSlices, 'label')) ?>,
            datasets: [{
              data: <?= json_encode(array_map(fn($r) => safeRound($r['contribution']), $kpiSlices)) ?>,
              backgroundColor: [
                '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', 
                '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'
              ]
            }]
          },
          options: { 
            plugins: { 
              legend: { position: 'bottom' },
              tooltip: {
                callbacks: {
                  label: function(context) {
                    return context.label + ': ' + context.parsed + '%';
                  }
                }
              }
            } 
          }
        });
        
        // Team Performance Bar Chart
        new Chart(document.getElementById('chartTeam'), {
          type: 'bar',
          data: {
            labels: <?= json_encode(array_column($teamPerformance, 'name')) ?>,
            datasets: [{
              label: 'Final Score',
              data: <?= json_encode(array_map(fn($r) => safeRound($r['final_score']), $teamPerformance)) ?>,
              backgroundColor: '#36A2EB',
              borderColor: '#1E88E5',
              borderWidth: 1
            }]
          },
          options: {
            indexAxis: 'y',
            scales: { 
              x: { 
                beginAtZero: true, 
                max: 100,
                ticks: {
                  callback: function(value) {
                    return value + '%';
                  }
                }
              } 
            },
            plugins: { 
              legend: { display: false },
              tooltip: {
                callbacks: {
                  label: function(context) {
                    return context.parsed.x + '%';
                  }
                }
              }
            }
          }
        });
        <?php endif; ?>
      });
    </script>





<?php else: /* ROLE_EMPLOYEE */ ?>
<?php
$selectedYear  = $_GET['year']  ?? date('Y');
$selectedMonth = $_GET['month'] ?? date('m');

$where=[];
$params=[':uid'=>$user['user_id'],':d'=>$deptId];

if($selectedMonth==='all'){
    $where[]='YEAR(e.month)=:year';
    $params[':year']=(int)$selectedYear;
}else{
    $selMonth=sprintf('%04d-%02d-01',(int)$selectedYear,(int)$selectedMonth);
    $where[]='e.month=:month';
    $params[':month']=$selMonth;
}
$whereSql=$where?(' AND '.implode(' AND ',$where)):'';

$subSql="
 SELECT u.name,
        CASE WHEN u.user_id = (SELECT manager_id FROM departments WHERE dept_id=:d)
             THEN 'Manager' ELSE 'Coworker' END AS relation,
        ROUND( AVG( (e.rating / ii.default_goal) * 100 ), 2 ) AS pct
   FROM individual_evaluations e
   JOIN users               u  ON u.user_id = e.evaluatee_id
   JOIN individual_indicators ii ON ii.indicator_id = e.indicator_id
  WHERE e.evaluator_id = :uid {$whereSql}
  GROUP BY u.user_id
  ORDER BY pct DESC";
$stm=$pdo->prepare($subSql);$stm->execute($params);
$rows=$stm->fetchAll(PDO::FETCH_ASSOC);
?>
<!-- FILTER -->
<form class="row mb-4" method="get">
  <div class="col-md-3"><label class="form-label">Year</label>
    <select name="year" class="form-select" onchange="updateEmpMonths()">
      <option value="all"<?= $selectedYear==='all'?' selected':'' ?>>All</option>
      <?php foreach(array_keys($availableMonths) as $y): ?>
        <option value="<?= $y ?>"<?= $y==$selectedYear?' selected':'' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="col-md-3"><label class="form-label">Month</label>
    <select id="emp-month" name="month" class="form-select"></select>
  </div>
  <div class="col-md-3 d-flex align-items-end">
    <button class="btn btn-primary w-100">Update</button>
  </div>
</form>

<div class="card mb-4">
  <div class="card-header">My Evaluation Submissions (0–100 %)</div>
  <div class="card-body table-responsive">
    <?php if(!$rows): ?><div class="alert alert-info text-center">No data.</div>
    <?php else: ?>
      <table class="table"><thead><tr><th>Name</th><th>Relation</th><th class="text-center">Average %</th></tr></thead><tbody>
        <?php foreach($rows as $r): ?>
          <tr><td><?= htmlspecialchars($r['name']) ?></td>
              <td><?= htmlspecialchars($r['relation']) ?></td>
              <td class="text-center"><?= safeRound($r['pct']) ?></td></tr>
        <?php endforeach; ?>
      </tbody></table>
    <?php endif; ?>
    <p class="small text-muted mb-0">* Percentage is averaged across all indicators you scored for that person.</p>
  </div>
</div>

<script>
const monthsByYear=<?= json_encode($availableMonths) ?>;
function updateEmpMonths(){
  const y=document.querySelector('select[name="year"]');
  const m=document.getElementById('emp-month');m.innerHTML='';
  m.append(new Option('All','all'));
  (monthsByYear[y.value]||[]).forEach(x=>{
    let o=new Option(x.name,x.value.substr(5,2));
    if(!x.has_data){o.text+=' (no data)';o.className='text-muted';}
    m.append(o);
  });
  m.value=<?= json_encode($selectedMonth) ?>;
}
document.addEventListener('DOMContentLoaded',updateEmpMonths);
</script>

<?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
