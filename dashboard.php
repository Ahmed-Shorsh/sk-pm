<?php
declare(strict_types=1);

/*
 |------------------------------------------------------------------
 |  dashboard.php — FULL OVERRIDE 24-Jul-2025
 |------------------------------------------------------------------
 |  Implements:
 |    • Role-aware views (admin / manager / employee)
 |    • Year / month filters with “All” options
 |    • Employee-only self-history & optional dept list
 |    • Manager / Admin top-performing depts & employees
 |    • Summary cards + Chart.js visualisations
 |
 |  Reference (previous build): :contentReference[oaicite:0]{index=0}
 */

require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/utils.php';
require_once __DIR__ . '/backend/user_controller.php';
require_once __DIR__ . '/backend/report_controller.php';

use Backend\ReportRepository;

secureSessionStart();
checkLogin();

const ROLE_ADMIN    = 1;
const ROLE_MANAGER  = 2;
const ROLE_EMPLOYEE = 3;

$user = getUser($_SESSION['user_id'] ?? 0);
if (!$user) {
    logoutUser();
    redirect('login.php');
}

$roleId     = (int)$user['role_id'];
$deptId     = (int)($user['dept_id'] ?? 0);
$cycleMonth = date('Y-m-01');           // first day of current month

global $pdo;
$reportRepo = new ReportRepository($pdo);

/**
 * Round helper.
 */
function safeRound($value, int $precision = 2): float
{
    return round((float)($value ?? 0), $precision);
}

/**
 * Return an array:
 *   [ 'YYYY' => [ [name,value,has_data]… ], … ] (descending years, ascending months)
 */
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

    // Ensure current year exists
    $currentYear = date('Y');
    $grouped[$currentYear] ??= [];

    // Fill 12 months each year
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

/* --------------------------------------------------------------
 *  Filter (year / month) handling
 * ------------------------------------------------------------ */
$availableMonths = getAvailableMonthsGrouped($reportRepo);
$selectedYear    = $_GET['year']  ?? date('Y');
$selectedMonth   = $_GET['month'] ?? date('m');
$selMonth        = "$selectedYear-" . sprintf('%02d', (int)$selectedMonth) . '-01';

$scoreMonths = $reportRepo->getScoreMonths();
if (!in_array($selMonth, $scoreMonths, true) && !empty($scoreMonths)) {
    $selMonth      = $scoreMonths[0];                // latest with data
    $selectedYear  = date('Y', strtotime($selMonth));
    $selectedMonth = date('m', strtotime($selMonth));
}

/* --------------------------------------------------------------
 *  Common summary metrics
 * ------------------------------------------------------------ */
$latestFinal     = 0;  // default to avoid “undefined”
$latest          = ['dept_score' => 0, 'individual_score' => 0];

$totalUsers      = $reportRepo->totalActiveUsers();
$totalDepts      = $reportRepo->totalDepartments();
$activeDeptsNow  = $reportRepo->activeDeptCountForMonth($cycleMonth);

$pendingPlans    = 0;
$pendingActuals  = 0;
$pendingEvals    = 0;

if ($roleId === ROLE_MANAGER) {
    $pendingPlans   = $reportRepo->pendingPlanEntries($deptId, $cycleMonth);
    $pendingActuals = $reportRepo->pendingActuals($deptId, $cycleMonth);
} elseif ($roleId === ROLE_EMPLOYEE) {
    $pendingEvals   = $reportRepo->pendingEvaluations($user['user_id'], $cycleMonth);
}

/* --------------------------------------------------------------
 *  UI Partials
 * ------------------------------------------------------------ */
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
    .month-option-disabled { color:#6c757d!important;font-style:italic }
    .month-option-enabled { font-weight:500 }
  </style>
</head>

<body class="bg-light font-serif">
<header class="container text-center my-4">
  <h1 class="display-5 fw-bold">Dashboard</h1>
  <p class="lead mb-0">Overview for <?= date('F Y', strtotime($selMonth)) ?></p>
</header>

<!-- ======================= SUMMARY CARDS ======================= -->
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
          <h6 class="text-uppercase small">Active Depts (<?= date('M') ?>)</h6>
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

    <?php else: /* EMPLOYEE */ ?>
      <div class="col-md-3">
        <div class="card shadow-sm text-center h-100"><div class="card-body">
          <h6 class="text-uppercase small">Pending Evaluations</h6>
          <p class="display-6"><?= $pendingEvals ?></p>
        </div></div>
      </div>
      <div class="col-md-3"></div>
    <?php endif; ?>
  </div>
</div>

<div class="container pb-5">
<?php
/* ===========================================================
 *  ADMIN DASHBOARD
 * ========================================================= */
if ($roleId === ROLE_ADMIN):

  $deptAverages = $reportRepo->deptAverages($selMonth);
  $orgTrend     = $reportRepo->organisationTrend(6);
?>
  <!-- ---------- Filter ---------- -->
  <form method="get" class="row mb-4">
    <div class="col-md-3">
      <label for="year-select" class="form-label">Select Year</label>
      <select id="year-select" name="year" class="form-select" onchange="updateMonthOptions()">
        <?php foreach (array_keys($availableMonths) as $y): ?>
          <option value="<?= $y ?>" <?= $y == $selectedYear ? 'selected':'' ?>><?= $y ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label for="month-select" class="form-label">Select Month</label>
      <select id="month-select" name="month" class="form-select"></select>
    </div>
    <div class="col-md-3 d-flex align-items-end">
      <button class="btn btn-primary" type="submit">Update View</button>
    </div>
  </form>

  <!-- ---------- Charts ---------- -->
  <div class="row g-4 mb-4">
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header fw-semibold">Dept Averages – <?= date('F Y', strtotime($selMonth)) ?></div>
        <div class="card-body"><canvas id="chartDeptAvg"></canvas></div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header fw-semibold">Organization Trend (6 months)</div>
        <div class="card-body"><canvas id="chartOrgTrend"></canvas></div>
      </div>
    </div>
  </div>

  <!-- ---------- Tables ---------- -->
  <div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold">Department Performance (<?= date('F Y', strtotime($selMonth)) ?>)</div>
    <div class="card-body table-responsive">
      <?php if (!$deptAverages): ?>
        <div class="alert alert-info text-center">No data for period.</div>
      <?php else: ?>
        <table class="table align-middle">
          <thead class="table-dark"><tr><th>Department</th><th class="text-center">Avg Score</th></tr></thead>
          <tbody>
            <?php foreach ($deptAverages as $d): ?>
              <tr><td><?= htmlspecialchars($d['dept_name']) ?></td><td class="text-center"><?= safeRound($d['avg_score']) ?>%</td></tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <script>
    const availableMonths = <?= json_encode($availableMonths) ?>;
    const selectedMonth   = '<?= $selectedMonth ?>';

    function updateMonthOptions() {
      const ySel = document.getElementById('year-select');
      const mSel = document.getElementById('month-select');
      mSel.innerHTML = '';
      /* All Months option */
      const allOpt = new Option('All Months','all');
      mSel.add(allOpt);
      if (availableMonths[ySel.value]) {
        availableMonths[ySel.value].forEach(m=>{
          const o = new Option(m.name, m.value.split('-')[1]);
          o.className = m.has_data ? 'month-option-enabled':'month-option-disabled';
          if (!m.has_data) o.text += ' (No Data)';
          mSel.add(o);
        });
      }
    }
    document.addEventListener('DOMContentLoaded',()=>{
      updateMonthOptions();
      document.getElementById('month-select').value = selectedMonth;
      /* Charts */
      new Chart(document.getElementById('chartDeptAvg'),{
        type:'bar',
        data:{
          labels:<?= json_encode(array_column($deptAverages,'dept_name')) ?>,
          datasets:[{label:'Avg',data:<?= json_encode(array_map(fn($r)=>safeRound($r['avg_score']),$deptAverages)) ?>}]
        },
        options:{scales:{y:{beginAtZero:true,max:100}},plugins:{legend:{display:false}}}
      });
      new Chart(document.getElementById('chartOrgTrend'),{
        type:'line',
        data:{
          labels:<?= json_encode(array_map(fn($r)=>date('M Y',strtotime($r['month'])),$orgTrend)) ?>,
          datasets:[{label:'Org Avg',tension:.3,fill:true,
            data:<?= json_encode(array_map(fn($r)=>safeRound($r['avg_score']),$orgTrend)) ?>}]
        },
        options:{scales:{y:{beginAtZero:true,max:100}}}
      });
    });
  </script>

<?php
/* ===========================================================
 *  MANAGER DASHBOARD
 * ========================================================= */
elseif ($roleId === ROLE_MANAGER):

  $deptScore = $reportRepo->deptScore($deptId, $selMonth) ?: 0;
  $teamStats = $reportRepo->teamFinalScores($deptId, $selMonth);
  $kpiSlices = $reportRepo->kpiContributions($deptId, $selMonth);
  $deptAverages = $reportRepo->deptAverages($selMonth); // for top list
?>
  <!-- ---------- Filter ---------- -->
  <form method="get" class="row mb-4">
    <div class="col-md-3">
      <label for="year-select" class="form-label">Select Year</label>
      <select id="year-select" name="year" class="form-select" onchange="updateMonthOptions()">
        <?php foreach (array_keys($availableMonths) as $y): ?>
          <option value="<?= $y ?>" <?= $y == $selectedYear ? 'selected':'' ?>><?= $y ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label for="month-select" class="form-label">Select Month</label>
      <select id="month-select" name="month" class="form-select"></select>
    </div>
    <div class="col-md-3 d-flex align-items-end">
      <button class="btn btn-primary" type="submit">Update View</button>
    </div>
  </form>

  <!-- ---------- Key Metrics ---------- -->
  <div class="row g-4">
    <div class="col-lg-4"><div class="card shadow-sm text-center h-100"><div class="card-body">
      <h6 class="text-uppercase small">Dept Score</h6><p class="display-5"><?= safeRound($deptScore) ?>%</p>
    </div></div></div>
    <div class="col-lg-4"><div class="card shadow-sm text-center h-100"><div class="card-body">
      <h6 class="text-uppercase small">Team Members</h6><p class="display-5"><?= count($teamStats) ?></p>
    </div></div></div>
    <div class="col-lg-4"><div class="card shadow-sm text-center h-100"><div class="card-body">
      <h6 class="text-uppercase small">Month</h6><p class="display-5"><?= date('M Y',strtotime($selMonth)) ?></p>
    </div></div></div>
  </div>

  <!-- ---------- KPI Pie + Team Bar ---------- -->
  <div class="row g-4 my-4">
    <div class="col-lg-6"><div class="card shadow-sm"><div class="card-body">
      <canvas id="chartKpi"></canvas></div></div></div>
    <div class="col-lg-6"><div class="card shadow-sm"><div class="card-body">
      <canvas id="chartTeam"></canvas></div></div></div>
  </div>

  <!-- ---------- Top Lists (reuse admin list logic) ---------- -->
  <div class="data-container mb-4">
    <h2>Top-Performing Departments (<?= date('F Y',strtotime($selMonth)) ?>)</h2>
    <?php if (!$deptAverages): ?><div class="alert alert-info text-center">No data.</div>
    <?php else: ?>
      <table class="table align-middle">
        <thead><tr><th>Department</th><th class="text-center">Avg Score</th></tr></thead>
        <tbody><?php foreach ($deptAverages as $d): ?>
          <tr><td><?= htmlspecialchars($d['dept_name']) ?></td><td class="text-center"><?= safeRound($d['avg_score']) ?>%</td></tr>
        <?php endforeach; ?></tbody>
      </table>
    <?php endif; ?>
  </div>

  <?php
    $topEmployeesStmt = $pdo->prepare("
      SELECT u.name, s.final_score
        FROM scores s
        JOIN users u ON s.user_id = u.user_id
       WHERE s.month = :m
       ORDER BY s.final_score DESC LIMIT 5");
    $topEmployeesStmt->execute([':m'=>$selMonth]);
    $topEmployees = $topEmployeesStmt->fetchAll(PDO::FETCH_ASSOC);
  ?>
  <div class="data-container mb-4">
    <h2>Top-Performing Employees (<?= date('F Y',strtotime($selMonth)) ?>)</h2>
    <?php if (!$topEmployees): ?><div class="alert alert-info text-center">No data.</div>
    <?php else: ?>
      <table class="table align-middle">
        <thead><tr><th>Name</th><th class="text-center">Final Score</th></tr></thead>
        <tbody><?php foreach ($topEmployees as $e): ?>
          <tr><td><?= htmlspecialchars($e['name']) ?></td><td class="text-center"><?= safeRound($e['final_score']) ?>%</td></tr>
        <?php endforeach; ?></tbody>
      </table>
    <?php endif; ?>
  </div>

  <script>
    const availableMonths = <?= json_encode($availableMonths) ?>;
    const selectedMonth   = '<?= $selectedMonth ?>';
    function updateMonthOptions(){ /* same body as admin but with All option */ 
      const y=document.getElementById('year-select'),m=document.getElementById('month-select');
      m.innerHTML='';const all=new Option('All Months','all');m.add(all);
      (availableMonths[y.value]||[]).forEach(mm=>{
        const o=new Option(mm.name,mm.value.split('-')[1]);
        o.className=mm.has_data?'month-option-enabled':'month-option-disabled';
        if(!mm.has_data)o.text+=' (No Data)';m.add(o);
      });
    }
    document.addEventListener('DOMContentLoaded',()=>{
      updateMonthOptions();document.getElementById('month-select').value=selectedMonth;

      new Chart(document.getElementById('chartKpi'),{
        type:'pie',
        data:{labels:<?= json_encode(array_column($kpiSlices,'label')) ?>,
              datasets:[{data:<?= json_encode(array_map(fn($r)=>safeRound($r['contribution']),$kpiSlices)) ?>}]},
        options:{plugins:{legend:{position:'bottom'}}}
      });
      new Chart(document.getElementById('chartTeam'),{
        type:'bar',
        data:{labels:<?= json_encode(array_column($teamStats,'name')) ?>,
              datasets:[{label:'Final',data:<?= json_encode(array_map(fn($r)=>safeRound($r['final_score']),$teamStats)) ?>}]},
        options:{indexAxis:'y',scales:{x:{beginAtZero:true,max:100}},plugins:{legend:{display:false}}}
      });
    });
  </script>

<?php
/* ===========================================================
 *  EMPLOYEE DASHBOARD
 * ========================================================= */
else: // ROLE_EMPLOYEE
  $history        = $reportRepo->personalTrend($user['user_id'], 6);
  $latest         = $reportRepo->personalBreakdown($user['user_id'], $selMonth) ?? $latest;
  $latestFinal    = safeRound(($latest['dept_score'] * 0.7) + ($latest['individual_score'] * 0.3));
?>
  <!-- ---------- Filter ---------- -->
  <form method="get" class="row mb-4">
    <div class="col-md-3">
      <label for="year-select" class="form-label">Select Year</label>
      <select id="year-select" name="year" class="form-select" onchange="updateMonthOptions()">
        <option value="all" <?= $selectedYear==='all'?'selected':''?>>All</option>
        <?php foreach (array_keys($availableMonths) as $y): ?>
          <option value="<?= $y ?>" <?= $y == $selectedYear ? 'selected':'' ?>><?= $y ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label for="month-select" class="form-label">Select Month</label>
      <select id="month-select" name="month" class="form-select"></select>
    </div>
    <div class="col-md-3 d-flex align-items-end">
      <button class="btn btn-primary" type="submit">Update View</button>
    </div>
  </form>

  <!-- ---------- Key Metrics ---------- -->
  <div class="row g-4">
    <div class="col-lg-4"><div class="card shadow-sm text-center h-100"><div class="card-body">
      <h6 class="text-uppercase small">Latest Final</h6><p class="display-5"><?= $latestFinal ?></p>
    </div></div></div>
    <div class="col-lg-4"><div class="card shadow-sm text-center h-100"><div class="card-body">
      <h6 class="text-uppercase small">Dept Component</h6><p class="display-5"><?= safeRound($latest['dept_score']) ?>%</p>
    </div></div></div>
    <div class="col-lg-4"><div class="card shadow-sm text-center h-100"><div class="card-body">
      <h6 class="text-uppercase small">Indiv Component</h6><p class="display-5"><?= safeRound($latest['individual_score']) ?>%</p>
    </div></div></div>
  </div>

  <!-- ---------- History Table ---------- -->
  <?php
    $historyFiltered = [];
    foreach ($history as $h) {
      $y = date('Y',strtotime($h['month']));
      if ($selectedYear==='all' || $selectedYear==$y) {
        if ($selectedMonth==='all' || $selectedMonth==date('m',strtotime($h['month'])))
          $historyFiltered[]=$h;
      }
    }
  ?>
  <div class="data-container table-responsive my-4">
    <h2>My Evaluation History <?= ($selectedYear==='all'?'':'– '.($selectedMonth==='all'?$selectedYear:date('F Y',strtotime($selMonth)))) ?></h2>
    <?php if(!$historyFiltered): ?><div class="alert alert-info text-center">No evaluations.</div>
    <?php else: ?>
      <table class="table align-middle"><thead><tr><th>Month</th><th class="text-center">Final Score</th></tr></thead>
        <tbody><?php foreach($historyFiltered as $h): ?>
          <tr><td><?= date('F Y',strtotime($h['month'])) ?></td><td class="text-center"><?= safeRound($h['final_score']) ?>%</td></tr>
        <?php endforeach; ?></tbody>
      </table>
    <?php endif; ?>
  </div>

  <!-- ---------- Optional Dept List ---------- -->
  <?php if ($selectedYear!=='all' && $selectedMonth!=='all'):
      $deptEval = array_filter(
        $reportRepo->teamFinalScores($deptId,$selMonth),
        fn($r)=>$r['user_id']!==$user['user_id']
      ); ?>
    <p><a class="btn btn-sm btn-outline-secondary" data-bs-toggle="collapse" href="#dept-evals">Show Department Evaluations</a></p>
    <div class="collapse" id="dept-evals">
      <div class="data-container table-responsive">
        <h2>Dept Evaluations – <?= date('F Y',strtotime($selMonth)) ?></h2>
        <?php if(!$deptEval): ?><div class="alert alert-info text-center">None.</div>
        <?php else: ?>
          <table class="table align-middle"><thead><tr><th>Name</th><th class="text-center">Final Score</th></tr></thead>
            <tbody><?php foreach($deptEval as $p): ?>
              <tr><td><?= htmlspecialchars($p['name']) ?></td><td class="text-center"><?= safeRound($p['final_score']) ?>%</td></tr>
            <?php endforeach; ?></tbody>
          </table>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>

  <script>
    const availableMonths = <?= json_encode($availableMonths) ?>;
    function updateMonthOptions(){
      const y=document.getElementById('year-select'),m=document.getElementById('month-select');
      m.innerHTML='';
      if(y.value==='all'){m.add(new Option('All Months','all'));m.disabled=true;return;}
      m.disabled=false;
      m.add(new Option('All Months','all'));
      (availableMonths[y.value]||[]).forEach(mm=>{
        const o=new Option(mm.name,mm.value.split('-')[1]);
        o.className=mm.has_data?'month-option-enabled':'month-option-disabled';
        if(!mm.has_data)o.text+=' (No Data)';m.add(o);
      });
    }
    document.addEventListener('DOMContentLoaded',()=>{updateMonthOptions();});
  </script>

<?php endif; /* role blocks */ ?>
</div><!-- /.container -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
