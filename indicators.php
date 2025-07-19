<?php
// File: public/indicators.php
// Admin UI for full CRUD on performance indicators

require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/utils.php';
require_once __DIR__ . '/backend/indicator_controller.php';

secureSessionStart();
checkLogin();
function requireRoleId(int $roleId) {
    checkLogin();
    if (($_SESSION['role_id'] ?? 0) !== $roleId) {
        header('HTTP/1.1 403 Forbidden');
        echo 'Access denied.';
        exit();
    }
}

// Categories map
$categories = [
    'individual' => 'Individual',
    'manager'    => 'Manager',
    'department' => 'Department',
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $name         = sanitize($_POST['name']);
        $description  = sanitize($_POST['description']);
        $category     = $_POST['category'];
        $default_goal = (float)$_POST['default_goal'];
        $unit         = sanitize($_POST['unit']);
        $default_weight = (int)$_POST['default_weight'];
        $sort_order     = (int)($_POST['sort_order'] ?? 0);

        $ok = createIndicator($name, $description, $category, $default_goal, $unit, $default_weight, $sort_order);
        flashMessage($ok ? 'Indicator created successfully.' : 'Failed to create indicator.', $ok?'success':'danger');

    } elseif ($action === 'update') {
        $id           = (int)$_POST['indicator_id'];
        $name         = sanitize($_POST['name']);
        $description  = sanitize($_POST['description']);
        $category     = $_POST['category'];
        $default_goal = (float)$_POST['default_goal'];
        $unit         = sanitize($_POST['unit']);
        $default_weight = (int)$_POST['default_weight'];
        $sort_order     = (int)($_POST['sort_order'] ?? 0);
        $active         = isset($_POST['active']) ? 1 : 0;

        $ok = updateIndicator($id, $name, $description, $category, $default_goal, $unit, $default_weight, $active, $sort_order);
        flashMessage($ok ? 'Indicator updated.' : 'Failed to update.', $ok?'success':'danger');

    } elseif ($action === 'deactivate') {
        $id = (int)$_POST['indicator_id'];
        $ok = deleteIndicator($id);
        flashMessage($ok ? 'Indicator deactivated.' : 'Failed to deactivate.', $ok?'success':'danger');
    }

    header('Location: indicators.php');
    exit();
}

// Fetch for display (sorted by sort_order then name)
$indicators = fetchAllIndicators(); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Indicators – SK-PM</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://fonts.googleapis.com/css2?family=Merriweather&family=Playfair+Display&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="./assets/css/style.css">
  <link rel="icon" href="./assets/logo/sk-n.ico" type="image/x-icon">

</head>
<body class="font-serif bg-light text-dark">
  <!-- Admin navbar partial -->
  <?php include __DIR__ . '/partials/navbar.php'; ?>

  <div class="container my-5">
    <h1 class="mb-4">Performance Indicators</h1>

    <!-- Flash messages -->
    <?php if (!empty($GLOBALS['message_html'])): ?>
      <div class="mb-4"><?= $GLOBALS['message_html'] ?></div>
    <?php endif; ?>

    <!-- Search & filter toolbar -->
    <div class="row mb-3">
      <div class="col-md-4">
        <input id="searchInput" class="form-control" placeholder="Search indicators…">
      </div>
      <div class="col-md-3">
        <select id="categoryFilter" class="form-select">
          <option value="">All Categories</option>
          <?php foreach($categories as $key => $label): ?>
            <option value="<?=$key?>"><?=$label?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col text-end">
        <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#createModal">
          + Add Indicator
        </button>
      </div>
    </div>

    <!-- Indicators table -->
    <div class="table-responsive">
      <table id="indTable" class="table table-striped align-middle">
        <thead class="table-dark">
          <tr>
            <th>Sort Order</th>
            <th>Name</th>
            <th>Category</th>
            <th>Default Goal</th>
            <th>Unit</th>
            <th>Weight</th>
            <th>Active</th>
            <th class="text-center">Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($indicators as $ind): ?>
          <tr data-name="<?=htmlspecialchars(strtolower($ind['name']))?>" data-cat="<?=$ind['category']?>">
            <td><?= $ind['sort_order'] ?? '-' ?></td>
            <td><?= htmlspecialchars($ind['name']) ?></td>
            <td><?= htmlspecialchars(ucfirst($ind['category'])) ?></td>
            <td><?= htmlspecialchars($ind['default_goal']) ?></td>
            <td><?= htmlspecialchars($ind['unit']) ?></td>
            <td><?= htmlspecialchars($ind['default_weight']) ?></td>
            <td><?= $ind['active'] ? 'Yes' : 'No' ?></td>
            <td class="text-center">
              <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#editModal<?=$ind['indicator_id']?>">
                Edit
              </button>
              <?php if($ind['active']): ?>
                <form method="POST" class="d-inline">
                  <input type="hidden" name="action" value="deactivate">
                  <input type="hidden" name="indicator_id" value="<?=$ind['indicator_id']?>">
                  <button class="btn btn-sm btn-outline-danger">Deactivate</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>

          <!-- Edit Modal -->
          <div class="modal fade" id="editModal<?=$ind['indicator_id']?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
              <form method="POST" class="modal-content">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="indicator_id" value="<?=$ind['indicator_id']?>">
                <div class="modal-header">
                  <h5 class="modal-title">Edit Indicator #<?=$ind['indicator_id']?></h5>
                  <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                  <div class="row g-3">
                    <div class="col-md-6">
                      <label class="form-label">Name</label>
                      <input name="name" class="form-control" value="<?=htmlspecialchars($ind['name'])?>" required>
                    </div>
                    <div class="col-md-6">
                      <label class="form-label">Sort Order</label>
                      <input type="number" name="sort_order" class="form-control" value="<?=$ind['sort_order']?>" required>
                    </div>
                    <div class="col-12">
                      <label class="form-label">Description</label>
                      <textarea name="description" class="form-control" rows="3"><?=htmlspecialchars($ind['description'])?></textarea>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Category</label>
                      <select name="category" class="form-select">
                        <?php foreach($categories as $k=>$v): ?>
                          <option value="<?=$k?>" <?=$k===$ind['category']?'selected':''?>><?=$v?></option>
                        <?php endforeach; ?>
                      </select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Default Goal</label>
                      <input type="number" step="0.01" name="default_goal" class="form-control" value="<?=$ind['default_goal']?>" required>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Unit</label>
                      <input name="unit" class="form-control" value="<?=htmlspecialchars($ind['unit'])?>">
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Weight</label>
                      <input type="number" name="default_weight" class="form-control" value="<?=$ind['default_weight']?>" required>
                    </div>
                    <div class="col-md-8 align-self-end">
                      <div class="form-check mt-2">
                        <input type="checkbox" name="active" class="form-check-input" id="active<?=$ind['indicator_id']?>" <?=$ind['active']?'checked':''?>>
                        <label class="form-check-label" for="active<?=$ind['indicator_id']?>">Active</label>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="modal-footer">
                  <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                  <button class="btn btn-dark">Save Changes</button>
                </div>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Create Modal -->
  <div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <form method="POST" class="modal-content">
        <input type="hidden" name="action" value="create">
        <div class="modal-header">
          <h5 class="modal-title">Add New Indicator</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Name</label>
              <input name="name" class="form-control" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Sort Order</label>
              <input type="number" name="sort_order" class="form-control" value="0" required>
            </div>
            <div class="col-12">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control" rows="3"></textarea>
            </div>
            <div class="col-md-4">
              <label class="form-label">Category</label>
              <select name="category" class="form-select">
                <?php foreach($categories as $k=>$v): ?>
                  <option value="<?=$k?>"><?=$v?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-md-4">
              <label class="form-label">Default Goal</label>
              <input type="number" step="0.01" name="default_goal" class="form-control" required>
            </div>
            <div class="col-md-4">
              <label class="form-label">Unit</label>
              <input name="unit" class="form-control">
            </div>
            <div class="col-md-4">
              <label class="form-label">Weight</label>
              <input type="number" name="default_weight" class="form-control" required>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-dark">Create Indicator</button>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
// Client-side search/filter:
document.getElementById('searchInput').addEventListener('input', () => {
  const term = document.getElementById('searchInput').value.toLowerCase();
  document.querySelectorAll('#indTable tbody tr').forEach(row => {
    row.style.display = row.dataset.name.includes(term) ? '' : 'none';
  });
});
document.getElementById('categoryFilter').addEventListener('change', () => {
  const cat = document.getElementById('categoryFilter').value;
  document.querySelectorAll('#indTable tbody tr').forEach(row => {
    row.style.display = (!cat || row.dataset.cat===cat) ? '' : 'none';
  });
});
  </script>
</body>
</html>