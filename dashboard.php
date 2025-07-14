<?php

declare(strict_types=1);

/* 1 ── Core includes  ─────────────────────────────────────────────────── */
require_once __DIR__ . '/backend/auth.php';            // login helpers
require_once __DIR__ . '/backend/utils.php';           // redirect(), etc.
require_once __DIR__ . '/backend/user_controller.php'; // getUser()
require_once __DIR__ . '/backend/report_controller.php';

use Backend\ReportRepository;

/* 2 ── Secure the session  ───────────────────────────────────────────── */
secureSessionStart();
checkLogin();                       // aborts + redirects if not auth’d

/* 3 ── Role constants & logged-in user  ──────────────────────────────── */
const ROLE_ADMIN    = 1;
const ROLE_MANAGER  = 2;
const ROLE_EMPLOYEE = 3;

$user = getUser($_SESSION['user_id'] ?? 0);
if (!$user) {          // failsafe
    logoutUser();
    redirect('login.php');
}

$roleId     = (int)$user['role_id'];
$deptId     = (int)($user['dept_id'] ?? 0);
$cycleMonth = date('Y-m-01');       // performance cycle key (1st of month)

/* 4 ── Repository bootstrap  ─────────────────────────────────────────── */
global $pdo;                        // provided by backend/db.php
$reportRepo = new ReportRepository($pdo);

/* 5 ── “Summary-card” metrics (shown to every role)  ─────────────────── */
$totalUsers     = $reportRepo->totalActiveUsers();
$totalDepts     = $reportRepo->totalDepartments();
$activeDeptsNow = $reportRepo->activeDeptCountForMonth($cycleMonth);

/* role-specific “pending” counters */
$pendingPlans   = 0;
$pendingActuals = 0;
$pendingEvals   = 0;

if ($roleId === ROLE_MANAGER) {
    $pendingPlans   = $reportRepo->pendingPlanEntries($deptId, $cycleMonth);
    $pendingActuals = $reportRepo->pendingActuals($deptId, $cycleMonth);
} elseif ($roleId === ROLE_EMPLOYEE) {
    $pendingEvals   = $reportRepo->pendingEvaluations(
                        $user['user_id'],
                        $cycleMonth
                    );
}

include __DIR__ . '/partials/navbar.php';
include __DIR__ . '/partials/intro_modal.php';   
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Dashboard – SK-PM</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="icon" href="./assets/logo/sk-n.ico">
  <link href="https://fonts.googleapis.com/css2?family=Merriweather&family=Playfair+Display&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link rel="stylesheet" href="./assets/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="bg-light font-serif">

<header class="container text-center my-4">
  <h1 class="display-5 fw-bold">Dashboard</h1>
  <p class="lead mb-0">Overview for <?= date('F Y', strtotime($cycleMonth)) ?></p>
</header>

<!-- ─────────── SUMMARY CARDS (row 1) ─────────── -->
<div class="container mb-5">
  <div class="row g-4">
    <!-- total users -->
    <div class="col-md-3">
      <div class="card shadow-sm text-center h-100">
        <div class="card-body">
          <h6 class="text-uppercase small">Total Users</h6>
          <p class="display-6"><?= $totalUsers ?></p>
        </div>
      </div>
    </div>
    <!-- total depts -->
    <div class="col-md-3">
      <div class="card shadow-sm text-center h-100">
        <div class="card-body">
          <h6 class="text-uppercase small">Departments</h6>
          <p class="display-6"><?= $totalDepts ?></p>
        </div>
      </div>
    </div>

    <?php if ($roleId === ROLE_ADMIN): ?>
      <!-- active depts this month -->
      <div class="col-md-3">
        <div class="card shadow-sm text-center h-100">
          <div class="card-body">
            <h6 class="text-uppercase small">Active Depts (<?= date('M') ?>)</h6>
            <p class="display-6"><?= $activeDeptsNow ?></p>
          </div>
        </div>
      </div>
    <?php elseif ($roleId === ROLE_MANAGER): ?>
      <!-- pending plans -->
      <div class="col-md-3">
        <div class="card shadow-sm text-center h-100">
          <div class="card-body">
            <h6 class="text-uppercase small">Pending Plans</h6>
            <p class="display-6"><?= $pendingPlans ?></p>
          </div>
        </div>
      </div>
      <!-- pending actuals -->
      <div class="col-md-3">
        <div class="card shadow-sm text-center h-100">
          <div class="card-body">
            <h6 class="text-uppercase small">Pending Actuals</h6>
            <p class="display-6"><?= $pendingActuals ?></p>
          </div>
        </div>
      </div>
    <?php else: ?>
      <!-- pending evaluations -->
      <div class="col-md-3">
        <div class="card shadow-sm text-center h-100">
          <div class="card-body">
            <h6 class="text-uppercase small">Pending Evaluations</h6>
            <p class="display-6"><?= $pendingEvals ?></p>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>

<!-- ─────────── ROLE-SPECIFIC BLOCKS ─────────── -->
<div class="container pb-5">

<?php
/* ════════════════════════════════════════════
 * 6A. ADMIN DASHBOARD
 * ════════════════════════════════════════════ */
if ($roleId === ROLE_ADMIN):

    $scoreMonths = $reportRepo->getScoreMonths();                 // DESC
    $selMonth    = $_GET['month'] ?? ($scoreMonths[0] ?? $cycleMonth);

    $deptAverages = $reportRepo->deptAverages($selMonth);
    $orgTrend     = $reportRepo->organisationTrend(6);         // 6-month line

?>
  <!-- Month selector -->
  <form method="get" class="row mb-4">
    <div class="col-md-4">
      <label class="form-label">Select Month</label>
      <select name="month" class="form-select" onchange="this.form.submit()">
        <?php foreach ($scoreMonths as $m): ?>
          <option value="<?= $m ?>" <?= $m === $selMonth ? 'selected' : '' ?>>
            <?= date('F Y', strtotime($m)) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
  </form>

  <!-- Charts -->
  <div class="row g-4">
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header fw-semibold">
          Dept Average – <?= date('F Y', strtotime($selMonth)) ?>
        </div>
        <div class="card-body">
          <canvas id="chartDeptAvg"></canvas>
        </div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header fw-semibold">Organisation Trend (6 months)</div>
        <div class="card-body">
          <canvas id="chartOrgTrend"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- initialise charts -->
  <script>
  new Chart(document.getElementById('chartDeptAvg'),{
    type:'bar',
    data:{
      labels: <?= json_encode(array_column($deptAverages,'dept_name')) ?>,
      datasets:[{ label:'Average',
                  data: <?= json_encode(array_map(fn($r)=>round($r['avg_score'],2),$deptAverages)) ?> }]
    },
    options:{scales:{y:{beginAtZero:true,max:100}}}
  });

  new Chart(document.getElementById('chartOrgTrend'),{
    type:'line',
    data:{
      labels: <?= json_encode(array_map(fn($r)=>date('M Y',strtotime($r['month'])),$orgTrend)) ?>,
      datasets:[{ label:'Org Avg',
                  data: <?= json_encode(array_map(fn($r)=>round($r['avg_score'],2),$orgTrend)) ?>,
                  tension:.3, fill:true, borderWidth:2 }]
    },
    options:{scales:{y:{beginAtZero:true,max:100}}}
  });
  </script>

<?php
/* ════════════════════════════════════════════
 * 6B. MANAGER DASHBOARD
 * ════════════════════════════════════════════ */
elseif ($roleId === ROLE_MANAGER):

    $deptScore = $reportRepo->deptScore($deptId, $cycleMonth);
    $teamStats = $reportRepo->teamFinalScores($deptId, $cycleMonth);
    $kpiSlices = $reportRepo->kpiContributions($deptId, $cycleMonth);
?>
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card shadow-sm text-center h-100"><div class="card-body">
        <h6 class="text-uppercase small">Dept Score</h6>
        <p class="display-5"><?= round($deptScore, 2) ?>%</p>
      </div></div>
    </div>
    <div class="col-lg-4">
      <div class="card shadow-sm text-center h-100"><div class="card-body">
        <h6 class="text-uppercase small">Team Members</h6>
        <p class="display-5"><?= count($teamStats) ?></p>
      </div></div>
    </div>
    <div class="col-lg-4">
      <div class="card shadow-sm text-center h-100"><div class="card-body">
        <h6 class="text-uppercase small">Month</h6>
        <p class="display-5"><?= date('M Y', strtotime($cycleMonth)) ?></p>
      </div></div>
    </div>
  </div>

  <div class="row g-4 mt-4">
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header fw-semibold">KPI Contributions</div>
        <div class="card-body"><canvas id="chartKpi"></canvas></div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header fw-semibold">Team Final Scores</div>
        <div class="card-body"><canvas id="chartTeam"></canvas></div>
      </div>
    </div>
  </div>


  <script>
  /* KPI pie */
  new Chart(document.getElementById('chartKpi'),{
    type:'pie',
    data:{
      labels: <?= json_encode(array_column($kpiSlices,'label')) ?>,
      datasets:[{ data: <?= json_encode(array_map(fn($r)=>round($r['contribution'],2),$kpiSlices)) ?> }]
    }
  });

  /* Team bar */
  new Chart(document.getElementById('chartTeam'),{
    type:'bar',
    data:{
      labels: <?= json_encode(array_column($teamStats,'name')) ?>,
      datasets:[{ label:'Final',
                  data: <?= json_encode(array_map(fn($r)=>round($r['final_score'],2),$teamStats)) ?> }]
    },
    options:{scales:{y:{beginAtZero:true,max:100}}}
  });
  </script>

<?php
/* ════════════════════════════════════════════
 * 6C. EMPLOYEE DASHBOARD
 * ══════════════════════════════════════════ */
else:

    $history = $reportRepo->getPersonalScoreHistory($user['user_id'], 6);
    $latest  = $reportRepo->getPersonalLatestBreakdown($user['user_id'], $cycleMonth) ??
               ['dept_score'=>0,'individual_score'=>0];

    $trendLabels = array_map(fn($r)=>date('M Y',strtotime($r['month'])),$history);
    $trendScores = array_map(fn($r)=>round($r['final_score'],2),$history);
    $latestFinal = round($latest['dept_score']*0.7 + $latest['individual_score']*0.3, 2);
?>
  <div class="row g-4">
    <div class="col-lg-4"><div class="card shadow-sm text-center h-100"><div class="card-body">
      <h6 class="text-uppercase small">Latest Final</h6>
      <p class="display-5"><?= $latestFinal ?></p>
    </div></div></div>

    <div class="col-lg-4"><div class="card shadow-sm text-center h-100"><div class="card-body">
      <h6 class="text-uppercase small">Dept Component</h6>
      <p class="display-5"><?= round($latest['dept_score'],2) ?>%</p>
    </div></div></div>

    <div class="col-lg-4"><div class="card shadow-sm text-center h-100"><div class="card-body">
      <h6 class="text-uppercase small">Ind Component</h6>
      <p class="display-5"><?= round($latest['individual_score'],2) ?>%</p>
    </div></div></div>
  </div>

  <div class="row g-4 mt-4">
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header fw-semibold">Performance Trend</div>
        <div class="card-body"><canvas id="chartTrend"></canvas></div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header fw-semibold">Latest Breakdown</div>
        <div class="card-body"><canvas id="chartBreak"></canvas></div>
      </div>
    </div>
  </div>

  <script>
  /* Trend line */
  new Chart(document.getElementById('chartTrend'),{
    type:'line',
    data:{
      labels: <?= json_encode($trendLabels) ?>,
      datasets:[{ label:'Final',
                  data: <?= json_encode($trendScores) ?>,
                  tension:.3, fill:true, borderWidth:2 }]
    },
    options:{scales:{y:{beginAtZero:true,max:100}}}
  });

  /* Doughnut */
  new Chart(document.getElementById('chartBreak'),{
    type:'doughnut',
    data:{
      labels:['Dept','Ind'],
      datasets:[{ data:[<?= round($latest['dept_score'],2) ?>,
                        <?= round($latest['individual_score'],2) ?>] }]
    }
  });
  </script>

<?php endif; /* role blocks */ ?>
</div><!-- /.container -->

<footer class="footer text-center py-3 border-top">
  <small>&copy; <?= date('Y') ?> SK-PM Performance Management</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
        crossorigin="anonymous"></script>
</body>
</html>
