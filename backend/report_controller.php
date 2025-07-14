<?php

/**
 * --------------------------------------------------------------------------
 *  File: backend/report_controller.php
 *  Role: Single source of read-only analytics / reporting queries
 *  Scope:
 *      • Global admin KPIs (organisation trend, dept averages, counts …)
 *      • Manager dashboards (dept score, team list, KPI contributions …)
 *      • Employee dashboards (personal trend / breakdown)
 *      • Drill-down helpers for reports.php (table rows, CSV, PDF, etc.)
 *
 *  Schema touches
 *      ─────────────────────────────────────────────────────────────────
 *          scores
 *          departments
 *          users
 *          individual_evaluations         (evaluator_id | evaluatee_id | rating)
 *          individual_indicators
 *          department_indicator_monthly   (snapshot of monthly KPIs)
 *          department_indicators
 *  --------------------------------------------------------------------------
 *  @author     SK-PM
 *  @version    3.0  (2025-05-27)
 * --------------------------------------------------------------------------
 */

declare(strict_types=1);

namespace Backend;


use PDO;
use PDOException;
use Exception;

require_once __DIR__ . '/db.php';     
require_once __DIR__ . '/settings_controller.php';

final class ReportRepository
{
    /* ----------------------------- weights ----------------------------- */
    // public const W_DEPT = 0.70;   
    // public const W_IND  = 0.30;  

    
    // public function __construct(PDO $pdo)
    // {
    //     $this->pdo = $pdo;
    //     $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    //     $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // }

    private float $deptWeight;   
    private float $indWeight;    
    private PDO $pdo;
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // dynamic weights
        $settingsRepo = new SettingsRepository($pdo);
        $deptPerc = (int)($settingsRepo->getSetting('department_score_weight') ?? 70);
        $indPerc  = (int)($settingsRepo->getSetting('individual_score_weight')  ?? 30);
        $this->deptWeight = $deptPerc / 100.0;
        $this->indWeight  = $indPerc  / 100.0;
    }


    /* ======================================================================
     *  ████  ADMIN-LEVEL  GLOBAL INSIGHT
     * ==================================================================== */

    /** List every department (id + name). */
    public function fetchAllDepartments(): array
    {
        return $this->select('SELECT dept_id, dept_name FROM departments ORDER BY dept_name');
    }

    /** Distinct months that have *scores* (YYYY-MM-01). */
    public function getScoreMonths(): array
    {
        return $this->selectColumn('SELECT DISTINCT month FROM scores ORDER BY month DESC');
    }

    /** Distinct months that have *evaluations* (YYYY-MM-01). */
    public function getEvaluationMonths(): array
    {
        return $this->selectColumn('SELECT DISTINCT month FROM individual_evaluations ORDER BY month DESC');
    }

    /** Average final-score per department for the given month. */
    public function deptAverages(string $month): array
    {
        $sql = "
          SELECT d.dept_name,
                 ROUND(AVG(s.final_score),2) AS avg_score
          FROM   scores s
          JOIN   departments d ON s.dept_id = d.dept_id
          WHERE  s.month = :m
          GROUP  BY d.dept_id
          ORDER  BY avg_score DESC
        ";
        return $this->select($sql, [':m' => $month]);
    }

    /** Organisation-wide average final-score for last *N* months (newest → oldest). */
    public function organisationTrend(int $monthsBack = 6): array
    {
        $sql = "
          SELECT month,
                 ROUND(AVG(final_score),2) AS avg_score
          FROM   scores
          GROUP  BY month
          ORDER  BY month DESC
          LIMIT  :lim
        ";
        $rows = $this->select($sql, [':lim' => $monthsBack], true);
        return array_reverse($rows);                // oldest first
    }

    /* ======================================================================
     *  ████  MANAGER-DASHBOARD HELPERS
     * ==================================================================== */

    /** One consolidated department score (0-100). */
    public function deptScore(int $deptId, string $month): float
    {
        return (float)$this->selectScalar(
            'SELECT DISTINCT dept_score FROM scores
             WHERE dept_id = :d AND month = :m LIMIT 1',
            [':d' => $deptId, ':m' => $month]
        );
    }

    /** Team members with individual & final scores (desc). */
    public function teamFinalScores(int $deptId, string $month): array
    {
        $sql = "
          SELECT u.user_id, u.name,
                 s.individual_score,
                 s.final_score
          FROM   scores s
          JOIN   users  u ON s.user_id = u.user_id
          WHERE  s.dept_id = :d
            AND  s.month   = :m
          ORDER  BY s.final_score DESC
        ";
        return $this->select($sql, [':d' => $deptId, ':m' => $month]);
    }

    /** Pie chart – how much each KPI contributed to the dept score. */
    public function kpiContributions(int $deptId, string $month): array
    {
        $sql = "
          SELECT COALESCE(di.name, dim.custom_name) AS label,
                 ROUND(
                   (CASE WHEN dim.target_value>0
                           THEN (dim.actual_value/dim.target_value)*100
                         ELSE 0 END) * dim.weight
                 ,2) AS contribution
          FROM   department_indicator_monthly dim
          LEFT   JOIN department_indicators di
                 ON   dim.indicator_id = di.indicator_id
          WHERE  dim.dept_id      = :d
            AND  dim.month        = :m
            AND  dim.actual_value IS NOT NULL
        ";
        return $this->select($sql, [':d' => $deptId, ':m' => $month]);
    }

    /* ======================================================================
     *  ████  EMPLOYEE-DASHBOARD HELPERS
     * ==================================================================== */

    /** Chronological list of a user’s final scores (oldest → newest). */
    public function personalTrend(int $userId, int $monthsBack = 6): array
    {
        $sql  = "
          SELECT month, final_score
          FROM   scores
          WHERE  user_id = :uid
          ORDER  BY month DESC
          LIMIT  :lim
        ";
        $rows = $this->select($sql, [':uid' => $userId, ':lim' => $monthsBack], true);
        return array_reverse($rows);
    }

    /** Latest month — dept vs individual components for one user. */
    public function personalBreakdown(int $userId, string $month): ?array
    {
        $row = $this->selectOne(
            'SELECT dept_score, individual_score
               FROM scores
              WHERE user_id=:u AND month=:m
              LIMIT 1',
            [':u' => $userId, ':m' => $month]
        );
        return $row ?: null;
    }

    /* ======================================================================
     *  ████  REPORT-BUILDER HELPERS  (used by reports.php / CSV / PDF)
     * ==================================================================== */

    /** Department-plan *score* (weight-adjusted %) for one month. */
    public function deptPlanScore(int $deptId, string $month): float
    {
        $sql = "
          SELECT COALESCE(
                   SUM(
                     CASE WHEN dim.target_value>0
                        THEN (dim.actual_value/dim.target_value)*dim.weight
                        ELSE 0 END
                   ),0)
          FROM   department_indicator_monthly dim
          WHERE  dim.dept_id      = :d
            AND  dim.month        = :m
            AND  dim.actual_value IS NOT NULL
        ";
        return (float)$this->selectScalar($sql, [':d' => $deptId, ':m' => $month]);
    }

    /**
     * Manager-style summary: each employee gets
     *   – individual avg   – manager avg  – final (70/30 mix of the two)
     */
    public function deptEvalSummary(int $deptId, string $month): array
    {
        $people = $this->deptMembers($deptId);
        $rows   = [];

        foreach ($people as $p) {
            $ind = $this->avgRatingByCat($p['user_id'], $month, 'individual');
            $mgr = $this->avgRatingByCat($p['user_id'], $month, 'manager');

            $rows[] = [
                'user_id' => $p['user_id'],
                'name'   => $p['name'],
                'ind_avg' => round($ind, 2),
                'mgr_avg' => round($mgr, 2),
                'final_score' => round(
                    $ind * $this->indWeight + $mgr * $this->deptWeight,
                    2
                )
            ];
        }
        return $rows;
    }

    /**
     * Department-style summary: everyone shares same dept-plan %
     * plus their own individual component → final 70/30 blend.
     */
    public function individualDeptSummary(int $deptId, string $month): array
    {
        $deptScore = $this->deptPlanScore($deptId, $month);
        $people    = $this->deptMembers($deptId);

        $rows = [];
        foreach ($people as $p) {
            $ind   = $this->avgRatingByCat($p['user_id'], $month, 'individual');
            $final = round($deptScore * $this->deptWeight + $ind * $this->indWeight, 2);
            $rows[] = [
                'user_id' => $p['user_id'],
                'name'   => $p['name'],
                'dept_score' => round($deptScore, 2),
                'individual_score' => round($ind, 2),
                'final_score' => $final
            ];
        }
        return $rows;
    }

    /**
     * Single-user composite needed for PDF export / quick cards.
     */
    public function userCompositeReport(int $userId, string $month): array
    {
        $u = $this->selectOne(
            'SELECT name, dept_id FROM users WHERE user_id=:id',
            [':id' => $userId]
        ) ?? throw new Exception('User missing');

        $deptScore = $this->deptPlanScore((int)$u['dept_id'], $month);
        $ind       = $this->avgRatingByCat($userId, $month, 'individual');
        $final     = round($deptScore * $this->deptWeight + $ind * $this->indWeight, 2);

        return [
            'user_id' => $userId,
            'name'   => $u['name'],
            'dept_id' => $u['dept_id'],
            'dept_score' => round($deptScore, 2),
            'individual_score' => round($ind, 2),
            'final_score' => $final
        ];
    }

    /** Indicator-by-indicator breakdown for PDF section. */
    public function individualIndicatorBreakdown(int $userId, string $month): array
    {
        $sql = "
          SELECT ind.name,
                 ind.default_goal,
                 ROUND(AVG(ev.rating),2) AS avg_rating
          FROM   individual_evaluations ev
          JOIN   individual_indicators ind
                 ON ev.indicator_id = ind.indicator_id
          WHERE  ev.evaluatee_id = :u
            AND  ev.month        = :m
            AND  ind.category    = 'individual'
          GROUP  BY ind.indicator_id
        ";
        return $this->select($sql, [':u' => $userId, ':m' => $month]);
    }

    /** Dept KPI table (target / actual / weight % & pct). */
    public function departmentPlanDetails(int $deptId, string $month): array
    {
        $sql = "
          SELECT COALESCE(di.name, dim.custom_name) AS name,
                 dim.target_value,
                 dim.actual_value,
                 dim.weight,
                 ROUND(
                   CASE WHEN dim.target_value>0
                        THEN (dim.actual_value/dim.target_value)*100
                        ELSE 0 END ,2) AS pct
          FROM   department_indicator_monthly dim
          LEFT   JOIN department_indicators di
                 ON dim.indicator_id = di.indicator_id
          WHERE  dim.dept_id      = :d
            AND  dim.month        = :m
            AND  dim.actual_value IS NOT NULL
        ";
        return $this->select($sql, [':d' => $deptId, ':m' => $month]);
    }

    /* ======================================================================
     *  ████  COUNT KPIs (small dashboard cards)
     * ==================================================================== */

    public function totalActiveUsers(): int
    {
        return (int)$this->selectScalar('SELECT COUNT(*) FROM users WHERE active=1');
    }

    public function totalDepartments(): int
    {
        return (int)$this->selectScalar('SELECT COUNT(*) FROM departments');
    }

    public function pendingPlanEntries(int $deptId, string $month): int
    {
        return (int)$this->selectScalar(
            'SELECT COUNT(*) FROM department_indicator_monthly
             WHERE dept_id=:d AND month=:m',
            [':d' => $deptId, ':m' => $month]
        );
    }

    public function pendingActuals(int $deptId, string $month): int
    {
        return (int)$this->selectScalar(
            'SELECT COUNT(*) FROM department_indicator_monthly
             WHERE dept_id=:d AND month=:m AND actual_value IS NULL',
            [':d' => $deptId, ':m' => $month]
        );
    }

    /** How many peers a user still needs to evaluate this month. */
    public function pendingEvaluations(int $evaluatorId, string $month): int
    {
        // total evaluatable peers
        $total = (int)$this->selectScalar(
            'SELECT COUNT(*) FROM users
             WHERE dept_id = (SELECT dept_id FROM users WHERE user_id=:uid_inner)
               AND user_id <> :uid_outer AND active=1',
            [':uid_inner' => $evaluatorId, ':uid_outer' => $evaluatorId]
        );
        

        // already done
        $done  = (int)$this->selectScalar(
            'SELECT COUNT(DISTINCT evaluatee_id)
               FROM individual_evaluations
              WHERE evaluator_id=:u AND month=:m',
            [':u' => $evaluatorId, ':m' => $month]
        );

        return max(0, $total - $done);
    }

    public function activeDeptCountForMonth(?string $month = null): int
    {
        $month ??= date('Y-m-01');
        return (int)$this->selectScalar(
            'SELECT COUNT(DISTINCT u.dept_id)
             FROM scores s
             JOIN users u ON s.user_id = u.user_id
             WHERE s.month = :m',
            [':m' => $month]
        );
        
    }

    /* ======================================================================
     *  ████  INTERNAL HELPERS
     * ==================================================================== */

    /** return [{user_id,name}, …] */
    private function deptMembers(int $deptId): array
    {
        return $this->select(
            'SELECT user_id,name FROM users
             WHERE dept_id=:d AND active=1 ORDER BY name',
            [':d' => $deptId]
        );
    }

    /** Average rating (1-5) for one category. */
    private function avgRatingByCat(int $userId, string $month, string $cat): float
    {
        $sql = "
          SELECT AVG(ev.rating)
          FROM   individual_evaluations ev
          JOIN   individual_indicators  ind
                 ON ev.indicator_id = ind.indicator_id
          WHERE  ev.evaluatee_id = :u
            AND  ev.month        = :m
            AND  ind.category    = :c
        ";
        return (float)$this->selectScalar(
            $sql,
            [':u' => $userId, ':m' => $month, ':c' => $cat]
        );
    }

    /* ---------- Generic DB helpers ----------------------------------- */

    private function select(string $sql, array $params = [], bool $allowIntLimit = false): array
    {
        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $k => $v) {
            $type = (\is_int($v) && ($allowIntLimit || $k !== ':lim')) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($k, $v, $type);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function selectOne(string $sql, array $params): ?array
    {
        $rows = $this->select($sql, $params);
        return $rows[0] ?? null;
    }

    private function selectScalar(string $sql, array $params = []): mixed
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function selectColumn(string $sql): array
    {
        return $this->pdo->query($sql)->fetchAll(PDO::FETCH_COLUMN);
    }

    /* ======================================================================
     *  ████  Back-compat wrapper aliases  (keep old method names alive)
     * ==================================================================== */
    public function getAvailableScoreMonths(): array
    {
        return $this->getScoreMonths();
    }
    public function getAvailableEvaluationMonths(): array
    {
        return $this->getEvaluationMonths();
    }
    public function getDepartmentPlanScore(int $d, string $m): float
    {
        return $this->deptPlanScore($d, $m);
    }
    public function getDepartmentEvaluationSummary(int $d, string $m): array
    {
        return $this->deptEvalSummary($d, $m);
    }
    public function getIndividualDeptSummary(int $d, string $m): array
    {
        return $this->individualDeptSummary($d, $m);
    }
    public function getUserIndividualDeptReport(int $u, string $m): array
    {
        return $this->userCompositeReport($u, $m);
    }
    public function getIndividualIndicatorsReport(int $u, string $m): array
    {
        return $this->individualIndicatorBreakdown($u, $m);
    }
    public function getDepartmentPlanDetails(int $d, string $m): array
    {
        return $this->departmentPlanDetails($d, $m);
    }


    public function getPersonalScoreHistory(int $userId, int $monthsBack = 6): array
    {
        $sql = '
            SELECT month, final_score
              FROM scores
             WHERE user_id = :uid
          ORDER BY month DESC
             LIMIT :lim';
        $rows = $this->select($sql, [':uid' => $userId, ':lim' => $monthsBack], true);

        return array_reverse($rows);   // put them oldest-first
    }

    /** employee’s latest dept / individual split for one month */
    public function getPersonalLatestBreakdown(int $userId, string $month): ?array
    {
        $sql = '
            SELECT dept_score, individual_score
              FROM scores
             WHERE user_id = :uid
               AND month    = :m
             LIMIT 1';
        return $this->selectOne($sql, [':uid' => $userId, ':m' => $month]);
    }



    
}
