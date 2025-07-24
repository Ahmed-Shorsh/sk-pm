
<div class="card mb-4 shadow-sm">
 <div class="card-header <?= !empty($targetIsManager) ? 'bg-secondary text-white' : '' ?>">
   <?php if (!empty($targetIsManager)): ?>
     <?= $p['name']  ?>
   <?php else: ?>
     <?= htmlspecialchars($p['name'])  ?>
   <?php endif; ?>
   <?= !empty($targetIsManager) ? ' (Manager)' : '' ?>
 </div>

  <div class="card-body">
    <?php
      /* ---------------------------------------------------------------
       * Helper to output one indicator row
       * ------------------------------------------------------------- */
      $row = function(array $ind) use ($p) {
        $goal = (float)$ind['default_goal'];
        $desc = trim($ind['description'] ?? '');
        $short = $desc;
        $needsToggle = false;
    
        if ($desc !== '') {
            // Split into sentences (simple heuristic)
            $sentences = preg_split('/(?<=[.?!])\s+(?=[A-Z0-9])/u', $desc);
            if ($sentences && count($sentences) > 2) {
                $needsToggle = true;
                $short = implode(' ', array_slice($sentences, 0, 2));
            }
        }
        ?>
        <div class="row mb-3 align-items-center">
          <label class="col-md-6 col-form-label">
            <?= htmlspecialchars($ind['name']) ?>
            <small class="text-muted">(Goal&nbsp;<?= $goal ?>)</small>
    
            <?php if ($desc !== ''): ?>
              <div
                class="indicator-desc text-muted small mt-1"
                data-full="<?= htmlspecialchars($desc, ENT_QUOTES) ?>"
                data-short="<?= htmlspecialchars($short, ENT_QUOTES) ?>"
                <?php if ($needsToggle): ?>data-toggle="1"<?php endif; ?>
              >
                <span class="desc-text">
                  <?= htmlspecialchars($needsToggle ? $short : $desc) ?>
                </span>
                <?php if ($needsToggle): ?>
                  <a href="#" class="toggle-desc ms-1">(read more)</a>
                <?php endif; ?>
              </div>
            <?php endif; ?>
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
