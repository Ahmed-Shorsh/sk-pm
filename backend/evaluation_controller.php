<?php

declare(strict_types=1);

namespace Backend;

use PDO;
use PDOException;
use Exception;

require_once __DIR__ . '/db.php';   // provides $pdo
require_once __DIR__ . '/settings_controller.php';  // for SettingsRepository

final class EvaluationRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    //======================================================================
    // 1. DIRECTORY HELPERS
    //======================================================================

    public function fetchPeers(int $userId): array
    {
        $deptId = $this->fetchDeptId($userId);
        if ($deptId === null) return [];

        $sql = "
          SELECT user_id, name
            FROM users
           WHERE dept_id  = :dept
             AND user_id  <> :self
             AND role_id   = 3    -- employees only
             AND active    = 1
        ORDER BY name
        ";
        return $this->select($sql, [':dept' => $deptId, ':self' => $userId]);
    }

    public function fetchManager(int $userId): ?array
    {
        $sql = "
            SELECT m.user_id, m.name
              FROM users u
              JOIN departments d ON u.dept_id = d.dept_id
              JOIN users m ON d.manager_id = m.user_id
             WHERE u.user_id = :uid
        ";
        return $this->selectOne($sql, [':uid' => $userId]);
    }

    public function fetchTeamMembers(int $managerId): array
    {
        $deptId = $this->selectScalar(
            "SELECT dept_id FROM users WHERE user_id = :uid",
            [':uid' => $managerId]
        );
        if ($deptId === null) return [];

        $sql = "
            SELECT user_id, name
              FROM users
             WHERE dept_id = :dept
               AND role_id = 3
               AND active  = 1
          ORDER BY name
        ";
        return $this->select($sql, [':dept' => $deptId]);
    }

    //======================================================================
    // 2. SAVE / UPDATE EVALUATIONS
    //======================================================================

    /**
     * Save all ratings for one evaluator in one month,
     * then update each target’s final scores immediately.
     */
    public function saveEvaluations(
        int    $evaluatorId,
        string $month,
        array  $ratings,
        array  $comments = []
    ): bool {
        $this->assertMonth($month);

        // DELETE + INSERT statements for individual_evaluations
        $del = $this->pdo->prepare("
            DELETE FROM individual_evaluations
             WHERE evaluator_id = :evtor
               AND evaluatee_id = :evtee
               AND indicator_id = :ind
               AND month        = :m
        ");
        $ins = $this->pdo->prepare("
            INSERT INTO individual_evaluations
              (evaluator_id, evaluatee_id, indicator_id,
               month, rating, comments, created_at)
            VALUES (:evtor, :evtee, :ind, :m, :val, :c, NOW())
        ");

        // Cache default goals
        $allIds = [];
        foreach ($ratings as $arr) {
            $allIds = array_merge($allIds, array_keys($arr));
        }
        $goalCache = [];
        if ($allIds) {
            $place = implode(',', array_fill(0, count($allIds), '?'));
            $stmt  = $this->pdo->prepare("
                SELECT indicator_id, default_goal
                  FROM individual_indicators
                 WHERE indicator_id IN ($place)
            ");
            $stmt->execute($allIds);
            $goalCache = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        }

        $this->pdo->beginTransaction();
        try {
            // Loop each evaluatee
            foreach ($ratings as $targetId => $indArr) {

                // Purge old ratings for these indicators
                foreach ($indArr as $indicatorId => $_) {
                    $del->execute([
                        ':evtor' => $evaluatorId,
                        ':evtee' => $targetId,
                        ':ind'   => $indicatorId,
                        ':m'     => $month,
                    ]);
                }

                // Insert fresh ratings
                foreach ($indArr as $indicatorId => $value) {
                    $value = (float)$value;
                    $ceil  = (float)($goalCache[$indicatorId] ?? 5.0);
                    if ($value < 0 || $value > $ceil) {
                        throw new Exception("Rating $value out of range (0–$ceil)");
                    }
                    $ins->execute([
                        ':evtor' => $evaluatorId,
                        ':evtee' => $targetId,
                        ':ind'   => $indicatorId,
                        ':m'     => $month,
                        ':val'   => $value,
                        ':c'     => $comments[$targetId] ?? '',
                    ]);
                }

                // Immediately update final scores for this target
                $this->updateScoresAfterEvaluation($targetId, $month);
            }

            $this->pdo->commit();
            return true;
        } catch (Exception | PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    //======================================================================
    // 3. CONTINUOUS SCORE UPDATES
    //======================================================================

    /**
     * Recalculate and upsert dept/individual/final scores for one user/month.
     */
    public function updateScoresAfterEvaluation(int $targetUserId, string $month): void
    {
        // 1. Dept ID
        $deptId = $this->fetchDeptId($targetUserId);
        if ($deptId === null) {
            return;
        }

        // 2. Individual Score: sum ratings ÷ (raters × sum(goals)) × 100
        $sql = "
            SELECT 
              SUM(ev.rating) AS total_rating,
              COUNT(DISTINCT ev.evaluator_id) AS evaluator_count
            FROM individual_evaluations ev
            JOIN users ue
              ON ev.evaluator_id = ue.user_id
            WHERE ev.evaluatee_id = :user
              AND ev.month        = :m
              AND ue.dept_id       = :dept
        ";
        $row = $this->selectOne($sql, [
            ':user' => $targetUserId,
            ':m'    => $month,
            ':dept' => $deptId
        ]);
        $totalRating    = (float)($row['total_rating']    ?? 0);
        $evaluatorCount = (int)  ($row['evaluator_count'] ?? 0);

        $individualScore = 0.0;
        if ($evaluatorCount > 0) {
            // sum of indicator goals for this set
            $goalSql = "
                SELECT SUM(ii.default_goal) AS goal_sum
                  FROM individual_indicators ii
                 WHERE ii.indicator_id IN (
                   SELECT DISTINCT ev.indicator_id
                     FROM individual_evaluations ev
                     JOIN users ue
                       ON ev.evaluator_id = ue.user_id
                    WHERE ev.evaluatee_id = :user
                      AND ev.month        = :m
                      AND ue.dept_id       = :dept
                 )
            ";
            $goalSum = (float)$this->selectScalar($goalSql, [
                ':user' => $targetUserId,
                ':m'    => $month,
                ':dept' => $deptId
            ]) ?: 0.0;

            if ($goalSum > 0) {
                $individualScore = ($totalRating / ($evaluatorCount * $goalSum)) * 100.0;
            }
        }

        // 3. Department Score: sum((audit_score/5)*weight)
        $deptSql = "
            SELECT COALESCE(SUM(dim.audit_score * dim.weight),0) AS dept_total
              FROM department_indicator_monthly dim
             WHERE dim.dept_id = :dept
               AND dim.month   = :m
        ";



        $deptScore = (float)$this->selectScalar($deptSql, [
            ':dept' => $deptId,
            ':m'    => $month
        ]);

        // 4. Fetch weights
        $settings = new SettingsRepository($this->pdo);
        $deptPerc = (int)$settings->getSetting('department_score_weight');
        $indPerc  = (int)$settings->getSetting('individual_score_weight');
        $deptW    = $deptPerc / 100.0;
        $indW     = $indPerc  / 100.0;

        // 5. Final Score
        $finalScore = round($deptScore * $deptW + $individualScore * $indW, 2);

        // 6. Upsert into `scores`
        $up = $this->pdo->prepare("
            INSERT INTO scores
              (user_id, dept_id, month, dept_score, individual_score, final_score)
            VALUES
              (:user, :dept, :m, :dscore, :iscore, :fscore)
            ON DUPLICATE KEY UPDATE
              dept_score       = VALUES(dept_score),
              individual_score = VALUES(individual_score),
              final_score      = VALUES(final_score),
              dept_id          = VALUES(dept_id)
        ");
        $up->execute([
            ':user'   => $targetUserId,
            ':dept'   => $deptId,
            ':m'      => $month,
            ':dscore' => round($deptScore, 2),
            ':iscore' => round($individualScore, 2),
            ':fscore' => $finalScore,
        ]);
    }

    //======================================================================
    // 4. PENDING EVALUATIONS COUNT (UNCHANGED)
    //======================================================================

    public function countPendingEvaluations(int $evaluatorId, string $month): int
    {
        $total = (int)$this->selectScalar("
            SELECT COUNT(*) FROM users
             WHERE dept_id  = (SELECT dept_id FROM users WHERE user_id = :uid)
               AND user_id <> :uid
               AND active   = 1
        ", [':uid' => $evaluatorId]);

        $done = (int)$this->selectScalar("
            SELECT COUNT(DISTINCT evaluatee_id)
              FROM individual_evaluations
             WHERE evaluator_id = :uid
               AND month        = :m
        ", [':uid' => $evaluatorId, ':m' => $month]);

        return max(0, $total - $done);
    }

    //======================================================================
    // 5. PRIVATE HELPERS
    //======================================================================

    private function fetchDeptId(int $userId): ?int
    {
        return $this->selectScalar(
            "SELECT dept_id FROM users WHERE user_id = :uid",
            [':uid' => $userId]
        );
    }

    private function assertMonth(string $month): void
    {
        if (!preg_match('/^\d{4}-\d{2}-01$/', $month)) {
            throw new Exception("Invalid month: $month");
        }
    }

    private function select(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function selectOne(string $sql, array $params): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function selectScalar(string $sql, array $params)
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $val = $stmt->fetchColumn();
        return $val === false ? null : $val;
    }
}
