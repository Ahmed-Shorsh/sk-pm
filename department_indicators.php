<?php

declare(strict_types=1);
/* Department Indicators Management */
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/utils.php';
require_once __DIR__ . '/backend/indicator_controller.php';
require_once __DIR__ . '/backend/department_controller.php';

secureSessionStart();
checkLogin();
if (($_SESSION['role_id'] ?? null) !== 1) {
  header('HTTP/1.1 403 Forbidden');
  echo '<h1>Access Denied</h1>';
  exit;
}

use Backend\IndicatorRepository;
use Backend\DepartmentRepository;

$indicatorRepo = new IndicatorRepository($pdo);
$deptRepo      = new DepartmentRepository($pdo);

$flash = ['msg' => '', 'type' => 'success'];

function inPost(string $k): string
{
  return trim($_POST[$k] ?? '');
}
function deptCsv($arr): ?string
{
  if (!$arr) return null;
  $ids = array_unique(array_filter(array_map('intval', (array)$arr)));
  return $ids ? implode(',', $ids) : null;
}
function fetchDeptIndicator(PDO $pdo, int $id): ?array
{
  $st = $pdo->prepare('SELECT * FROM department_indicators WHERE indicator_id=?');
  $st->execute([$id]);
  return $st->fetch(PDO::FETCH_ASSOC) ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  try {
    if ($action === 'create' || $action === 'update') {
      $id    = $action === 'update' ? (int)($_POST['indicator_id'] ?? 0) : 0;
      $name  = inPost('name');
      $goal  = inPost('default_goal');
      $sort  = inPost('sort_order');
      $desc  = inPost('description');
      $uGoal = inPost('unit_of_goal');
      $weight = inPost('default_weight');
      $way   = inPost('way_of_measurement');
      $deptS = deptCsv($_POST['responsible_departments'] ?? []);

      $errors = [];
      if ($name === '') $errors[] = 'Name is required.';
      if ($goal === '') $errors[] = 'Default Goal is required.';
      if ($sort === '') $errors[] = 'Sort Order is required.';
      if ($goal !== '' && !is_numeric($goal)) $errors[] = 'Default Goal must be numeric.';
      if ($sort !== '' && (!ctype_digit($sort) || (int)$sort < 0)) $errors[] = 'Sort Order must be a non-negative integer.';
      if ($weight !== '' && !ctype_digit(ltrim($weight, '-'))) $errors[] = 'Weight must be an integer.';
      if ($action === 'update' && !$id) $errors[] = 'Missing indicator id.';
      if ($action === 'update' && $id && !fetchDeptIndicator($pdo, $id)) $errors[] = 'Indicator not found (refresh).';
      if (mb_strlen($name) > 255) $errors[] = 'Name too long (max 255).';
      if ($uGoal !== '' && mb_strlen($uGoal) > 50) $errors[] = 'Unit of Goal too long (max 50).';
      if ($way !== '' && mb_strlen($way) > 255) $errors[] = 'Way of Measurement too long (max 255).';

      if ($errors) {
        $flash = ['msg' => implode('<br>', $errors), 'type' => 'danger'];
      } else {
        $data = [
          'name'                   => sanitize($name),
          'description'            => sanitize($desc),
          'responsible_departments' => $deptS,
          'default_goal'           => (float)$goal,
          'unit_of_goal'           => sanitize($uGoal),
          'unit'                   => null,
          'way_of_measurement'     => sanitize($way),
          'default_weight'         => ($weight === '') ? null : (int)$weight,
          'sort_order'             => (int)$sort
        ];
        if ($action === 'create') {
          $data['active'] = 1;
          $indicatorRepo->createDepartmentIndicator($data);
          $flash = ['msg' => 'Department KPI created.', 'type' => 'success'];
        } else {
          $data['active'] = isset($_POST['active']) ? 1 : 0;
          $indicatorRepo->updateDepartmentIndicator($id, $data);
          $flash = ['msg' => 'Department KPI updated.', 'type' => 'success'];
        }
        redirect('department_indicators.php');
      }
    } elseif ($action === 'toggle') {
      $id = (int)($_POST['indicator_id'] ?? 0);
      $cur = fetchDeptIndicator($pdo, $id);
      if (!$cur) {
        $flash = ['msg' => 'Indicator not found.', 'type' => 'danger'];
      } else {
        $indicatorRepo->updateDepartmentIndicator($id, [
          'name'                   => $cur['name'],
          'description'            => $cur['description'],
          'responsible_departments' => $cur['responsible_departments'],
          'default_goal'           => (float)$cur['default_goal'],
          'unit_of_goal'           => $cur['unit_of_goal'],
          'unit'                   => null,
          'way_of_measurement'     => $cur['way_of_measurement'],
          'default_weight'         => $cur['default_weight'],
          'sort_order'             => (int)$cur['sort_order'],
          'active'                 => $cur['active'] ? 0 : 1
        ]);
        $flash = ['msg' => 'Status changed.', 'type' => 'info'];
      }
      redirect('department_indicators.php');
    }
  } catch (Exception $e) {
    $flash = ['msg' => 'Error: ' . htmlspecialchars($e->getMessage()), 'type' => 'danger'];
    redirect('department_indicators.php');
  }
}

$indicators      = $indicatorRepo->fetchDepartmentIndicators(false);
$departmentsList = $deptRepo->fetchAllDepartments();

include __DIR__ . '/partials/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Department Indicators – SK-PM</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-9ndCyUa6mY2Hl2c53v9FRR0z0rsEkR3O89E+9aZ1OgGvJvH+0hZ5P2x0ZKKb4L1p"
    crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="./assets/css/style.css">
  <link rel="icon" href="./assets/logo/sk-n.ico" type="image/x-icon">
  <style>
    body.font-serif {
      font-family: 'Merriweather', serif
    }

    .table td {
      vertical-align: top
    }

    .desc-wrap .full {
      display: none
    }

    .desc-wrap.expanded .short {
      display: none
    }

    .desc-wrap.expanded .full {
      display: inline
    }

    .desc-toggle {
      font-size: .75rem;
      margin-left: .25rem;
      text-decoration: none
    }

    .custom-multiselect {
      position: relative
    }

    .multiselect-dropdown {
      position: relative;
      border: 1px solid #ced4da;
      border-radius: .375rem;
      background: #fff;
      min-height: 42px;
      cursor: pointer;
      padding: 6px 10px;
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 4px
    }

    .multiselect-dropdown:focus-within {
      outline: 2px solid rgba(13, 110, 253, .4)
    }

    .multiselect-placeholder {
      color: #6c757d
    }

    .selected-item {
      background: #212529;
      color: #fff;
      border-radius: 14px;
      padding: 2px 8px;
      font-size: .7rem;
      display: flex;
      align-items: center;
      gap: 4px
    }

    .selected-item .remove-item {
      cursor: pointer;
      font-weight: bold
    }

    .dropdown-arrow {
      margin-left: auto
    }

    .multiselect-options {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: #fff;
      border: 1px solid #ced4da;
      border-top: none;
      border-radius: 0 0 .375rem .375rem;
      max-height: 240px;
      overflow-y: auto;
      z-index: 50;
      display: none
    }

    .multiselect-options.show {
      display: block
    }

    .multiselect-search {
      border: none;
      border-bottom: 1px solid #ddd;
      width: 100%;
      padding: .45rem .6rem;
      outline: none;
      font-size: .85rem
    }

    .multiselect-option {
      padding: .4rem .65rem;
      display: flex;
      align-items: center;
      gap: .5rem;
      cursor: pointer;
      font-size: .85rem
    }

    .multiselect-option:hover {
      background: #f8f9fa
    }

    .multiselect-option.selected {
      background: #e9ecef
    }
  </style>
</head>

<body class="font-serif bg-light text-dark">
  <header class="data-container text-center py-4">
    <h1>Department KPI Management</h1>
    <p class="text-muted">Create, edit or deactivate departmental KPIs (soft toggle).</p>
  </header>

  <main class="data-container mb-5">
    <?php if ($flash['msg']) flashMessage($flash['msg'], $flash['type']); ?>

    <div class="text-end mb-3">
      <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#createModal" id="openCreate">+ Add KPI</button>
    </div>

    <div class="table-responsive">
      <table class="table table-striped table-bordered align-middle">
        <thead class="table-dark">
          <tr>
            <th>#</th>
            <th>Name & Description</th>
            <th>Goal</th>
            <th>Unit of Goal</th>
            <th>Weight</th>
            <th>Sort</th>
            <th>Active</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($indicators as $ind): ?>
            <?php
            $full = trim((string)$ind['description']);
            $short = $full;
            $more = false;
            if ($full !== '') {
              $sentences = preg_split('/(?<=[.!?])\s+/', $full);
              if ($sentences && count($sentences) > 2) {
                $more = true;
                $short = implode(' ', array_slice($sentences, 0, 2));
              }
            }
            ?>
            <tr class="<?= $ind['active'] ? '' : 'table-danger' ?>">
              <td><?= $ind['indicator_id'] ?></td>
              <td>
                <strong><?= htmlspecialchars($ind['name']) ?></strong>
                <?php if ($full !== ''): ?>
                  <div class="text-muted small desc-wrap">
                    <span class="short"><?= htmlspecialchars($short) ?></span>
                    <span class="full"><?= htmlspecialchars($full) ?></span>
                    <?php if ($more): ?><a href="#" class="desc-toggle">(read more)</a><?php endif; ?>
                  </div>
                <?php endif; ?>
              </td>
              <td><?= htmlspecialchars((string)$ind['default_goal']) ?></td>
              <td><?= htmlspecialchars((string)$ind['unit_of_goal']) ?></td>
              <td><?= htmlspecialchars((string)$ind['default_weight']) ?></td>
              <td><?= htmlspecialchars((string)$ind['sort_order']) ?></td>
              <td><span class="badge bg-<?= $ind['active'] ? 'success' : 'secondary' ?>"><?= $ind['active'] ? 'Yes' : 'No' ?></span></td>
              <td class="text-center">
                <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editModal<?= $ind['indicator_id'] ?>">Edit</button>
                <form method="post" class="d-inline" onsubmit="return confirm('Change status?');">
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="indicator_id" value="<?= $ind['indicator_id'] ?>">
                  <button class="btn btn-sm btn-outline-<?= $ind['active'] ? 'danger' : 'success' ?>"><?= $ind['active'] ? 'Disable' : 'Enable' ?></button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </main>

  <?php foreach ($indicators as $ind):
    $selected = [];
    if (!empty($ind['responsible_departments'])) {
      $selected = array_map('intval', explode(',', $ind['responsible_departments']));
    }
  ?>
    <div class="modal fade" id="editModal<?= $ind['indicator_id'] ?>" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form method="post" class="modal-content">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="indicator_id" value="<?= $ind['indicator_id'] ?>">
          <div class="modal-header">
            <h5 class="modal-title">Edit KPI #<?= $ind['indicator_id'] ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-8">
                <label class="form-label">Name<span class="text-danger">*</span></label>
                <input type="text" name="name" required class="form-control" value="<?= htmlspecialchars($ind['name']) ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label">Sort Order<span class="text-danger">*</span></label>
                <input type="number" name="sort_order" required class="form-control" value="<?= (int)$ind['sort_order'] ?>">
              </div>
              <div class="col-12">
                <label class="form-label">Description</label>
                <textarea name="description" rows="3" class="form-control"><?= htmlspecialchars($ind['description']) ?></textarea>
              </div>
              <div class="col-12">
                <label class="form-label">Responsible Departments</label>
                <div class="custom-multiselect" data-name="responsible_departments[]">
                  <div class="multiselect-dropdown" tabindex="0">
                    <span class="multiselect-placeholder">Select departments...</span>
                    <i class="bi bi-chevron-down dropdown-arrow ms-auto"></i>
                  </div>
                  <div class="multiselect-options">
                    <input type="text" class="multiselect-search" placeholder="Search departments...">
                    <?php foreach ($departmentsList as $d):
                      $isSel = in_array((int)$d['dept_id'], $selected, true); ?>
                      <div class="multiselect-option<?= $isSel ? ' selected' : '' ?>" data-value="<?= $d['dept_id'] ?>" data-selected="<?= $isSel ? 'true' : 'false' ?>">
                        <input type="checkbox" <?= $isSel ? 'checked' : '' ?>>
                        <span><?= htmlspecialchars($d['dept_name']) ?></span>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <label class="form-label">Default Goal<span class="text-danger">*</span></label>
                <input type="number" step="0.01" name="default_goal" required class="form-control" value="<?= htmlspecialchars((string)$ind['default_goal']) ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Unit of Goal</label>
                <input type="text" name="unit_of_goal" class="form-control" value="<?= htmlspecialchars((string)$ind['unit_of_goal']) ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Weight</label>
                <input type="number" name="default_weight" class="form-control" value="<?= htmlspecialchars((string)$ind['default_weight']) ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Way of Measurement</label>
                <input type="text" name="way_of_measurement" class="form-control" value="<?= htmlspecialchars((string)$ind['way_of_measurement']) ?>">
              </div>
              <div class="col-md-3 d-flex align-items-center">
                <div class="form-check mt-4">
                  <input class="form-check-input" type="checkbox" name="active" <?= $ind['active'] ? 'checked' : '' ?>>
                  <label class="form-check-label">Active</label>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-dark">Save</button>
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="modal fade" id="createModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <form method="post" class="modal-content">
        <input type="hidden" name="action" value="create">
        <div class="modal-header">
          <h5 class="modal-title">Add Department KPI</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-8">
              <label class="form-label">Name<span class="text-danger">*</span></label>
              <input type="text" name="name" required class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">Sort Order<span class="text-danger">*</span></label>
              <input type="number" name="sort_order" required class="form-control" value="0">
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea name="description" rows="3" class="form-control"></textarea>
            </div>
            <div class="col-12">
              <label class="form-label">Responsible Departments</label>
              <div class="custom-multiselect" data-name="responsible_departments[]">
                <div class="multiselect-dropdown" tabindex="0">
                  <span class="multiselect-placeholder">Select departments...</span>
                  <i class="bi bi-chevron-down dropdown-arrow ms-auto"></i>
                </div>
                <div class="multiselect-options">
                  <input type="text" class="multiselect-search" placeholder="Search departments...">
                  <?php foreach ($departmentsList as $d): ?>
                    <div class="multiselect-option" data-value="<?= $d['dept_id'] ?>" data-selected="false">
                      <input type="checkbox">
                      <span><?= htmlspecialchars($d['dept_name']) ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <label class="form-label">Default Goal<span class="text-danger">*</span></label>
              <input type="number" step="0.01" name="default_goal" required class="form-control">
            </div>
            <div class="col-md-3">
              <label class="form-label">Unit of Goal</label>
              <input type="text" name="unit_of_goal" class="form-control">
            </div>
            <div class="col-md-3">
              <label class="form-label">Weight</label>
              <input type="number" name="default_weight" class="form-control">
            </div>
            <div class="col-md-3">
              <label class="form-label">Way of Measurement</label>
              <input type="text" name="way_of_measurement" class="form-control">
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-dark">Create</button>
        </div>
      </form>
    </div>
  </div>

  <footer class="footer py-3 text-center">
    <small>&copy; <?= date('Y') ?> SK-PM Performance Management</small>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz"
    crossorigin="anonymous"></script>
  <script>
    /* Modal fallback (in case data API suppressed) */
    document.addEventListener('click', e => {
      if (e.target.matches('[data-bs-toggle="modal"]')) {
        if (window.bootstrap) {
          const sel = e.target.getAttribute('data-bs-target');
          const el = document.querySelector(sel);
          if (el) bootstrap.Modal.getOrCreateInstance(el).show();
        }
      }
    });

    /* Read more */
    document.addEventListener('click', e => {
      if (e.target.classList.contains('desc-toggle')) {
        e.preventDefault();
        const wrap = e.target.closest('.desc-wrap');
        wrap.classList.toggle('expanded');
        e.target.textContent = wrap.classList.contains('expanded') ? '(show less)' : '(read more)';
      }
    });

    /* Custom Multi-Select */
    (function() {
      function init(ms) {
        try {
          const dropdown = ms.querySelector('.multiselect-dropdown');
          const options = ms.querySelector('.multiselect-options');
          const search = ms.querySelector('.multiselect-search');
          const placeholder = ms.querySelector('.multiselect-placeholder');
          const nameAttr = ms.dataset.name;

          function ensureHiddenInputs() {
            ms.querySelectorAll('input[type="hidden"]').forEach(h => h.remove());
            ms.querySelectorAll('.multiselect-option[data-selected="true"]').forEach(opt => {
              const hid = document.createElement('input');
              hid.type = 'hidden';
              hid.name = nameAttr;
              hid.value = opt.dataset.value;
              ms.appendChild(hid);
            });
          }

          function renderChips() {
            dropdown.querySelectorAll('.selected-item').forEach(ch => ch.remove());
            const selected = ms.querySelectorAll('.multiselect-option[data-selected="true"]');
            if (selected.length === 0) {
              placeholder.style.display = '';
            } else {
              placeholder.style.display = 'none';
              selected.forEach(opt => {
                const chip = document.createElement('span');
                chip.className = 'selected-item';
                chip.dataset.value = opt.dataset.value;
                chip.innerHTML = '<span>' + opt.querySelector('span').textContent + '</span><span class="remove-item">&times;</span>';
                chip.querySelector('.remove-item').addEventListener('click', ev => {
                  ev.stopPropagation();
                  opt.dataset.selected = 'false';
                  opt.classList.remove('selected');
                  const ck = opt.querySelector('input[type="checkbox"]');
                  ck.checked = false;
                  ensureHiddenInputs();
                  renderChips();
                });
                dropdown.insertBefore(chip, dropdown.querySelector('.dropdown-arrow'));
              });
            }
          }
          dropdown.addEventListener('click', e => {
            if (!e.target.classList.contains('remove-item')) {
              options.classList.toggle('show');
              if (options.classList.contains('show')) {
                search && search.focus();
              }
            }
          });
          document.addEventListener('click', e => {
            if (!ms.contains(e.target)) options.classList.remove('show');
          });
          if (search) {
            search.addEventListener('input', () => {
              const q = search.value.toLowerCase();
              ms.querySelectorAll('.multiselect-option').forEach(opt => {
                const t = opt.querySelector('span').textContent.toLowerCase();
                opt.style.display = t.includes(q) ? 'flex' : 'none';
              });
            });
          }
          ms.querySelectorAll('.multiselect-option').forEach(opt => {
            const ck = opt.querySelector('input[type="checkbox"]');
            if (opt.dataset.selected === 'true') {
              ck.checked = true;
              opt.classList.add('selected');
            }
            ck.addEventListener('change', () => {
              opt.dataset.selected = ck.checked ? 'true' : 'false';
              opt.classList.toggle('selected', ck.checked);
              ensureHiddenInputs();
              renderChips();
            });
            opt.addEventListener('click', e => {
              if (e.target.tagName === 'INPUT') return;
              ck.checked = !ck.checked;
              ck.dispatchEvent(new Event('change'));
            });
          });
          ensureHiddenInputs();
          renderChips();
        } catch (err) {
          console.error('Multi-select init error', err);
        }
      }
      document.querySelectorAll('.custom-multiselect').forEach(init);
    })();

    /* Debug */
    if (typeof bootstrap === 'undefined') {
      console.warn('Bootstrap JS not detected; modals will not open.');
    }
  </script>
</body>

</html>