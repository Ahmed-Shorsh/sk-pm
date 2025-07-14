<?php
/**
 * Repository for “person–to–person” evaluations.
 *
 *  • Table  : individual_evaluations
 *  • Joins  : users, departments, individual_indicators
 *  • Scope  : one evaluator  → one target  → many indicators  (per month)
 *
 * @author   SK-PM
 * @version  2.0 – 2025-05-27
 */

declare(strict_types=1);

namespace Backend;

use PDO;
use PDOException;
use DateTime;
use Exception;

require_once __DIR__ . '/db.php';   // provides $pdo

final class EvaluationRepository
{
    private PDO $pdo;

    // ---------------------------------------------------------------------
    // ctor
    // ---------------------------------------------------------------------
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ---------------------------------------------------------------------
    // 1.  DIRECTORY HELPERS
    // ---------------------------------------------------------------------

    public function fetchPeers(int $userId): array
    {
        $deptId = $this->fetchDeptId($userId);
        if ($deptId === null) return [];
    
        $sql = "
          SELECT user_id, name
          FROM   users
          WHERE  dept_id = :dept
            AND  user_id <> :self
            AND  role_id = 3          -- employees only
            AND  active  = 1
          ORDER  BY name";
        return $this->select($sql, [':dept' => $deptId, ':self' => $userId]);
    }

    /** Manager of this user (if any) */
    public function fetchManager(int $userId): ?array
    {
        $sql = "
            SELECT m.user_id, m.name
            FROM   users       u
            JOIN   departments d ON u.dept_id = d.dept_id
            JOIN   users       m ON d.manager_id = m.user_id
            WHERE  u.user_id = :uid
        ";

        $row = $this->selectOne($sql, [':uid' => $userId]);

        return $row ?: null;
    }

    /** Team members for a manager */

public function fetchTeamMembers(int $managerId): array
{
    // manager’s own department
    $deptId = $this->selectScalar(
        "SELECT dept_id FROM users WHERE user_id = :uid",
        [':uid' => $managerId]
    );
    if ($deptId === null) return [];

    $sql = "
        SELECT user_id, name
        FROM   users
        WHERE  dept_id = :dept
          AND  role_id = 3          -- employees only
          AND  active  = 1
        ORDER  BY name
    ";
    return $this->select($sql, [':dept' => $deptId]);
}


    // ---------------------------------------------------------------------
    // 2.  SAVE / UPDATE EVALUATIONS
    // ---------------------------------------------------------------------

    public function saveEvaluations(
        int    $evaluatorId,
        string $month,
        array  $ratings,
        array  $comments = []
    ): bool {
        $this->assertMonth($month);
    
        /* --------------------------------------------------------------
         * Prepared statements
         * ------------------------------------------------------------ */
        $del = $this->pdo->prepare(
            'DELETE FROM individual_evaluations
              WHERE evaluator_id = :evtor
                AND evaluatee_id = :evtee
                AND month        = :m'
        );
    
        $ins = $this->pdo->prepare(
            'INSERT INTO individual_evaluations
                   (evaluator_id, evaluatee_id, indicator_id,
                    month, rating, comments, created_at)
             VALUES (:evtor, :evtee, :ind, :m, :val, :c, NOW())'
        );
    
        /* --------------------------------------------------------------
         * Cache default-goal ceilings so we can validate ranges
         * ------------------------------------------------------------ */
        $goalCache = [];
        $allIndIds = [];
        foreach ($ratings as $indArr) $allIndIds += array_keys($indArr);
        if ($allIndIds) {
            $place = implode(',', array_fill(0, count($allIndIds), '?'));
            $stmt  = $this->pdo->prepare(
                "SELECT indicator_id, default_goal
                   FROM individual_indicators
                  WHERE indicator_id IN ($place)"
            );
            $stmt->execute($allIndIds);
            $goalCache = $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        }
    
        /* --------------------------------------------------------------
         * Transaction: delete old rows → insert new ones
         * ------------------------------------------------------------ */
        $this->pdo->beginTransaction();
        try {
            foreach ($ratings as $targetId => $indArr) {
    
                /* purge previous ratings for this evaluator-target-month */
                $del->execute([
                    ':evtor' => $evaluatorId,
                    ':evtee' => $targetId,
                    ':m'     => $month,
                ]);
    
                foreach ($indArr as $indicatorId => $value) {
                    $value = (float)$value;         // allow decimals (step 0.1)
    
                    /* ---------- range check against default goal ---------- */
                    $ceil = (float)($goalCache[$indicatorId] ?? 5.0);
                    if ($value < 0 || $value > $ceil) {
                        throw new Exception(
                            "Rating $value out of range for indicator $indicatorId (0–$ceil)"
                        );
                    }
                    /* ------------------------------------------------------ */
    
                    $ins->execute([
                        ':evtor' => $evaluatorId,
                        ':evtee' => $targetId,
                        ':ind'   => $indicatorId,
                        ':m'     => $month,
                        ':val'   => $value,
                        ':c'     => $comments[$targetId] ?? '',
                    ]);
                }
            }
            $this->pdo->commit();
            return true;
    
        } catch (Exception|PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    // ---------------------------------------------------------------------
    // 3.  READ (REPORT) EVALUATIONS
    // ---------------------------------------------------------------------

    /**
     * Fetch evaluations with optional filters.
     *
     * @param string|null $month  YYYY-MM-01
     * @param int|null    $deptId limit to targets in department
     * @return array
     */
    public function fetchIndividualEvaluations(
        ?string $month = null,
        ?int    $deptId = null
    ): array {
        $sql = "
            SELECT  ie.eval_id,
                    ie.month,
                    ie.actual            AS rating,
                    ie.comments,
                    ie.created_at,
                    evtr.name            AS evaluator,
                    evte.name            AS evaluatee,
                    ind.name             AS indicator,
                    d.dept_name
            FROM    individual_evaluations ie
            JOIN    users evtr        ON ie.evaluator_id = evtr.user_id
            JOIN    users evte        ON ie.target_id    = evte.user_id
            JOIN    individual_indicators ind
                                     ON ie.indicator_id = ind.indicator_id
            LEFT    JOIN departments d ON evte.dept_id = d.dept_id
        ";

        $conds  = [];
        $params = [];

        if ($month !== null) {
            $this->assertMonth($month);
            $conds[]            = 'ie.month = :m';
            $params[':m']       = $month;
        }
        if ($deptId !== null) {
            $conds[]            = 'evte.dept_id = :d';
            $params[':d']       = $deptId;
        }
        if ($conds) {
            $sql .= ' WHERE ' . implode(' AND ', $conds);
        }

        $sql .= "
            ORDER BY ie.month DESC,
                     evtr.name,
                     evte.name,
                     ind.sort_order
        ";

        return $this->select($sql, $params);
    }

    // ---------------------------------------------------------------------
    // PRIVATE UTILS
    // ---------------------------------------------------------------------

    /** Get dept_id for a user, or null */
    private function fetchDeptId(int $userId): ?int
    {
        return $this->selectScalar(
            "SELECT dept_id FROM users WHERE user_id = :uid",
            [':uid' => $userId]
        );
    }

    /** Ensure month string is `YYYY-MM-01` */
    private function assertMonth(string $month): void
    {
        if (!preg_match('/^\d{4}-\d{2}-01$/', $month)) {
            throw new Exception('Invalid month format, expected YYYY-MM-01');
        }
    }

    /** Run SELECT and return all rows */
    private function select(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Run SELECT and return single row */
    private function selectOne(string $sql, array $params): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** Run SELECT and return first column of first row */
    private function selectScalar(string $sql, array $params): ?int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $val = $stmt->fetchColumn();

        return $val === false ? null : (int)$val;
    }
}

// -------------------------------------------------------------------------
// USAGE EXAMPLE (delete after wiring into controllers)
// -------------------------------------------------------------------------
$evalRepo = $evalRepo ?? new EvaluationRepository($pdo);

