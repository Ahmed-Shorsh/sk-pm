<?php
/* =======================================================================
 * PARTIAL: eval_target_card.php
 * Renders one evaluation card for a single target employee / manager.
 *
 * Expects (already defined in the parent file):
 *   • $p               array  {user_id,name}
 *   • $indShared       array  indicators common to everyone
 *   • $indManager      array  manager-specific indicators
 *   • $roleId          int    current user role
 *   • $targetIsManager bool   true if the current card is the department manager
 * ======================================================================= */
?>

<div class="card mb-4 shadow-sm">
  <div class="card-header <?= !empty($targetIsManager) ? 'bg-secondary text-white' : '' ?>">
    <?= htmlspecialchars($p['name']) ?>
    <?= !empty($targetIsManager) ? ' (Manager)' : '' ?>
  </div>

  <div class="card-body">
    <?php
      /* ---------------------------------------------------------------
       * Helper to output one indicator row
       * ------------------------------------------------------------- */
      $row = function(array $ind) use ($p) {
          $goal = (float)$ind['default_goal'];
          ?>
          <div class="row mb-3 align-items-center">
            <label class="col-md-6 col-form-label">
              <?= htmlspecialchars($ind['name']) ?>
              <small class="text-muted">(Goal&nbsp;<?= $goal ?>)</small>
            </label>
            <div class="col-md-4">
              <input
                type="number" class="form-control"
                step="0.1" min="0" max="<?= $goal ?>"
                placeholder="0 – <?= $goal ?>"
                name="ratings[<?= $p['user_id'] ?>][<?= $ind['indicator_id'] ?>]"
                required
              >
            </div>
          </div>
          <?php
      };
    ?>

    <?php foreach ($indShared as $ind) $row($ind); ?>

    <?php if (!empty($targetIsManager)): ?>
        <?php foreach ($indManager as $ind) $row($ind); ?>
    <?php endif; ?>

    <div class="mb-3">
      <label class="form-label">Notes / Comments</label>
      <textarea
        class="form-control"
        name="notes[<?= $p['user_id'] ?>]"
        rows="3"
        placeholder="Optional feedback…"></textarea>
    </div>
  </div>
</div>
