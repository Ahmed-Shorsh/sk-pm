<?php
declare(strict_types=1);

// Include core backend files and enforce authentication
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/utils.php';
require_once __DIR__ . '/backend/user_controller.php';
require_once __DIR__ . '/backend/report_controller.php';

use Backend\ReportRepository;

secureSessionStart();
checkLogin();  // Redirects to login if not authenticated

// Role ID constants and retrieve logged-in user
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
$cycleMonth = date('Y-m-01');  // current performance cycle (first day of current month)

global $pdo;
$reportRepo = new ReportRepository($pdo);

// Helper function to safely round numbers
function safeRound($value, int $precision = 2): float {
    return round((float)($value ?? 0), $precision);
}

// Helper function to get available years and months
function getAvailableMonthsGrouped($reportRepo): array {
    $scoreMonths = $reportRepo->getScoreMonths();
    $grouped = [];
    
    foreach ($scoreMonths as $month) {
        $year = date('Y', strtotime($month));
        $monthName = date('F', strtotime($month));
        $monthValue = date('Y-m-01', strtotime($month));
        
        if (!isset($grouped[$year])) {
            $grouped[$year] = [];
        }
        
        $grouped[$year][] = [
            'name' => $monthName,
            'value' => $monthValue,
            'has_data' => true
        ];
    }
    
    // Add current year if not present
    $currentYear = date('Y');
    if (!isset($grouped[$currentYear])) {
        $grouped[$currentYear] = [];
    }
    
    // Fill in missing months for each year
    foreach ($grouped as $year => &$months) {
        $existingMonths = array_column($months, 'value');
        for ($m = 1; $m <= 12; $m++) {
            $monthValue = $year . '-' . sprintf('%02d', $m) . '-01';
            if (!in_array($monthValue, $existingMonths)) {
                $months[] = [
                    'name' => date('F', mktime(0, 0, 0, $m, 1)),
                    'value' => $monthValue,
                    'has_data' => false
                ];
            }
        }
        
        // Sort months chronologically
        usort($months, function($a, $b) {
            return strtotime($a['value']) - strtotime($b['value']);
        });
    }
    
    // Sort years in descending order
    krsort($grouped);
    
    return $grouped;
}

// Get year/month selection
$availableMonths = getAvailableMonthsGrouped($reportRepo);
$selectedYear = $_GET['year'] ?? date('Y');
$selectedMonth = $_GET['month'] ?? date('m');
$selMonth = $selectedYear . '-' . sprintf('%02d', (int)$selectedMonth) . '-01';

// Validate selection - if no data exists for selected month, use latest available
$scoreMonths = $reportRepo->getScoreMonths();
if (!in_array($selMonth, $scoreMonths) && !empty($scoreMonths)) {
    $selMonth = $scoreMonths[0]; // Use latest available month
    $selectedYear = date('Y', strtotime($selMonth));
    $selectedMonth = date('m', strtotime($selMonth));
}

// Common summary metrics (visible to all roles)
$totalUsers = $reportRepo->totalActiveUsers();
$totalDepts = $reportRepo->totalDepartments();
$activeDeptsNow = $reportRepo->activeDeptCountForMonth($cycleMonth);

// Role-specific pending counts (default 0 if not applicable)
$pendingPlans   = 0;
$pendingActuals = 0;
$pendingEvals   = 0;
if ($roleId === ROLE_MANAGER) {
    // Manager: pending plan entries and actuals for their department (current month)
    $pendingPlans   = $reportRepo->pendingPlanEntries($deptId, $cycleMonth);
    $pendingActuals = $reportRepo->pendingActuals($deptId, $cycleMonth);
} elseif ($roleId === ROLE_EMPLOYEE) {
    // Employee: pending peer evaluations to submit (in their department)
    $pendingEvals   = $reportRepo->pendingEvaluations($user['user_id'], $cycleMonth);
}

// Include UI partials (navbar, intro modal)
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
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
  <link rel="stylesheet" href="./assets/css/style.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    .month-option-disabled {
      color: #6c757d !important;
      font-style: italic;
    }
    .month-option-enabled {
      font-weight: 500;
    }
  </style>
</head>

<body class="bg-light font-serif">
<header class="container text-center my-4">
  <h1 class="display-5 fw-bold">Dashboard</h1>
  <p class="lead mb-0">Overview for <?= date('F Y', strtotime($selMonth)) ?></p>
</header>

<!-- Summary Cards (common for all roles) -->
<div class="container mb-5">
  <div class="row g-4">
    <!-- Total Users -->
    <div class="col-md-3">
      <div class="card shadow-sm text-center h-100">
        <div class="card-body">
          <h6 class="text-uppercase small">Total Users</h6>
          <p class="display-6"><?= $totalUsers ?></p>
        </div>
      </div>
    </div>
    <!-- Total Departments -->
    <div class="col-md-3">
      <div class="card shadow-sm text-center h-100">
        <div class="card-body">
          <h6 class="text-uppercase small">Departments</h6>
          <p class="display-6"><?= $totalDepts ?></p>
        </div>
      </div>
    </div>

    <?php if ($roleId === ROLE_ADMIN): ?>
      <!-- Active Departments (current month) for Admin -->
      <div class="col-md-3">
        <div class="card shadow-sm text-center h-100">
          <div class="card-body">
            <h6 class="text-uppercase small">Active Depts (<?= date('M') ?>)</h6>
            <p class="display-6"><?= $activeDeptsNow ?></p>
          </div>
        </div>
      </div>
      <!-- (Empty column to maintain layout spacing) -->
      <div class="col-md-3"></div>

    <?php elseif ($roleId === ROLE_MANAGER): ?>
      <!-- Pending Plan Entries for Manager's dept -->
      <div class="col-md-3">
        <div class="card shadow-sm text-center h-100">
          <div class="card-body">
            <h6 class="text-uppercase small">Pending Plans</h6>
            <p class="display-6"><?= $pendingPlans ?></p>
          </div>
        </div>
      </div>
      <!-- Pending Actuals for Manager's dept -->
      <div class="col-md-3">
        <div class="card shadow-sm text-center h-100">
          <div class="card-body">
            <h6 class="text-uppercase small">Pending Actuals</h6>
            <p class="display-6"><?= $pendingActuals ?></p>
          </div>
        </div>
      </div>

    <?php else: ?>
      <!-- Pending Evaluations for Employee -->
      <div class="col-md-3">
        <div class="card shadow-sm text-center h-100">
          <div class="card-body">
            <h6 class="text-uppercase small">Pending Evaluations</h6>
            <p class="display-6"><?= $pendingEvals ?></p>
          </div>
        </div>
      </div>
      <!-- (Empty column to maintain layout spacing) -->
      <div class="col-md-3"></div>
    <?php endif; ?>
  </div>
</div>

<div class="container pb-5">
<?php
// Admin Dashboard Section
if ($roleId === ROLE_ADMIN):
    // Fetch data for selected month
    $deptAverages = $reportRepo->deptAverages($selMonth);    // average final scores per department
    $orgTrend     = $reportRepo->organisationTrend(6);       // last 6 months organization-wide average trend
?>
  <!-- Admin: Year and Month selector -->
  <form method="get" class="row mb-4">
    <div class="col-md-3">
      <label for="year-select" class="form-label">Select Year</label>
      <select id="year-select" name="year" class="form-select" onchange="updateMonthOptions()">
        <?php foreach (array_keys($availableMonths) as $year): ?>
          <option value="<?= $year ?>" <?= $year == $selectedYear ? 'selected' : '' ?>>
            <?= $year ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label for="month-select" class="form-label">Select Month</label>
      <select id="month-select" name="month" class="form-select">
        <!-- Options will be populated by JavaScript -->
      </select>
    </div>
    <div class="col-md-3 d-flex align-items-end">
      <button type="submit" class="btn btn-primary">Update View</button>
    </div>
  </form>

  <!-- Admin: Charts for organization overview -->
  <div class="row g-4 mb-4">
    <!-- Department Averages Bar Chart -->
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header fw-semibold">
          Dept Averages – <?= date('F Y', strtotime($selMonth)) ?>
        </div>
        <div class="card-body">
          <canvas id="chartDeptAvg"></canvas>
        </div>
      </div>
    </div>
    <!-- Organization Trend Line Chart (6 months) -->
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header fw-semibold">Organization Trend (6 months)</div>
        <div class="card-body">
          <canvas id="chartOrgTrend"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Admin: Department performance table for selected month -->
  <div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold">Department Performance (<?= date('F Y', strtotime($selMonth)) ?>)</div>
    <div class="card-body table-responsive">
      <?php if (empty($deptAverages)): ?>
        <div class="alert alert-info text-center">
          <i class="fas fa-info-circle me-2"></i>
          No performance data available for <?= date('F Y', strtotime($selMonth)) ?>
        </div>
      <?php else: ?>
        <table class="table table-striped table-bordered align-middle">
          <thead class="table-dark">
            <tr>
              <th>Department</th>
              <th class="text-center">Avg Score</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($deptAverages as $row): ?>
            <tr>
              <td><?= htmlspecialchars($row['dept_name']) ?></td>
              <td class="text-center"><?= safeRound($row['avg_score']) ?>%</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Initialize Admin charts using Chart.js -->
  <script>
    // Available months data for JavaScript
    const availableMonths = <?= json_encode($availableMonths) ?>;
    const selectedMonth = '<?= $selectedMonth ?>';

    function updateMonthOptions() {
      const yearSelect = document.getElementById('year-select');
      const monthSelect = document.getElementById('month-select');
      const selectedYear = yearSelect.value;
      
      // Clear existing options
      monthSelect.innerHTML = '';
      
      if (availableMonths[selectedYear]) {
        availableMonths[selectedYear].forEach(function(month) {
          const option = document.createElement('option');
          option.value = month.value.split('-')[1]; // Extract month number
          option.textContent = month.name;
          
          if (!month.has_data) {
            option.className = 'month-option-disabled';
            option.textContent += ' (No Data)';
          } else {
            option.className = 'month-option-enabled';
          }
          
          monthSelect.appendChild(option);
        });
      }
    }

    // Initialize month options on page load
    document.addEventListener('DOMContentLoaded', function() {
      updateMonthOptions();
      document.getElementById('month-select').value = selectedMonth;
    });

    // Bar chart for Department Averages
    new Chart(document.getElementById('chartDeptAvg'), {
      type: 'bar',
      data: {
        labels: <?= json_encode(array_column($deptAverages, 'dept_name')) ?>,
        datasets: [{
          label: 'Average',
          data: <?= json_encode(array_map(fn($r) => safeRound($r['avg_score']), $deptAverages)) ?>,
          backgroundColor: 'rgba(54,162,235,0.5)',
          borderColor: 'rgba(54,162,235,1)',
          borderWidth: 1
        }]
      },
      options: {
        scales: { y: { beginAtZero: true, max: 100 } },
        plugins: { legend: { display: false } }
      }
    });

    // Line chart for Organization Trend
    new Chart(document.getElementById('chartOrgTrend'), {
      type: 'line',
      data: {
        labels: <?= json_encode(array_map(fn($r) => date('M Y', strtotime($r['month'])), $orgTrend)) ?>,
        datasets: [{
          label: 'Org Avg',
          data: <?= json_encode(array_map(fn($r) => safeRound($r['avg_score']), $orgTrend)) ?>,
          tension: 0.3,
          fill: true,
          borderColor: 'rgba(75,192,192,1)',
          backgroundColor: 'rgba(75,192,192,0.2)',
          borderWidth: 2
        }]
      },
      options: {
        scales: { y: { beginAtZero: true, max: 100 } }
      }
    });
  </script>

<?php
// Manager Dashboard Section
elseif ($roleId === ROLE_MANAGER):
    // Fetch data for manager's department and selected month
    $deptScore = $reportRepo->deptScore($deptId, $selMonth) ?: 0;
    $teamStats = $reportRepo->teamFinalScores($deptId, $selMonth);
    $kpiSlices = $reportRepo->kpiContributions($deptId, $selMonth);
?>
  <!-- Manager: Year and Month selector -->
  <form method="get" class="row mb-4">
    <div class="col-md-3">
      <label for="year-select" class="form-label">Select Year</label>
      <select id="year-select" name="year" class="form-select" onchange="updateMonthOptions()">
        <?php foreach (array_keys($availableMonths) as $year): ?>
          <option value="<?= $year ?>" <?= $year == $selectedYear ? 'selected' : '' ?>>
            <?= $year ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label for="month-select" class="form-label">Select Month</label>
      <select id="month-select" name="month" class="form-select">
        <!-- Options will be populated by JavaScript -->
      </select>
    </div>
    <div class="col-md-3 d-flex align-items-end">
      <button type="submit" class="btn btn-primary">Update View</button>
    </div>
  </form>

  <!-- Manager: Key metrics cards (Dept Score, Team Members count, Month) -->
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card shadow-sm text-center h-100">
        <div class="card-body">
          <h6 class="text-uppercase small">Dept Score</h6>
          <p class="display-5"><?= safeRound($deptScore) ?>%</p>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card shadow-sm text-center h-100">
        <div class="card-body">
          <h6 class="text-uppercase small">Team Members</h6>
          <p class="display-5"><?= count($teamStats) ?></p>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card shadow-sm text-center h-100">
        <div class="card-body">
          <h6 class="text-uppercase small">Month</h6>
          <p class="display-5"><?= date('M Y', strtotime($selMonth)) ?></p>
        </div>
      </div>
    </div>
  </div>

  <!-- Manager: Charts for KPI contributions and team final scores -->
  <div class="row g-4 mt-4">
    <!-- KPI Contributions Pie Chart -->
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header fw-semibold">KPI Contributions</div>
        <div class="card-body">
          <canvas id="chartKpi"></canvas>
        </div>
      </div>
    </div>
    <!-- Team Final Scores Bar Chart -->
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header fw-semibold">Team Final Scores</div>
        <div class="card-body">
          <canvas id="chartTeam"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Manager: Team performance table for selected month -->
  <div class="card shadow-sm mt-4">
    <div class="card-header fw-semibold">Team Performance (<?= date('F Y', strtotime($selMonth)) ?>)</div>
    <div class="card-body table-responsive">
      <?php if (empty($teamStats)): ?>
        <div class="alert alert-info text-center">
          <i class="fas fa-info-circle me-2"></i>
          No performance data available for your team in <?= date('F Y', strtotime($selMonth)) ?>
        </div>
      <?php else: ?>
        <table class="table table-striped table-bordered align-middle">
          <thead class="table-dark">
            <tr>
              <th>Employee Name</th>
              <th class="text-center">Individual Score</th>
              <th class="text-center">Final Score</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($teamStats as $member): ?>
            <tr>
              <td><?= htmlspecialchars($member['name']) ?></td>
              <td class="text-center"><?= safeRound($member['individual_score'] ?? 0) ?>%</td>
              <td class="text-center"><?= safeRound($member['final_score'] ?? 0) ?>%</td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Initialize Manager charts -->
  <script>
    // Available months data for JavaScript
    const availableMonths = <?= json_encode($availableMonths) ?>;
    const selectedMonth = '<?= $selectedMonth ?>';

    function updateMonthOptions() {
      const yearSelect = document.getElementById('year-select');
      const monthSelect = document.getElementById('month-select');
      const selectedYear = yearSelect.value;
      
      // Clear existing options
      monthSelect.innerHTML = '';
      
      if (availableMonths[selectedYear]) {
        availableMonths[selectedYear].forEach(function(month) {
          const option = document.createElement('option');
          option.value = month.value.split('-')[1]; // Extract month number
          option.textContent = month.name;
          
          if (!month.has_data) {
            option.className = 'month-option-disabled';
            option.textContent += ' (No Data)';
          } else {
            option.className = 'month-option-enabled';
          }
          
          monthSelect.appendChild(option);
        });
      }
    }

    // Initialize month options on page load
    document.addEventListener('DOMContentLoaded', function() {
      updateMonthOptions();
      document.getElementById('month-select').value = selectedMonth;
    });

    // Pie chart for KPI Contributions
    new Chart(document.getElementById('chartKpi'), {
      type: 'pie',
      data: {
        labels: <?= json_encode(array_column($kpiSlices, 'label')) ?>,
        datasets: [{
          data: <?= json_encode(array_map(fn($r) => safeRound($r['contribution'] ?? 0), $kpiSlices)) ?>,
          backgroundColor: ['#4e79a7','#f28e2b','#e15759','#76b7b2','#59a14f','#edc949','#af7aa1','#ff9da7','#9c755f','#bab0ab']
        }]
      },
      options: {
        plugins: {
          legend: { position: 'bottom' }
        }
      }
    });

    // Bar chart for Team Final Scores
    new Chart(document.getElementById('chartTeam'), {
      type: 'bar',
      data: {
        labels: <?= json_encode(array_column($teamStats, 'name')) ?>,
        datasets: [{
          label: 'Final Score',
          data: <?= json_encode(array_map(fn($r) => safeRound($r['final_score'] ?? 0), $teamStats)) ?>,
          backgroundColor: 'rgba(54,162,235,0.5)',
          borderColor: 'rgba(54,162,235,1)',
          borderWidth: 1
        }]
      },
      options: {
        indexAxis: 'y',
        scales: { x: { beginAtZero: true, max: 100 } },
        plugins: { legend: { display: false } }
      }
    });
  </script>

<?php
// Employee Dashboard Section
else:
    // Personal performance data for selected month and recent history
    $history = $reportRepo->getPersonalScoreHistory($user['user_id'], 6);  // last 6 months (oldest first)
    $latest  = $reportRepo->getPersonalLatestBreakdown($user['user_id'], $selMonth);
    if (!$latest) {
        $latest = ['dept_score' => 0, 'individual_score' => 0];
    }
    $latestFinal = safeRound(($latest['dept_score'] ?? 0) * 0.7 + ($latest['individual_score'] ?? 0) * 0.3);
    // Prepare data for charts
    $trendLabels = array_map(fn($r) => date('M Y', strtotime($r['month'])), $history);
    $trendScores = array_map(fn($r) => safeRound($r['final_score'] ?? 0), $history);
?>
  <!-- Employee: Year and Month selector -->
  <form method="get" class="row mb-4">
    <div class="col-md-3">
      <label for="year-select" class="form-label">Select Year</label>
      <select id="year-select" name="year" class="form-select" onchange="updateMonthOptions()">
        <?php foreach (array_keys($availableMonths) as $year): ?>
          <option value="<?= $year ?>" <?= $year == $selectedYear ? 'selected' : '' ?>>
            <?= $year ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label for="month-select" class="form-label">Select Month</label>
      <select id="month-select" name="month" class="form-select">
        <!-- Options will be populated by JavaScript -->
      </select>
    </div>
    <div class="col-md-3 d-flex align-items-end">
      <button type="submit" class="btn btn-primary">Update View</button>
    </div>
  </form>

  <!-- Employee: Key metrics cards (Latest Final, Dept component, Ind component) -->
  <div class="row g-4">
    <div class="col-lg-4">
      <div class="card shadow-sm text-center h-100">
        <div class="card-body">
          <h6 class="text-uppercase small">Latest Final</h6>
          <p class="display-5"><?= $latestFinal ?></p>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card shadow-sm text-center h-100">
        <div class="card-body">
          <h6 class="text-uppercase small">Dept Component</h6>
          <p class="display-5"><?= safeRound($latest['dept_score'] ?? 0) ?>%</p>
        </div>
      </div>
    </div>
    <div class="col-lg-4">
      <div class="card shadow-sm text-center h-100">
        <div class="card-body">
          <h6 class="text-uppercase small">Indiv Component</h6>
          <p class="display-5"><?= safeRound($latest['individual_score'] ?? 0) ?>%</p>
        </div>
      </div>
    </div>
  </div>

  <!-- Employee: Charts for performance trend and latest breakdown -->
  <div class="row g-4 mt-4">
    <!-- Personal Performance Trend (line chart) -->
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header fw-semibold">Performance Trend (Last 6 Months)</div>
        <div class="card-body">
          <canvas id="chartTrend"></canvas>
        </div>
      </div>
    </div>
    <!-- Latest Performance Breakdown (doughnut chart) -->
    <div class="col-lg-6">
      <div class="card shadow-sm">
        <div class="card-header fw-semibold">Latest Breakdown (Dept vs Ind)</div>
        <div class="card-body">
          <canvas id="chartBreak"></canvas>
        </div>
      </div>
    </div>
  </div>

  <!-- Initialize Employee charts -->
  <script>
    // Available months data for JavaScript
    const availableMonths = <?= json_encode($availableMonths) ?>;
    const selectedMonth = '<?= $selectedMonth ?>';

    function updateMonthOptions() {
      const yearSelect = document.getElementById('year-select');
      const monthSelect = document.getElementById('month-select');
      const selectedYear = yearSelect.value;
      
      // Clear existing options
      monthSelect.innerHTML = '';
      
      if (availableMonths[selectedYear]) {
        availableMonths[selectedYear].forEach(function(month) {
          const option = document.createElement('option');
          option.value = month.value.split('-')[1]; // Extract month number
          option.textContent = month.name;
          
          if (!month.has_data) {
            option.className = 'month-option-disabled';
            option.textContent += ' (No Data)';
          } else {
            option.className = 'month-option-enabled';
          }
          
          monthSelect.appendChild(option);
        });
      }
    }

    // Initialize month options on page load
    document.addEventListener('DOMContentLoaded', function() {
      updateMonthOptions();
      document.getElementById('month-select').value = selectedMonth;
    });

    // Line chart for personal performance trend
    new Chart(document.getElementById('chartTrend'), {
      type: 'line',
      data: {
        labels: <?= json_encode($trendLabels) ?>,
        datasets: [{
          label: 'Final Score',
          data: <?= json_encode($trendScores) ?>,
          tension: 0.3,
          fill: true,
          borderColor: 'rgba(75,192,192,1)',
          backgroundColor: 'rgba(75,192,192,0.2)',
          borderWidth: 2
        }]
      },
      options: {
        scales: { y: { beginAtZero: true, max: 100 } }
      }
    });

    // Doughnut chart for latest breakdown (Dept vs Individual contribution)
    new Chart(document.getElementById('chartBreak'), {
      type: 'doughnut',
      data: {
        labels: ['Dept', 'Ind'],
        datasets: [{
          data: [
            <?= safeRound($latest['dept_score'] ?? 0) ?>,
            <?= safeRound($latest['individual_score'] ?? 0) ?>
          ],
          backgroundColor: ['#4e79a7', '#f28e2b']
        }]
      },
      options: {
        plugins: {
          legend: { position: 'bottom' }
        }
      }
    });
  </script>
<?php endif; // end role-specific sections ?>
</div> <!-- /.container -->

<footer class="footer text-center py-3 border-top">
  <small>&copy; <?= date('Y') ?> SK-PM Performance Management</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
