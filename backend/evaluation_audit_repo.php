<?php
declare(strict_types=1);
namespace Backend;

use PDO;
use PDOException;

require_once __DIR__ . '/db.php';

final class EvaluationAuditRepository
{
    private PDO $pdo;
    
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public function availableMonths(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT DISTINCT DATE_FORMAT(month, '%Y-%m') as month 
                FROM individual_evaluations 
                ORDER BY month DESC
            ");
            return $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $e) {
            error_log("Error fetching available months: " . $e->getMessage());
            return [];
        }
    }

    public function allDepartments(): array
    {
        try {
            $stmt = $this->pdo->query("
                SELECT DISTINCT d.dept_id, d.dept_name 
                FROM departments d 
                INNER JOIN users u ON d.dept_id = u.dept_id 
                ORDER BY d.dept_name
            ");
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error fetching departments: " . $e->getMessage());
            return [];
        }
    }

    public function getAllDepartmentsByMonth(string $month): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT 
                    d.dept_id, 
                    d.dept_name,
                    CASE WHEN submitted_depts.dept_id IS NOT NULL THEN 1 ELSE 0 END as has_submissions
                FROM departments d 
                INNER JOIN users u ON d.dept_id = u.dept_id 
                LEFT JOIN (
                    SELECT DISTINCT u2.dept_id 
                    FROM users u2 
                    INNER JOIN individual_evaluations ie ON u2.user_id = ie.evaluator_id 
                    WHERE DATE_FORMAT(ie.month, '%Y-%m') = ?
                ) submitted_depts ON d.dept_id = submitted_depts.dept_id
                ORDER BY has_submissions DESC, d.dept_name
            ");
            $stmt->execute([$month]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error fetching departments by month: " . $e->getMessage());
            return [];
        }
    }

    public function getAllEmployeesForMonth(string $month): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT 
                    u.user_id, 
                    u.name,
                    d.dept_name,
                    CASE WHEN submitted_users.user_id IS NOT NULL THEN 1 ELSE 0 END as has_submitted
                FROM users u 
                INNER JOIN departments d ON u.dept_id = d.dept_id
                LEFT JOIN (
                    SELECT DISTINCT ie.evaluator_id as user_id
                    FROM individual_evaluations ie 
                    WHERE DATE_FORMAT(ie.month, '%Y-%m') = ?
                ) submitted_users ON u.user_id = submitted_users.user_id
                ORDER BY has_submitted DESC, d.dept_name, u.name
            ");
            $stmt->execute([$month]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error fetching all employees for month: " . $e->getMessage());
            return [];
        }
    }

    public function getEmployeesForDepartmentAndMonth(int $deptId, string $month): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT DISTINCT 
                    u.user_id, 
                    u.name,
                    CASE WHEN submitted_users.user_id IS NOT NULL THEN 1 ELSE 0 END as has_submitted
                FROM users u 
                LEFT JOIN (
                    SELECT DISTINCT ie.evaluator_id as user_id
                    FROM individual_evaluations ie 
                    WHERE DATE_FORMAT(ie.month, '%Y-%m') = ?
                ) submitted_users ON u.user_id = submitted_users.user_id
                WHERE u.dept_id = ?
                ORDER BY has_submitted DESC, u.name
            ");
            $stmt->execute([$month, $deptId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error fetching employees for department and month: " . $e->getMessage());
            return [];
        }
    }

    public function getEvaluatorAuditRows(?string $month, ?int $deptId, ?int $evaluatorId): array
    {
        try {
            $sql = "
                SELECT 
                    DATE_FORMAT(ev.month, '%Y-%m') AS month,
                    d.dept_name,
                    evtor.name AS evaluator,
                    evtee.name AS evaluatee,
                    ind.name AS indicator,
                    ev.rating,
                    ev.comments,
                    DATE(ev.created_at) AS date_submitted
                FROM individual_evaluations ev
                JOIN users evtor ON evtor.user_id = ev.evaluator_id
                JOIN users evtee ON evtee.user_id = ev.evaluatee_id
                JOIN departments d ON evtor.dept_id = d.dept_id
                JOIN individual_indicators ind ON ind.indicator_id = ev.indicator_id
                WHERE 1=1
            ";
            
            $params = [];
            
            if ($month) {
                $sql .= " AND DATE_FORMAT(ev.month, '%Y-%m') = ?";
                $params[] = $month;
            }
            
            if ($deptId) {
                $sql .= " AND evtor.dept_id = ?";
                $params[] = $deptId;
            }
            
            if ($evaluatorId) {
                $sql .= " AND ev.evaluator_id = ?";
                $params[] = $evaluatorId;
            }
            
            $sql .= " ORDER BY ev.month DESC, d.dept_name, evtor.name, evtee.name, ind.name";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error fetching evaluator audit rows: " . $e->getMessage());
            throw new \Exception("Unable to load evaluation data. Please check your filters and try again.");
        }
    }

    public function getMissingSubmissions(string $month, ?int $deptId, ?int $evaluatorId): array
    {
        try {
            $sql = "
                SELECT 
                    ? AS month,
                    d.dept_name,
                    u.name AS evaluator_name,
                    GROUP_CONCAT(DISTINCT expected_evaluatees.name ORDER BY expected_evaluatees.name SEPARATOR ', ') AS expected_evaluatees,
                    COUNT(DISTINCT expected_evaluatees.user_id) AS missing_count
                FROM users u
                JOIN departments d ON u.dept_id = d.dept_id
                CROSS JOIN users expected_evaluatees
                LEFT JOIN individual_evaluations ie ON (
                    ie.evaluator_id = u.user_id 
                    AND ie.evaluatee_id = expected_evaluatees.user_id 
                    AND DATE_FORMAT(ie.month, '%Y-%m') = ?
                )
                WHERE u.user_id != expected_evaluatees.user_id
                AND ie.evaluation_id IS NULL
            ";
            
            $params = [$month, $month];
            
            if ($deptId) {
                $sql .= " AND u.dept_id = ?";
                $params[] = $deptId;
            }
            
            if ($evaluatorId) {
                $sql .= " AND u.user_id = ?";
                $params[] = $evaluatorId;
            }
            
            $sql .= " GROUP BY u.user_id, d.dept_name, u.name
                     HAVING missing_count > 0
                     ORDER BY d.dept_name, u.name";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error fetching missing submissions: " . $e->getMessage());
            throw new \Exception("Unable to load missing submissions data. Please try again.");
        }
    }
}