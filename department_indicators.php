<?php
declare(strict_types=1);
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/utils.php';
require_once __DIR__ . '/backend/indicator_controller.php';
require_once __DIR__ . '/backend/department_controller.php';   // ← add this line

secureSessionStart();
checkLogin();
if ($_SESSION['role_id'] !== 1) {
    header('HTTP/1.1 403 Forbidden');
    echo '<h1>Access Denied</h1>';
    exit;
}

use Backend\IndicatorRepository;
$indicatorRepo = new IndicatorRepository($pdo);

$flash = ['msg'=>'', 'type'=>'success'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        try {
            $indicatorRepo->createDepartmentIndicator([
                'name'                   => sanitize($_POST['name']),
                'description'            => sanitize($_POST['description']),
                'responsible_departments' =>
    isset($_POST['responsible_departments'])
        ? implode(',', array_map('intval', $_POST['responsible_departments']))
        : null,
                'default_goal'           => (float)$_POST['default_goal'],
                'unit_of_goal'           => sanitize($_POST['unit_of_goal']),
                'unit'                   => sanitize($_POST['unit']),
                'way_of_measurement'     => sanitize($_POST['way_of_measurement']),
                'default_weight'         => $_POST['default_weight']==='' ? null : (int)$_POST['default_weight'],
                'sort_order'             => (int)$_POST['sort_order'],
            ]);
            $flash = ['msg'=>'Department indicator created.','type'=>'success'];
        } catch (Exception $e) {
            $flash = ['msg'=>'Create failed: '.$e->getMessage(),'type'=>'danger'];
        }
        redirect('department_indicators.php');
    }

    if ($action === 'update') {
        $id = (int)$_POST['indicator_id'];
        try {
            $indicatorRepo->updateDepartmentIndicator($id, [
                'name'                   => sanitize($_POST['name']),
                'description'            => sanitize($_POST['description']),
                'responsible_departments' =>
    isset($_POST['responsible_departments'])
        ? implode(',', array_map('intval', $_POST['responsible_departments']))
        : null,
                'default_goal'           => (float)$_POST['default_goal'],
                'unit_of_goal'           => sanitize($_POST['unit_of_goal']),
                'unit'                   => sanitize($_POST['unit']),
                'way_of_measurement'     => sanitize($_POST['way_of_measurement']),
                'default_weight'         => $_POST['default_weight']==='' ? null : (int)$_POST['default_weight'],
                'sort_order'             => (int)$_POST['sort_order'],
                'active'                 => isset($_POST['active']) ? 1 : 0,
            ]);
            $flash = ['msg'=>'Indicator updated.','type'=>'success'];
        } catch (Exception $e) {
            $flash = ['msg'=>'Update error: '.$e->getMessage(),'type'=>'danger'];
        }
        redirect('department_indicators.php');
    }

    if ($action === 'toggle') {
        $id     = (int)$_POST['indicator_id'];
        $active = (int)$_POST['current_active'] ? 0 : 1;
        $indicatorRepo->updateDepartmentIndicator($id, ['active'=>$active,'name'=>'']);
        $flash = ['msg'=>'Status changed.','type'=>'info'];
        redirect('department_indicators.php');
    }
}

$indicators = $indicatorRepo->fetchDepartmentIndicators(false);

use Backend\DepartmentRepository;
$deptRepo         = new DepartmentRepository($pdo);
$departmentsList  = $deptRepo->fetchAllDepartments(); 

include __DIR__ . '/partials/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Department Indicators – SK-PM</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Merriweather&family=Playfair+Display&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="./assets/css/style.css">
<link rel="icon" href="./assets/logo/sk-n.ico" type="image/x-icon">
<style>
body.font-serif{font-family:'Merriweather',serif}
.btn{opacity:1!important}
.modal-content{background:#fff}

/* Custom Multi-Select Styles */
.custom-multiselect {
    position: relative;
}

.multiselect-dropdown {
    position: relative;
    border: 1px solid #ced4da;
    border-radius: 0.375rem;
    background-color: #fff;
    min-height: 38px;
    cursor: pointer;
    padding: 6px 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.multiselect-dropdown:focus-within {
    border-color: #86b7fe;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
}

.selected-items {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
    flex: 1;
    min-height: 24px;
    align-items: center;
}

.selected-item {
    background-color: #0d6efd;
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 0.875rem;
    display: flex;
    align-items: center;
    gap: 4px;
}

.selected-item .remove-item {
    cursor: pointer;
    font-weight: bold;
    padding: 0 2px;
    border-radius: 50%;
    background: rgba(255,255,255,0.3);
}

.selected-item .remove-item:hover {
    background: rgba(255,255,255,0.5);
}

.multiselect-placeholder {
    color: #6c757d;
    font-size: 1rem;
}

.dropdown-arrow {
    margin-left: 8px;
    transition: transform 0.2s;
}

.dropdown-arrow.open {
    transform: rotate(180deg);
}

.multiselect-options {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ced4da;
    border-top: none;
    border-radius: 0 0 0.375rem 0.375rem;
    max-height: 200px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
}

.multiselect-options.show {
    display: block;
}

.multiselect-search {
    padding: 8px 12px;
    border: none;
    border-bottom: 1px solid #ced4da;
    width: 100%;
    outline: none;
}

.multiselect-option {
    padding: 8px 12px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
}

.multiselect-option:hover {
    background-color: #f8f9fa;
}

.multiselect-option input[type="checkbox"] {
    margin: 0;
}

.multiselect-option.selected {
    background-color: #e3f2fd;
}
</style>

<script src="https://code.jquery.com/jquery-3.6.0.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>

</head>
<body class="font-serif bg-light text-dark">

<header class="data-container text-center py-4">
  <h1>Department KPI Management</h1>
  <p class="text-muted">Create, edit or deactivate KPIs that apply to departments.</p>
</header>

<main class="data-container mb-5">
  <?php if ($flash['msg']): flashMessage($flash['msg'],$flash['type']); endif; ?>

  <div class="text-end mb-3">
    <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#createModal">+ Add KPI</button>
  </div>

  <div class="table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead class="table-dark">
        <tr>
          <th>#</th><th>Name</th><th>Goal</th><th>Unit</th><th>Weight</th><th>Sort</th><th>Active</th><th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($indicators as $ind): ?>
        <tr class="<?= $ind['active'] ? '' : 'table-danger' ?>">
          <td><?= $ind['indicator_id'] ?></td>
          <td><?= htmlspecialchars($ind['name']) ?></td>
          <td><?= $ind['default_goal'] ?></td>
          <td><?= htmlspecialchars($ind['unit'] ?? '') ?></td>
          <td><?= $ind['default_weight'] ?></td>
          <td><?= $ind['sort_order'] ?></td>
          <td><span class="badge bg-<?= $ind['active']?'success':'secondary' ?>"><?= $ind['active']?'Yes':'No' ?></span></td>
          <td class="text-center">
            <button class="btn btn-sm btn-outline-secondary me-1" data-bs-toggle="modal" data-bs-target="#editModal<?= $ind['indicator_id'] ?>">Edit</button>
            <form class="d-inline" method="post" onsubmit="return confirm('Change status?');">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="indicator_id" value="<?= $ind['indicator_id'] ?>">
              <input type="hidden" name="current_active" value="<?= $ind['active'] ?>">
              <button class="btn btn-sm btn-outline-<?= $ind['active']?'danger':'success' ?>"><?= $ind['active']?'Disable':'Enable' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</main>

<?php foreach ($indicators as $ind): ?>
<div class="modal fade" id="editModal<?= $ind['indicator_id'] ?>" tabindex="-1">
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
            <input type="number" name="sort_order" required class="form-control" value="<?= $ind['sort_order'] ?>">
          </div>

          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="description" rows="3" class="form-control"><?= htmlspecialchars($ind['description']) ?></textarea>
          </div>

          <?php $selected = array_map('intval', explode(',', $ind['responsible_departments'] ?? '')); ?>
          <div class="col-12">
            <label class="form-label">Responsible Departments</label>
            <div class="custom-multiselect" data-name="responsible_departments[]">
              <div class="multiselect-dropdown" tabindex="0">
                <div class="selected-items">
                  <span class="multiselect-placeholder">Select departments...</span>
                </div>
                <i class="bi bi-chevron-down dropdown-arrow"></i>
              </div>
              <div class="multiselect-options">
                <input type="text" class="multiselect-search" placeholder="Search departments...">
                <?php foreach ($departmentsList as $d): ?>
                  <div class="multiselect-option" data-value="<?= $d['dept_id'] ?>" <?= in_array($d['dept_id'], $selected) ? 'data-selected="true"' : '' ?>>
                    <input type="checkbox" <?= in_array($d['dept_id'], $selected) ? 'checked' : '' ?>>
                    <span><?= htmlspecialchars($d['dept_name']) ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="col-md-3">
            <label class="form-label">Default Goal</label>
            <input type="number" step="0.01" name="default_goal" class="form-control" value="<?= $ind['default_goal'] ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Unit of Goal</label>
            <input type="text" name="unit_of_goal" class="form-control" value="<?= htmlspecialchars($ind['unit_of_goal']) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Unit</label>
            <input type="text" name="unit" class="form-control" value="<?= htmlspecialchars($ind['unit']) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Weight</label>
            <input type="number" name="default_weight" class="form-control" value="<?= $ind['default_weight'] ?>">
          </div>

          <div class="col-12">
            <label class="form-label">Way of Measurement</label>
            <input type="text" name="way_of_measurement" class="form-control" value="<?= htmlspecialchars($ind['way_of_measurement']) ?>">
          </div>

          <div class="col-md-3 d-flex align-items-center">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" name="active" <?= $ind['active']?'checked':'' ?>>
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

<div class="modal fade" id="createModal" tabindex="-1">
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
                <div class="selected-items">
                  <span class="multiselect-placeholder">Select departments...</span>
                </div>
                <i class="bi bi-chevron-down dropdown-arrow"></i>
              </div>
              <div class="multiselect-options">
                <input type="text" class="multiselect-search" placeholder="Search departments...">
                <?php foreach ($departmentsList as $d): ?>
                  <div class="multiselect-option" data-value="<?= $d['dept_id'] ?>">
                    <input type="checkbox">
                    <span><?= htmlspecialchars($d['dept_name']) ?></span>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <div class="col-md-3">
            <label class="form-label">Default Goal</label>
            <input type="number" step="0.01" name="default_goal" class="form-control">
          </div>
          <div class="col-md-3">
            <label class="form-label">Unit of Goal</label>
            <input type="text" name="unit_of_goal" class="form-control">
          </div>
          <div class="col-md-3">
            <label class="form-label">Unit</label>
            <input type="text" name="unit" class="form-control">
          </div>
          <div class="col-md-3">
            <label class="form-label">Weight</label>
            <input type="number" name="default_weight" class="form-control">
          </div>

          <div class="col-12">
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

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize all multi-select dropdowns
    document.querySelectorAll('.custom-multiselect').forEach(initMultiSelect);
    
    function initMultiSelect(container) {
        const dropdown = container.querySelector('.multiselect-dropdown');
        const options = container.querySelector('.multiselect-options');
        const selectedItems = container.querySelector('.selected-items');
        const placeholder = container.querySelector('.multiselect-placeholder');
        const arrow = container.querySelector('.dropdown-arrow');
        const searchInput = container.querySelector('.multiselect-search');
        const fieldName = container.dataset.name;
        
        let isOpen = false;
        
        // Initialize pre-selected items
        initializeSelected();
        
        // Toggle dropdown
        dropdown.addEventListener('click', function(e) {
            e.stopPropagation();
            toggleDropdown();
        });
        
        // Search functionality
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const optionItems = container.querySelectorAll('.multiselect-option');
            
            optionItems.forEach(option => {
                const text = option.querySelector('span').textContent.toLowerCase();
                option.style.display = text.includes(searchTerm) ? 'flex' : 'none';
            });
        });
        
        // Handle option selection
        container.addEventListener('change', function(e) {
            if (e.target.type === 'checkbox') {
                handleOptionChange(e.target);
            }
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!container.contains(e.target)) {
                closeDropdown();
            }
        });
        
        function toggleDropdown() {
            if (isOpen) {
                closeDropdown();
            } else {
                openDropdown();
            }
        }
        
        function openDropdown() {
            options.classList.add('show');
            arrow.classList.add('open');
            isOpen = true;
            searchInput.focus();
        }
        
        function closeDropdown() {
            options.classList.remove('show');
            arrow.classList.remove('open');
            isOpen = false;
            searchInput.value = '';
            // Reset search results
            container.querySelectorAll('.multiselect-option').forEach(option => {
                option.style.display = 'flex';
            });
        }
        
        function initializeSelected() {
            container.querySelectorAll('.multiselect-option[data-selected="true"]').forEach(option => {
                const checkbox = option.querySelector('input[type="checkbox"]');
                const value = option.dataset.value;
                const text = option.querySelector('span').textContent;
                
                checkbox.checked = true;
                option.classList.add('selected');
                addSelectedItem(value, text);
            });
            updatePlaceholder();
        }
        
        function handleOptionChange(checkbox) {
            const option = checkbox.closest('.multiselect-option');
            const value = option.dataset.value;
            const text = option.querySelector('span').textContent;
            
            if (checkbox.checked) {
                option.classList.add('selected');
                addSelectedItem(value, text);
            } else {
                option.classList.remove('selected');
                removeSelectedItem(value);
            }
            
            updatePlaceholder();
        }
        
        function addSelectedItem(value, text) {
            // Check if already exists
            if (selectedItems.querySelector(`[data-value="${value}"]`)) return;
            
            const item = document.createElement('div');
            item.className = 'selected-item';
            item.dataset.value = value;
            item.innerHTML = `
                <span>${text}</span>
                <span class="remove-item" data-value="${value}">&times;</span>
                <input type="hidden" name="${fieldName}" value="${value}">
            `;
            
            // Insert before placeholder
            selectedItems.insertBefore(item, placeholder);
            
            // Add remove functionality
            item.querySelector('.remove-item').addEventListener('click', function(e) {
                e.stopPropagation();
                removeSelectedItem(value);
                // Also uncheck the corresponding checkbox
                const option = container.querySelector(`.multiselect-option[data-value="${value}"]`);
                if (option) {
                    const checkbox = option.querySelector('input[type="checkbox"]');
                    checkbox.checked = false;
                    option.classList.remove('selected');
                }
                updatePlaceholder();
            });
        }
        
        function removeSelectedItem(value) {
            const item = selectedItems.querySelector(`[data-value="${value}"]`);
            if (item) {
                item.remove();
            }
        }
        
        function updatePlaceholder() {
            const hasSelectedItems = selectedItems.querySelectorAll('.selected-item').length > 0;
            placeholder.style.display = hasSelectedItems ? 'none' : 'block';
        }
    }
});
</script>

</body>
</html>