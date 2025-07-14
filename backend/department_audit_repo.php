<?php
declare(strict_types=1);
namespace Backend;

use PDO;
use PDOException;

require_once __DIR__.'/db.php';

final class DepartmentAuditRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public function availableYears(): array
    {
        $stmt = $this->pdo->query(
            'SELECT DISTINCT YEAR(month) AS yr FROM department_indicator_monthly ORDER BY yr DESC'
        );
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function availableMonthsForYear(int $year): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT DISTINCT DATE_FORMAT(month,'%Y-%m') AS m
             FROM department_indicator_monthly
             WHERE YEAR(month)=?
             ORDER BY m DESC"
        );
        $stmt->execute([$year]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function allDepartments(): array
    {
        $stmt = $this->pdo->query(
            'SELECT DISTINCT d.dept_id,d.dept_name
             FROM departments d
             INNER JOIN department_indicator_monthly dim ON d.dept_id=dim.dept_id
             ORDER BY d.dept_name'
        );
        return $stmt->fetchAll();
    }

    public function getDepartmentsByMonth(string $month): array
    {
        return $this->getDepartmentsFilter('DATE_FORMAT(dim.month,\'%Y-%m\')=?', [$month]);
    }

    public function getDepartmentsByYear(int $year): array
    {
        return $this->getDepartmentsFilter('YEAR(dim.month)=?', [$year]);
    }

    private function getDepartmentsFilter(string $where, array $params): array
    {
        $sql = "
            SELECT d.dept_id,
                   d.dept_name,
                   COUNT(dim.snapshot_id) AS indicator_count,
                   SUM(dim.actual_value IS NOT NULL) AS completed_count
            FROM departments d
            LEFT JOIN department_indicator_monthly dim
                   ON d.dept_id=dim.dept_id AND $where
            GROUP BY d.dept_id,d.dept_name
            HAVING indicator_count>0
            ORDER BY d.dept_name";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getDepartmentEvaluations(
        ?string $month,
        ?int $deptId,
        ?int $year
    ): array {
        $sql = $this->baseEvalSelect();
        $params = [];

        if ($month) {
            $sql .= " AND DATE_FORMAT(dim.month,'%Y-%m')=?";
            $params[] = $month;
        } elseif ($year) {
            $sql .= ' AND YEAR(dim.month)=?';
            $params[] = $year;
        }

        if ($deptId) {
            $sql .= ' AND dim.dept_id=?';
            $params[] = $deptId;
        }

        $sql .= ' ORDER BY dim.month DESC,d.dept_name,dim.weight DESC,indicator_name';
        return $this->run($sql, $params);
    }

    public function getDepartmentSummary(
        ?string $month,
        ?int $deptId,
        ?int $year
    ): array {
        $sql = $this->baseSummarySelect();
        $params = [];

        if ($month) {
            $sql .= " AND DATE_FORMAT(dim.month,'%Y-%m')=?";
            $params[] = $month;
        } elseif ($year) {
            $sql .= ' AND YEAR(dim.month)=?';
            $params[] = $year;
        }

        if ($deptId) {
            $sql .= ' AND dim.dept_id=?';
            $params[] = $deptId;
        }

        $sql .= '
            GROUP BY DATE_FORMAT(dim.month,\'%Y-%m\'),d.dept_id,d.dept_name
            ORDER BY dim.month DESC,d.dept_name';
        return $this->run($sql, $params);
    }

    public function getIncompleteDepartmentPlans(
        ?string $month,
        ?int $deptId,
        ?int $year
    ): array {
        $sql = $this->baseIncompleteSelect();
        $params = [];

        if ($month) {
            $sql .= " AND DATE_FORMAT(dim.month,'%Y-%m')=?";
            $params[] = $month;
        } elseif ($year) {
            $sql .= ' AND YEAR(dim.month)=?';
            $params[] = $year;
        }

        if ($deptId) {
            $sql .= ' AND dim.dept_id=?';
            $params[] = $deptId;
        }

        $sql .= '
            GROUP BY DATE_FORMAT(dim.month,\'%Y-%m\'),d.dept_id,d.dept_name
            HAVING incomplete_indicators>0
            ORDER BY incomplete_indicators DESC,d.dept_name';
        return $this->run($sql, $params);
    }

    private function baseEvalSelect(): string
    {
        return "
            SELECT DATE_FORMAT(dim.month,'%Y-%m') AS month,
                   d.dept_name,
                   COALESCE(dim.custom_name,di.name) AS indicator_name,
                   di.description AS indicator_description,
                   dim.target_value,
                   dim.actual_value,
                   dim.weight,
                   dim.unit_of_goal,
                   dim.unit,
                   dim.way_of_measurement,
                   dim.notes,
                   u.name AS created_by_name,
                   DATE(dim.created_at) AS created_date,
                   dim.is_custom,
                   CASE
                       WHEN dim.actual_value IS NULL THEN 'Pending'
                       WHEN dim.actual_value>=dim.target_value THEN 'Achieved'
                       WHEN dim.actual_value>=dim.target_value*0.8 THEN 'Partially Achieved'
                       ELSE 'Not Achieved'
                   END AS achievement_status,
                   CASE
                       WHEN dim.actual_value IS NULL THEN 0
                       ELSE ROUND(dim.actual_value/dim.target_value*100,2)
                   END AS achievement_percentage
            FROM department_indicator_monthly dim
            JOIN departments d ON d.dept_id=dim.dept_id
            LEFT JOIN department_indicators di ON di.indicator_id=dim.indicator_id
            LEFT JOIN users u ON u.user_id=dim.created_by
            WHERE 1=1";
    }

    private function baseSummarySelect(): string
    {
        return "
            SELECT DATE_FORMAT(dim.month,'%Y-%m') AS month,
                   d.dept_name,
                   COUNT(dim.snapshot_id) AS total_indicators,
                   SUM(dim.actual_value IS NOT NULL) AS completed_indicators,
                   SUM(dim.actual_value>=dim.target_value) AS achieved_indicators,
                   SUM(dim.actual_value>=dim.target_value*0.8 
                       AND dim.actual_value<dim.target_value) AS partially_achieved_indicators,
                   SUM(dim.actual_value<dim.target_value*0.8) AS not_achieved_indicators,
                   SUM(dim.actual_value IS NULL) AS pending_indicators,
                   ROUND(AVG(CASE WHEN dim.actual_value IS NOT NULL
                                  THEN dim.actual_value/dim.target_value*100 END),2) AS avg_achievement_percentage,
                   SUM(dim.weight) AS total_weight,
                   ROUND(SUM(CASE WHEN dim.actual_value IS NOT NULL
                                  THEN dim.actual_value/dim.target_value*dim.weight END)/
                         SUM(dim.weight)*100,2) AS weighted_achievement_percentage
            FROM department_indicator_monthly dim
            JOIN departments d ON d.dept_id=dim.dept_id
            WHERE 1=1";
    }

    private function baseIncompleteSelect(): string
    {
        return "
            SELECT DATE_FORMAT(dim.month,'%Y-%m') AS month,
                   d.dept_name,
                   COUNT(dim.snapshot_id) AS total_indicators,
                   SUM(dim.actual_value IS NULL) AS incomplete_indicators,
                   GROUP_CONCAT(
                       CASE WHEN dim.actual_value IS NULL
                            THEN COALESCE(dim.custom_name,di.name) END
                       ORDER BY dim.weight DESC SEPARATOR ', ') AS incomplete_indicator_names
            FROM department_indicator_monthly dim
            JOIN departments d ON d.dept_id=dim.dept_id
            LEFT JOIN department_indicators di ON di.indicator_id=dim.indicator_id
            WHERE 1=1";
    }

    private function run(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
