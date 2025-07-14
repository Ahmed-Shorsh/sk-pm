<?php
// File: public/partials/navbar.php  (Bootstrap 5) old

// --- bootstrapping -------------------------------------------------------
require_once __DIR__ . '/../backend/auth.php';
require_once __DIR__ . '/../backend/user_controller.php';
secureSessionStart();
checkLogin();

$user      = getUser($_SESSION['user_id']);
$roleId    = (int)($_SESSION['role_id'] ?? 0);
$roleName  = htmlspecialchars($_SESSION['role_name'] ?? '');
$userName  = htmlspecialchars($user['name']       ?? '');

$currentPage = basename($_SERVER['PHP_SELF']);

function isActivePage($page) {
    global $currentPage;
    return $currentPage === $page ? 'active' : '';
}

function isActiveDropdown($pages) {
    global $currentPage;
    return in_array($currentPage, $pages) ? 'active' : '';
}

// ------------------------------------------------------------------------
?>


<!DOCTYPE html>
<html lang="en">
<head>
<link href="https://fonts.googleapis.com/css2?family=Merriweather&family=Playfair+Display&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="./assets/css/style.css">
<link rel="icon" href="./assets/logo/sk-n.ico" type="image/x-icon">
</head>
<body>
  

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container-fluid">
  <a class="navbar-brand d-flex align-items-center" href="dashboard.php">
  <img src="./assets/logo/SKE.png" alt="SK Group" class="d-inline-block align-text-top">
</a>


    <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
            data-bs-target="#navMenu" aria-controls="navMenu"
            aria-expanded="false" aria-label="Toggle navigation">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navMenu">
      <ul class="navbar-nav me-auto">

        <li class="nav-item">
          <a class="nav-link <?= isActivePage('dashboard.php') ?>" href="dashboard.php">Dashboard</a>
        </li>

        <?php if ($roleId === 1): ?>
          <li class="nav-item"><a class="nav-link <?= isActivePage('users.php') ?>" href="users.php">Users</a></li>
          <li class="nav-item"><a class="nav-link <?= isActivePage('departments.php') ?>" href="departments.php">Departments</a></li>

          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle <?= isActiveDropdown(['individual_indicators.php', 'department_indicators.php']) ?>" href="#" id="indicatorsDrop"
               role="button" data-bs-toggle="dropdown" aria-expanded="false">
              Indicators
            </a>
            <ul class="dropdown-menu" aria-labelledby="indicatorsDrop">
              <li><a class="dropdown-item <?= isActivePage('individual_indicators.php') ?>" href="individual_indicators.php">
                    Individual Indicators</a></li>
              <li><a class="dropdown-item <?= isActivePage('department_indicators.php') ?>" href="department_indicators.php">
                    Department Indicators</a></li>
            </ul>
          </li>

          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle <?= isActiveDropdown(['audit_evaluations.php', 'audit_departments.php']) ?>" href="#" id="auditDrop"
               role="button" data-bs-toggle="dropdown" aria-expanded="false">
              Audit
            </a>
            <ul class="dropdown-menu" aria-labelledby="auditDrop">
              <li><a class="dropdown-item <?= isActivePage('audit_evaluations.php') ?>" href="audit_evaluations.php">
                     Individuals Audit</a></li>
              <li><a class="dropdown-item <?= isActivePage('audit_departments.php') ?>" href="audit_departments.php">
                    Department Audit</a></li>
            </ul>
          </li>

          <li class="nav-item"><a class="nav-link <?= isActivePage('reports.php') ?>" href="reports.php">Reports</a></li>

          <li class="nav-item"><a class="nav-link <?= isActivePage('reminders.php') ?>" href="reminders.php">Reminders</a></li>
          <li class="nav-item">
            <a class="nav-link <?= isActivePage('settings.php') ?>" href="settings.php">
              <i class="bi bi-gear me-1"></i> Settings
            </a>
          </li>

        <?php elseif ($roleId === 2): ?>
          <li class="nav-item"><a class="nav-link <?= isActivePage('department_plan.php') ?>" href="department_plan.php">Dept Plan</a></li>
          <li class="nav-item"><a class="nav-link <?= isActivePage('actuals_entry.php') ?>" href="actuals_entry.php">Enter Actuals</a></li>
          <li class="nav-item"><a class="nav-link <?= isActivePage('evaluate.php') ?>" href="evaluate.php">Team Ratings</a></li>

        <?php else: ?>
          <li class="nav-item"><a class="nav-link <?= isActivePage('evaluate.php') ?>" href="evaluate.php">
                Submit Ratings</a></li>
        <?php endif; ?>
      </ul>

      <ul class="navbar-nav ms-auto align-items-center">
        <li class="nav-item me-3">
          <span class="navbar-text">
            Hello, <strong><?= $userName ?></strong> (<?= $roleName ?>)
          </span>
        </li>
        <li class="nav-item">
          <a class="nav-link <?= isActivePage('logout.php') ?>" href="logout.php">Log Out</a>
        </li>
      </ul>
    </div>
  </div>
</nav>


</body>
</html>