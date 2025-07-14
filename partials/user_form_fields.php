<?php

$user = $formUser ?? [
    'name' => '',
    'email' => '',
    'phone' => '',
    'position' => '',
    'birth_date' => '',
    'hire_date' => '',
    'rating_window_days' => '',
    'role_id' => '',
    'dept_id' => '',
    'active' => 1,
];
?>

<div class="row g-3">
  <div class="col-md-6">
    <label class="form-label">Full Name</label>
    <input type="text" name="name" required class="form-control"
           value="<?= htmlspecialchars($user['name']) ?>">
  </div>
  
  <div class="col-md-6">
    <label class="form-label">Email</label>
    <input type="email" name="email" required class="form-control"
           value="<?= htmlspecialchars($user['email']) ?>">
  </div>

  <div class="col-md-6">
    <label class="form-label">Password <?= $isCreating ? '' : '(leave blank to keep)' ?></label>
    <input type="password" name="password" <?= $isCreating ? 'required' : '' ?> class="form-control">
  </div>
  
  <div class="col-md-6">
    <label class="form-label">Phone</label>
    <input type="text" name="phone" class="form-control"
           value="<?= htmlspecialchars($user['phone']) ?>">
  </div>

  <div class="col-md-6">
    <label class="form-label">Position</label>
    <input type="text" name="position" class="form-control"
           value="<?= htmlspecialchars($user['position']) ?>">
  </div>
  
  <div class="col-md-3">
    <label class="form-label">Birth Date</label>
    <input type="date" name="birth_date" class="form-control"
           value="<?= htmlspecialchars($user['birth_date']) ?>">
  </div>
  
  <div class="col-md-3">
    <label class="form-label">Hire Date</label>
    <input type="date" name="hire_date" class="form-control"
           value="<?= htmlspecialchars($user['hire_date']) ?>">
  </div>

  <div class="col-md-3">
    <label class="form-label">Role</label>
    <select name="role_id" class="form-select" required>
      <option value="">-- Select Role --</option>
      <?php foreach ($roles as $r): ?>
        <option value="<?= $r['role_id'] ?>"
          <?= ($user['role_id'] ?? null) == $r['role_id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($r['role_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  
  <div class="col-md-4">
    <label class="form-label">Department</label>
    <select name="dept_id" class="form-select">
      <option value="">None</option>
      <?php foreach ($departments as $d): ?>
        <option value="<?= $d['dept_id'] ?>"
          <?= ($user['dept_id'] ?? '') == $d['dept_id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars($d['dept_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
  
  <div class="col-md-2">
    <label class="form-label" data-bs-toggle="tooltip"
           data-bs-title="Days before month-end when rating/actual buttons unlock. 0 = always open.">
      Window&nbsp;Days
    </label>
    <input type="number" name="rating_window_days" class="form-control"
           value="<?= htmlspecialchars($user['rating_window_days']) ?>">
  </div>

  <?php if (!$isCreating): ?>
    <div class="col-md-3 d-flex align-items-center">
      <div class="form-check mt-4">
        <input class="form-check-input" type="checkbox" name="active"
               <?= ($user['active'] ?? 1) ? 'checked' : '' ?>>
        <label class="form-check-label">Active</label>
      </div>
    </div>
  <?php endif; ?>
</div>