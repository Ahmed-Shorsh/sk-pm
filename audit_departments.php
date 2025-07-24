<?php
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/utils.php';
require_once __DIR__ . '/backend/department_audit_repo.php';
require_once __DIR__ . '/backend/evaluation_controller.php';

secureSessionStart();
checkLogin();
if (($_SESSION['role_id'] ?? 0) !== 1) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied');
}

use Backend\DepartmentAuditRepository;

/**
 * Repository instance (uses existing $pdo supplied by included DB bootstrap;
 * DO NOT introduce getPDO() since it is not part of your original codebase).
 */
$repo = new DepartmentAuditRepository($pdo);

/**
 * AJAX: submit audit score (5 = Completed, 2.5 = Not Enough Data, 0 = Not Completed)
 */
if (
  $_SERVER['REQUEST_METHOD'] === 'POST'
  && isset($_POST['id'], $_POST['score'])
  && !isset($_POST['normal_page_load'])
) {
  header('Content-Type: application/json; charset=utf-8');

  $snapshotId = (int)$_POST['id'];
  $scoreRaw   = trim((string)$_POST['score']);

  // Whitelist
  $validScores = ['5', '2.5', '0'];
  if (!in_array($scoreRaw, $validScores, true)) {
      echo json_encode(['success' => false, 'message' => 'Invalid score value.']);
      exit;
  }

  // Auth check
  if (($_SESSION['role_id'] ?? 0) !== 1) {
      echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
      exit;
  }

  $scoreFloat = (float)$scoreRaw;

  try {
      // 1) update the audit_score
      $stmt = $pdo->prepare("
          UPDATE department_indicator_monthly
             SET audit_score = :score
           WHERE snapshot_id = :id
           LIMIT 1
      ");
      $stmt->execute([
          ':score' => $scoreFloat,
          ':id'    => $snapshotId
      ]);

      if ($stmt->rowCount() === 0) {
          echo json_encode([
              'success' => false,
              'message' => 'Record not found or unchanged.'
          ]);
          exit;
      }

      // ── NEW: cascade this audit into the scores table ──
      // fetch dept_id & month for this snapshot
      $infoStmt = $pdo->prepare("
          SELECT dept_id, month
            FROM department_indicator_monthly
           WHERE snapshot_id = :id
      ");
      $infoStmt->execute([':id' => $snapshotId]);
      $info = $infoStmt->fetch(PDO::FETCH_ASSOC);

      if ($info) {
          $evalRepo = new \Backend\EvaluationRepository($pdo);

          // fetch all active users in that dept
          $uStmt = $pdo->prepare("
              SELECT user_id
                FROM users
               WHERE dept_id = :d
                 AND active  = 1
          ");
          $uStmt->execute([':d' => $info['dept_id']]);

          // refresh their scores
          foreach ($uStmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
              $evalRepo->updateScoresAfterEvaluation((int)$uid, $info['month']);
          }
      }
      // ─────────────────────────────────────────────────────

      // Map numeric → label
      $labelMap = [
          5.0  => 'Completed',
          2.5  => 'Not Enough Data',
          0.0  => 'Not Completed'
      ];
      $label = $labelMap[$scoreFloat] ?? 'Updated';

      echo json_encode([
          'success'     => true,
          'score'       => $scoreFloat,
          'score_label' => $label,
          'message'     => 'Audit score saved.'
      ]);

  } catch (Throwable $e) {
      error_log('Audit score update error: ' . $e->getMessage());
      echo json_encode([
          'success' => false,
          'message' => 'Failed to update score.'
      ]);
  }
  exit;
}

/**
 * Filter inputs
 */
$yearSel  = (int)($_GET['year'] ?? date('Y'));
$monthSel = $_GET['month'] ?? '';             // '' | 'all' | 'YYYY-MM'
$deptSel  = $_GET['dept']  ?? '';             // '' | 'all' | dept_id
$viewType = $_GET['view']  ?? 'summary';      // summary | incomplete

/**
 * Populate filter dropdown data via repository API
 */
$years  = $repo->availableYears();
$months = $repo->availableMonthsForYear($yearSel);

if ($monthSel) {
    // If user already chose a month, refine department list.
    if ($monthSel === 'all') {
        $depts = $repo->getDepartmentsByYear($yearSel);
    } else {
        $depts = $repo->getDepartmentsByMonth($monthSel);
    }
} else {
    $depts = $repo->allDepartments();
}

/**
 * Data containers
 */
$evaluations    = [];
$summaries      = [];
$incompleteData = [];
$errorMessage   = '';

try {
    if ($monthSel && $deptSel) {

        // Build dynamic WHERE conditions (raw SQL for the detailed evaluations table)
        $conditions = [];
        $params     = [];

        if ($monthSel !== 'all') {
            $conditions[] = "DATE_FORMAT(dim.month, '%Y-%m') = ?";
            $params[]     = $monthSel;
        } else {
            $conditions[] = "YEAR(dim.month) = ?";
            $params[]     = $yearSel;
        }

        if ($deptSel !== 'all') {
            $conditions[] = "dim.dept_id = ?";
            $params[]     = (int)$deptSel;
        }

        $sql = "SELECT
        dim.snapshot_id,
        DATE_FORMAT(dim.month, '%Y-%m') AS month,
        d.dept_name,
        dim.task_file_path AS task_file_path,        -- ✅ task-specific path
        COALESCE(NULLIF(dim.custom_name, ''), di.name) AS indicator_name,
        di.description AS indicator_description,
        dim.target_value,
        dim.actual_value,
        dim.weight,
        dim.unit_of_goal,
        dim.unit,
        dim.way_of_measurement,
        dim.notes,
        u.name AS created_by_name,
        DATE(dim.created_at) AS created_date,
        dim.is_custom,
        dim.audit_score,
        CASE
            WHEN dim.actual_value IS NULL THEN 'Pending'
            WHEN dim.actual_value >= dim.target_value THEN 'Achieved'
            WHEN dim.actual_value >= dim.target_value * 0.8 THEN 'Partially Achieved'
            ELSE 'Not Achieved'
        END AS achievement_status,
        CASE
            WHEN dim.actual_value IS NULL THEN 0
            ELSE ROUND(dim.actual_value / dim.target_value * 100, 2)
        END AS achievement_percentage
    FROM department_indicator_monthly dim
    JOIN departments d ON d.dept_id = dim.dept_id
    LEFT JOIN department_indicators di ON di.indicator_id = dim.indicator_id
    LEFT JOIN users u ON u.user_id = dim.created_by
    WHERE 1=1";


        if ($conditions) {
            $sql .= ' AND ' . implode(' AND ', $conditions);
        }

        $sql .= " ORDER BY dim.month DESC, d.dept_name, dim.weight DESC, indicator_name";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $evaluations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Repository-provided summaries / incomplete lists
        $summaries = $repo->getDepartmentSummary(
            $monthSel === 'all' ? null : $monthSel,
            $deptSel === 'all' ? null : (int)$deptSel,
            $yearSel
        );

        if ($viewType === 'incomplete') {
            $incompleteData = $repo->getIncompleteDepartmentPlans(
                $monthSel === 'all' ? null : $monthSel,
                $deptSel === 'all' ? null : (int)$deptSel,
                $yearSel
            );
        }

    } elseif ($monthSel || $deptSel) {
        // One filter selected without the other
        $errorMessage = !$monthSel
            ? 'Please select a month to continue filtering.'
            : 'Please select a department to view results.';
    }

} catch (Throwable $e) {
    $errorMessage = 'An error occurred while loading the department audit data. Please try again.';
    error_log('Department audit load error: ' . $e->getMessage());
}

// (Keep the rest of your HTML / template exactly as it was.)
include __DIR__ . '/partials/navbar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Department Audit – SK-PM</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Merriweather&family=Playfair+Display&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="./assets/css/style.css">
  <style>
    /* Existing inline styles for achievement and summary visuals */
    .achievement-achieved { color: #198754; font-weight: 600; }
    .achievement-partial { color: #fd7e14; font-weight: 600; }
    .achievement-not-achieved { color: #dc3545; font-weight: 600; }
    .achievement-pending { color: #6c757d; font-weight: 600; }
    .progress-bar-achieved { background-color: #198754; }
    .progress-bar-partial { background-color: #fd7e14; }
    .progress-bar-not-achieved { background-color: #dc3545; }
    .summary-card { border-left: 4px solid #0d6efd; background: linear-gradient(135deg,#f8f9fa 0%,#e9ecef 100%); }
    .incomplete-indicator { color: #dc3545; font-weight: 500; }

    /* Page-specific layout adjustments */
    .copy-path-btn {
    background:#0d6efd;
    border-color:#0d6efd;
    color:#fff;
    font-weight:600;
}
.copy-path-btn:hover, .copy-path-btn:focus {
    background:#0b5ed7;
    border-color:#0b5ed7;
    color:#fff;
}

    .data-container {
    max-width:100% !important;
    width:100% !important;
    margin:0;
    padding:1.5rem 1.5rem 2rem;
    background:#fff;
}
    .audit-wide-wrapper {overflow-x:auto; width:100%;}
    table.audit-wide-table {min-width:1700px;}
    table.audit-wide-table th, table.audit-wide-table td {padding:0.85rem 1rem; white-space:nowrap;}
    table.audit-wide-table th.description-col, table.audit-wide-table td.description-col,
    table.audit-wide-table th.notes-col, table.audit-wide-table td.notes-col {white-space:normal; min-width:280px;}
    .score-buttons .score-btn.active {background:#000; color:#fff;}
    .copy-path-btn {min-width:60px;}
  </style>
</head>
<body class="bg-light font-serif">

  <div class="container-fluid py-4">
    <h1 class="mb-4">Department Performance Audit</h1>

    <!-- Filter selection error -->
    <?php if ($errorMessage): ?>
      <div class="alert alert-warning mb-4"><?= htmlspecialchars($errorMessage) ?></div>
    <?php endif; ?>

    <div class="data-container">

      <!-- Filters -->
      <form class="row g-3 mb-4" onsubmit="return false" id="filterForm">
        <div class="col-md-3">
          <label class="form-label">Year *</label>
          <select name="year" class="form-select" onchange="updateFilters()">
            <?php foreach ($years as $y): ?>
              <option value="<?= $y ?>" <?= $y == $yearSel ? 'selected' : '' ?>><?= $y ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Month *</label>
          <select name="month" class="form-select" onchange="updateFilters()">
            <option value="">Select Month</option>
            <?php if ($months): ?>
              <option value="all" <?= $monthSel === 'all' ? 'selected' : '' ?>>All Months</option>
              <?php foreach ($months as $m): ?>
                <option value="<?= $m ?>" <?= $m === $monthSel ? 'selected' : '' ?>>
                  <?= date('F Y', strtotime("$m-01")) ?>
                </option>
              <?php endforeach; ?>
            <?php endif; ?>
          </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">Department *</label>
            <select name="dept" class="form-select" onchange="updateFilters()" <?= !$monthSel ? 'disabled' : '' ?>>
              <option value="">Select Department</option>
              <?php if ($depts): ?>
                <option value="all" <?= $deptSel === 'all' ? 'selected' : '' ?>>All Departments</option>
                <?php foreach ($depts as $d): ?>
                  <option value="<?= $d['dept_id'] ?>" <?= $deptSel == $d['dept_id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($d['dept_name']) ?>
                  </option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
        </div>
        <div class="col-md-3">
          <label class="form-label">View *</label>
          <select name="view" class="form-select" onchange="updateFilters()" <?= (!$monthSel || !$deptSel) ? 'disabled' : '' ?>>
            <option value="summary" <?= $viewType === 'summary' ? 'selected' : '' ?>>Summary &amp; Details</option>
            <option value="incomplete" <?= $viewType === 'incomplete' ? 'selected' : '' ?>>Incomplete Plans Only</option>
          </select>
        </div>
      </form>

      <!-- Info counts -->
      <?php if (!empty($summaries) || !empty($incompleteData)): ?>
        <div class="alert alert-info mb-4">
          <?php if ($viewType === 'incomplete'): ?>
            Found <?= count($incompleteData) ?> department(s) with incomplete indicator plans.
          <?php else: ?>
            Found <?= count($summaries) ?> department performance summary(ies)
            and <?= count($evaluations) ?> detailed indicator evaluation(s).
          <?php endif; ?>
        </div>

        <!-- Incomplete table -->
        <?php if ($viewType === 'incomplete' && !empty($incompleteData)): ?>
          <section class="mb-4">
            <h4 class="text-danger mb-3">Incomplete Department Plans</h4>
            <div class="table-responsive">
              <table class="table table-striped table-bordered align-middle audit-wide-table">
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
                      <td><span class="badge bg-danger"><?= $data['incomplete_indicators'] ?></span></td>
                      <td><span class="incomplete-indicator"><?= htmlspecialchars($data['incomplete_indicator_names']) ?></span></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </section>
        <?php endif; ?>

        <!-- Summary cards -->
        <?php if ($viewType === 'summary' && !empty($summaries)): ?>
          <section class="mb-4">
            <h4 class="text-primary mb-3">Performance Summary</h4>
            <div class="row">
              <?php foreach ($summaries as $summary): ?>
                <div class="col-md-6 mb-3">
                  <div class="card summary-card h-100">
                    <div class="card-body">
                      <h5 class="card-title mb-1"><?= htmlspecialchars($summary['dept_name']) ?></h5>
                      <p class="card-text text-muted mb-3">
                        <?= date('F Y', strtotime($summary['month'] . '-01')) ?>
                      </p>
                      <?php
                        $achievedPct    = $summary['total_indicators'] ? ($summary['achieved_indicators'] / $summary['total_indicators']) * 100 : 0;
                        $partialPct     = $summary['total_indicators'] ? ($summary['partially_achieved_indicators'] / $summary['total_indicators']) * 100 : 0;
                        $notAchievedPct = $summary['total_indicators'] ? ($summary['not_achieved_indicators'] / $summary['total_indicators']) * 100 : 0;
                        $pendingPct     = $summary['total_indicators'] ? ($summary['pending_indicators'] / $summary['total_indicators']) * 100 : 0;
                      ?>
                      <div class="progress mb-2" style="height:18px;">
                        <div class="progress-bar progress-bar-achieved" style="width:<?= $achievedPct ?>%"></div>
                        <div class="progress-bar progress-bar-partial" style="width:<?= $partialPct ?>%"></div>
                        <div class="progress-bar progress-bar-not-achieved" style="width:<?= $notAchievedPct ?>%"></div>
                        <div class="progress-bar bg-secondary" style="width:<?= $pendingPct ?>%"></div>
                      </div>
                      <div class="d-flex justify-content-between small">
                        <span>Weighted: <strong><?= $summary['weighted_achievement_percentage'] ?? 0 ?>%</strong></span>
                        <span>Completion: <strong><?= $summary['completed_indicators'] ?>/<?= $summary['total_indicators'] ?></strong></span>
                      </div>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </section>
        <?php endif; ?>

        <!-- Detailed evaluations -->
        <?php if ($viewType === 'summary' && !empty($evaluations)): ?>
          <section class="mb-4">
            <h4 class="text-info mb-3">Detailed Indicator Evaluations</h4>
            <div class="table-responsive">
              <table class="table table-striped table-bordered align-middle audit-wide-table">
                <thead class="table-info">
                  <tr>
                    <th>Month</th>
                    <th>Department</th>
                    <th>Indicator</th>
                    <th class="description-col">Description</th>
                    <th>Target</th>
                    <th>Actual</th>
                    <th>Achievement</th>
                    <th>Status</th>
                    <th>Weight</th>
                    <th>Audit Score</th>
                    <th>Mark</th>
                    <th class="notes-col">Notes</th>
                    <th>Created By</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($evaluations as $eval): ?>
                    <?php
                      $savedScore = $eval['audit_score'];
                      $savedLabel = $savedScore === null ? '' :
                        ($savedScore == 5    ? 'Completed' :
                        ($savedScore == 2.5  ? 'Not Enough Data' :
                        ($savedScore == 0    ? 'Not Completed' : '')));
                    ?>
                    <tr data-snapshot-id="<?= $eval['snapshot_id'] ?>">
                      <td><?= date('F Y', strtotime($eval['month'] . '-01')) ?></td>
                      <td><?= htmlspecialchars($eval['dept_name']) ?></td>
                      <td>
                       <b> <?= htmlspecialchars($eval['indicator_name']) ?></b>
                        <?php if ($eval['is_custom']): ?><span class="badge bg-secondary ms-1">Custom</span><?php endif; ?>
                      </td>
                      <td class="description-col">
                        <?php
                          if (!empty($eval['indicator_description'])) {
                            $descFull = $eval['indicator_description'];
                            $firstPeriodPos = strpos($descFull, '.');
                            if ($firstPeriodPos !== false && $firstPeriodPos < strlen($descFull) - 1) {
                              $shortDesc = substr($descFull, 0, $firstPeriodPos + 1);
                            } elseif (strlen($descFull) > 100) {
                              $shortDesc = substr($descFull, 0, 100);
                              $lastSpace = strrpos($shortDesc, ' ');
                              if ($lastSpace !== false) $shortDesc = substr($shortDesc, 0, $lastSpace);
                              $shortDesc .= '.';
                            } else {
                              $shortDesc = $descFull;
                            }
                            echo htmlspecialchars($shortDesc);
                            if (strlen($shortDesc) < strlen($descFull)) {
                              echo ' <a href="#" class="read-more" data-full-text="' .
                                    htmlspecialchars($descFull, ENT_QUOTES) .
                                    '" data-title="Indicator Description">(read more)</a>';
                            }
                          } else {
                            echo '<em>No description</em>';
                          }
                        ?>
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
                          <div class="progress" style="height:20px;">
                            <div
                              class="progress-bar
                                <?= $eval['achievement_percentage'] >= 100
                                    ? 'progress-bar-achieved'
                                    : ($eval['achievement_percentage'] >= 80
                                        ? 'progress-bar-partial'
                                        : 'progress-bar-not-achieved') ?>"
                              style="width: <?= min($eval['achievement_percentage'], 100) ?>%;">
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
                      <td><span class="badge bg-info"><?= $eval['weight'] ?>%</span></td>

                      <!-- Audit Score Buttons -->
                      <td class="score-cell">
                        <div class="btn-group btn-group-sm w-100 flex-column score-buttons" role="group" aria-label="Audit Score">
                          <button type="button"
                                  class="btn btn-outline-dark score-btn"
                                  data-id="<?= $eval['snapshot_id'] ?>"
                                  data-score="5"
                                  data-label="Completed">
                            5 <small class="d-block text-muted">Completed</small>
                          </button>
                          <button type="button"
                                  class="btn btn-outline-dark score-btn"
                                  data-id="<?= $eval['snapshot_id'] ?>"
                                  data-score="2.5"
                                  data-label="Not Enough Data">
                            2.5 <small class="d-block text-muted">Not Enough Data</small>
                          </button>
                          <button type="button"
                                  class="btn btn-outline-dark score-btn"
                                  data-id="<?= $eval['snapshot_id'] ?>"
                                  data-score="0"
                                  data-label="Not Completed">
                            0 <small class="d-block text-muted">Not Completed</small>
                          </button>
                        </div>
                        <div class="score-selected small mt-1">
                          <?php if ($savedScore !== null): ?>
                            <?= htmlspecialchars($savedScore) ?> (<?= htmlspecialchars($savedLabel) ?>)
                          <?php endif; ?>
                        </div>
                      </td>
                      <td>
    <?php if (!empty($eval['task_file_path'])): ?>
        <button type="button"
                class="btn btn-sm btn-outline-primary copy-path-btn"
                data-path="<?= htmlspecialchars($eval['task_file_path'], ENT_QUOTES) ?>">
            Copy
        </button>
        <div class="copy-feedback small text-success mt-1 d-none">Copied!</div>
    <?php else: ?>
        <em>No path</em>
    <?php endif; ?>
</td>

                      <td class="notes-col">
                        <?php
                          $noteText = trim($eval['notes'] ?? '');
                          if ($noteText === '') {
                            echo '<em>No notes</em>';
                          } else {
                            $firstPeriod = strpos($noteText, '.');
                            if ($firstPeriod !== false && $firstPeriod < strlen($noteText) - 1) {
                              $shortNote = substr($noteText, 0, $firstPeriod + 1);
                            } elseif (strlen($noteText) > 100) {
                              $shortNote = substr($noteText, 0, 100);
                              $lastSpaceN = strrpos($shortNote, ' ');
                              if ($lastSpaceN !== false) $shortNote = substr($shortNote, 0, $lastSpaceN);
                              $shortNote .= '.';
                            } else {
                              $shortNote = $noteText;
                            }
                            echo htmlspecialchars($shortNote);
                            if (strlen($shortNote) < strlen($noteText)) {
                              echo ' <a href="#" class="read-more" data-full-text="' .
                                   htmlspecialchars($noteText, ENT_QUOTES) .
                                   '" data-title="Notes">(read more)</a>';
                            }
                          }
                        ?>
                      </td>
                      <td>
                        <?= htmlspecialchars($eval['created_by_name'] ?? 'Unknown') ?>
                        <?php if (!empty($eval['created_date'])): ?>
                          <small class="text-muted d-block">
                            <?= date('M j, Y', strtotime($eval['created_date'])) ?>
                          </small>
                        <?php endif; ?>
                      </td>
                  
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </section>
        <?php endif; ?>

      <?php else: ?>
        <?php if ($monthSel && $deptSel): ?>
          <div class="alert alert-secondary text-center py-4">
            No department evaluation records found for the selected criteria. Try adjusting your filters.
          </div>
        <?php endif; ?>
      <?php endif; ?>

    </div><!-- /.data-container -->
  </div><!-- /.container-fluid -->


  <!-- Success / Status Modal -->
  <!-- <div class="modal fade" id="auditStatusModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-2">
        <div class="modal-header bg-dark text-white py-2">
          <h5 class="modal-title">Status Updated</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="auditStatusModalBody">
   
        </div>
        <div class="modal-footer py-2">
          <button type="button" class="btn btn-dark btn-sm" data-bs-dismiss="modal">OK</button>
        </div>
      </div>
    </div>
  </div> -->

  <!-- Shared modal for full description / notes -->
  <!-- <div class="modal fade" id="fullContentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content border-2">
        <div class="modal-header">
          <h5 class="modal-title" id="fullContentModalLabel">Full Content</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="fullContentModalBody"></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div> -->

  <div class="modal fade" id="fullContentModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content border-2">
      <div class="modal-header">
        <h5 class="modal-title" id="fullContentModalLabel">Full Content</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="fullContentModalBody"></div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>



  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function updateFilters() {
      const form = document.getElementById('filterForm');
      const params = new URLSearchParams(new FormData(form));
      window.location.search = params.toString();
    }

    document.addEventListener('DOMContentLoaded', () => {
      // Highlight pre-existing scores
      document.querySelectorAll('tr[data-snapshot-id]').forEach(row => {
        const saved = row.querySelector('.score-selected');
        if (!saved) return;
        const txt = saved.textContent.trim();
        if (!txt) return;
        const numMatch = txt.match(/^(\d+(?:\.\d+)?)/);
        if (numMatch) {
          const btn = row.querySelector('.score-btn[data-score="' + numMatch[1] + '"]');
            if (btn) btn.classList.add('active');
        }
      });
    });

    // Event delegation for buttons & links
    document.addEventListener('click', function(e) {
      // Score buttons
      if (e.target.closest('.score-btn')) {
        const btn = e.target.closest('.score-btn');
        submitScore(btn);
      }

      // Copy path
      if (e.target.classList.contains('copy-path-btn')) {
        copyPath(e.target);
      }

      // Read more
      if (e.target.classList.contains('read-more')) {
        e.preventDefault();
        openFullContent(e.target);
      }
    });

    function submitScore(btn) {
      const id = btn.getAttribute('data-id');
      const score = btn.getAttribute('data-score');
      const label = btn.getAttribute('data-label');
      const cell = btn.closest('.score-cell');
      const group = cell.querySelector('.score-buttons');

      // Disable during request
      group.querySelectorAll('button').forEach(b => b.disabled = true);

      fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + encodeURIComponent(id) + '&score=' + encodeURIComponent(score)
      })
        .then(r => r.json())
        .then(data => {
          if (!data.success) throw new Error(data.message || 'Update failed');

group.querySelectorAll('button').forEach(b => b.classList.remove('active'));
btn.classList.add('active');

const indicator = cell.querySelector('.score-selected');
if (indicator) {
  indicator.textContent = data.score + ' (' + (data.score_label || btn.getAttribute('data-label')) + ')';
}

showAuditStatusModal('Audit score set to: <strong>' + (data.score_label || btn.getAttribute('data-label')) + '</strong>');

        })
        .catch(err => {
          showAlert(err.message || 'Unexpected error.');
        })
        .finally(() => {
          group.querySelectorAll('button').forEach(b => b.disabled = false);
        });
    }

    function showAuditStatusModal(html, title = 'Status Updated') {
  const modalEl   = document.getElementById('fullContentModal');
  const titleEl   = document.getElementById('fullContentModalLabel');
  const bodyEl    = document.getElementById('fullContentModalBody');

  if (titleEl) titleEl.textContent = title;
  if (bodyEl)  bodyEl.innerHTML = html;

  const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
  modal.show();
}

    function openFullContent(link) {
      const full = link.getAttribute('data-full-text') || '';
      const title = link.getAttribute('data-title') || 'Details';
      document.getElementById('fullContentModalLabel').textContent = title;
      document.getElementById('fullContentModalBody').textContent = full;
      bootstrap.Modal.getOrCreateInstance(document.getElementById('fullContentModal')).show();
    }

    function copyPath(btn) {
      const text = btn.getAttribute('data-path');
      const feedback = btn.parentElement.querySelector('.copy-feedback');
      const done = () => {
        if (feedback) {
          feedback.classList.remove('d-none');
          setTimeout(() => feedback.classList.add('d-none'), 1500);
        }
      };
      if (navigator.clipboard?.writeText) {
        navigator.clipboard.writeText(text).then(done).catch(done);
      } else {
        const ta = document.createElement('textarea');
        ta.value = text;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        document.body.removeChild(ta);
        done();
      }
    }

    function showAlert(msg) {
      const alert = document.createElement('div');
      alert.className = 'alert alert-danger';
      alert.textContent = msg;
      const container = document.querySelector('.data-container');
      container.prepend(alert);
      setTimeout(() => alert.remove(), 5000);
    }
  </script>
</body>

</html>
