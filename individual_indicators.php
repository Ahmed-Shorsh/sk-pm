<?php
declare(strict_types=1);
require_once __DIR__ . '/backend/auth.php';
require_once __DIR__ . '/backend/utils.php';
require_once __DIR__ . '/backend/indicator_controller.php';

secureSessionStart();
checkLogin();
if ($_SESSION['role_id'] !== 1) {
    header('HTTP/1.1 403 Forbidden');
    echo '<h1>Access Denied</h1>';
    exit;
}

use Backend\IndicatorRepository;
$indicatorRepo = new IndicatorRepository($pdo);

$flash = ['msg' => '', 'type' => 'success'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        try {
            $indicatorRepo->createIndividualIndicator([
                'name'                   => sanitize($_POST['name']),
                'description'            => sanitize($_POST['description']),
                'category'               => $_POST['category'],
                'responsible_departments'=> sanitize($_POST['responsible_departments']),
                'default_goal'           => (float)$_POST['default_goal'],
                'default_weight'         => $_POST['default_weight'] === '' ? null : (int)$_POST['default_weight'],
                'sort_order'             => (int)$_POST['sort_order'],
            ]);
            $flash = ['msg'=>'Indicator created.','type'=>'success'];
        } catch (Exception $e) {
            $flash = ['msg'=>'Error: '.$e->getMessage(),'type'=>'danger'];
        }
        redirect('individual_indicators.php');
    }

    if ($action === 'update') {
        $id = (int)$_POST['indicator_id'];
        try {
            $indicatorRepo->updateIndividualIndicator($id, [
                'name'                   => sanitize($_POST['name']),
                'description'            => sanitize($_POST['description']),
                'category'               => $_POST['category'],
                'responsible_departments'=> sanitize($_POST['responsible_departments']),
                'default_goal'           => (float)$_POST['default_goal'],
                'default_weight'         => $_POST['default_weight'] === '' ? null : (int)$_POST['default_weight'],
                'sort_order'             => (int)$_POST['sort_order'],
                'active'                 => isset($_POST['active']) ? 1 : 0,
            ]);
            $flash = ['msg'=>'Indicator updated.','type'=>'success'];
        } catch (Exception $e) {
            $flash = ['msg'=>'Update failed: '.$e->getMessage(),'type'=>'danger'];
        }
        redirect('individual_indicators.php');
    }

    if ($action === 'toggle') {
        $id = (int)$_POST['indicator_id'];
        $active = (int)$_POST['current_active'] ? 0 : 1;
        $indicatorRepo->updateIndividualIndicator($id, ['active'=>$active,'name'=>'','category'=>'individual']);
        $flash = ['msg'=>'Status changed.','type'=>'info'];
        redirect('individual_indicators.php');
    }
}

$indicators = $indicatorRepo->fetchIndividualIndicators(false);
include __DIR__ . '/partials/navbar.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Manage Indicators – SK-PM</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://fonts.googleapis.com/css2?family=Merriweather&family=Playfair+Display&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="./assets/css/style.css">
<link rel="icon" href="./assets/logo/sk-n.ico" type="image/x-icon">

</head>
<body class="font-serif bg-light text-dark">

<header class="data-container text-center py-4">
  <h1>Individual Indicator Management</h1>
  <p class="text-muted">Create, edit or deactivate individual performance indicators.</p>
</header>

<main class="data-container mb-5">
  <?php if ($flash['msg']): flashMessage($flash['msg'],$flash['type']); endif; ?>

  <div class="text-end mb-3">
    <button class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#createModal">+ Add Indicator</button>
  </div>

  <div class="table-responsive">
    <table class="table table-striped table-bordered align-middle">
      <thead class="table-dark">
        <tr>
          <th>#</th><th>Name</th><th>Category</th><th>Goal</th><th>Weight</th><th>Sort</th><th>Active</th><th class="text-center">Actions</th>
        </tr>
      </thead>
      <tbody>
      <tbody>
<?php foreach ($indicators as $ind): ?>
  <tr class="<?= $ind['active'] ? '' : 'table-danger' ?>">
    <td><?= $ind['indicator_id'] ?></td>
    <td><?= htmlspecialchars($ind['name']) ?></td>
    <td><?= $ind['category'] ?></td>
    <td><?= $ind['default_goal'] ?></td>
    <td><?= $ind['default_weight'] ?></td>
    <td><?= $ind['sort_order'] ?></td>
    <td>
      <span class="badge bg-<?= $ind['active'] ? 'success' : 'secondary' ?>">
        <?= $ind['active'] ? 'Yes' : 'No' ?>
      </span>
    </td>
    <td class="text-center">
      <button
        class="btn btn-sm btn-outline-secondary me-1"
        data-bs-toggle="modal"
        data-bs-target="#editModal<?= $ind['indicator_id'] ?>">
        Edit
      </button>

      <form method="post" class="d-inline" onsubmit="return confirm('Change status?');">
        <input type="hidden" name="action" value="toggle">
        <input type="hidden" name="indicator_id" value="<?= $ind['indicator_id'] ?>">
        <input type="hidden" name="current_active" value="<?= $ind['active'] ?>">
        <button
          class="btn btn-sm btn-outline-<?= $ind['active'] ? 'danger' : 'success' ?>">
          <?= $ind['active'] ? 'Disable' : 'Enable' ?>
        </button>
      </form>
    </td>
  </tr>
<?php endforeach; ?>
</tbody>
</table>

<?php foreach ($indicators as $ind): ?>
<div class="modal fade" id="editModal<?= $ind['indicator_id'] ?>" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="post" class="modal-content">
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="indicator_id" value="<?= $ind['indicator_id'] ?>">

      <div class="modal-header">
        <h5 class="modal-title">Edit Indicator</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>

      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label">Name<span class="text-danger">*</span></label>
            <input type="text" name="name" required class="form-control"
                   value="<?= htmlspecialchars($ind['name']) ?>">
          </div>
          <div class="col-md-4">
            <label class="form-label">Category<span class="text-danger">*</span></label>
            <select name="category" class="form-select" required>
              <?php foreach (['individual','manager'] as $cat): ?>
                <option value="<?= $cat ?>" <?= $ind['category']===$cat?'selected':'' ?>>
                  <?= ucfirst($cat) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="description" rows="3" class="form-control">
              <?= htmlspecialchars($ind['description']) ?>
            </textarea>
          </div>

          <div class="col-md-6">
            <label class="form-label">Responsible Departments</label>
            <input type="text" name="responsible_departments" class="form-control"
                   value="<?= htmlspecialchars($ind['responsible_departments']) ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Default Goal</label>
            <input type="number" step="0.01" name="default_goal" class="form-control"
                   value="<?= $ind['default_goal'] ?>">
          </div>
          <div class="col-md-3">
            <label class="form-label">Default Weight</label>
            <input type="number" name="default_weight" class="form-control"
                   value="<?= $ind['default_weight'] ?>">
          </div>

          <div class="col-md-3">
            <label class="form-label">Sort Order<span class="text-danger">*</span></label>
            <input type="number" name="sort_order" required class="form-control"
                   value="<?= $ind['sort_order'] ?>">
          </div>
          <div class="col-md-3 d-flex align-items-center">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" name="active"
                     <?= $ind['active']?'checked':'' ?>>
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

      </tbody>
    </table>
  </div>
</main>

<div class="modal fade" id="createModal" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <form method="post" class="modal-content">
      <input type="hidden" name="action" value="create">
      <div class="modal-header">
        <h5 class="modal-title">Add Indicator</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-md-8">
            <label class="form-label">Name<span class="text-danger">*</span></label>
            <input type="text" name="name" required class="form-control">
          </div>
          <div class="col-md-4">
            <label class="form-label">Category<span class="text-danger">*</span></label>
            <select name="category" class="form-select" required>
              <option value="individual">Individual</option>
              <option value="manager">Manager</option>
            </select>
          </div>
          <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="description" rows="3" class="form-control"></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">Responsible Departments</label>
            <input type="text" name="responsible_departments" class="form-control">
          </div>
          <div class="col-md-3">
            <label class="form-label">Default Goal</label>
            <input type="number" step="0.01" name="default_goal" class="form-control">
          </div>
          <div class="col-md-3">
            <label class="form-label">Default Weight</label>
            <input type="number" name="default_weight" class="form-control">
          </div>
          <div class="col-md-3">
            <label class="form-label">Sort Order<span class="text-danger">*</span></label>
            <input type="number" name="sort_order" required class="form-control" value="0">
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</body>
</html>
