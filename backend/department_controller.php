<?php

declare(strict_types=1);

namespace Backend;

use PDO;
use PDOException;
use Exception;

require_once __DIR__ . '/db.php';   // provides $pdo

final class DepartmentRepository
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
    // 1.  DEPARTMENT CRUD
    // ---------------------------------------------------------------------

    /** List departments with optional manager name */
    public function fetchAllDepartments(): array
    {
        $sql = "
            SELECT 
                d.dept_id,
                d.dept_name,
                d.manager_id,
                u.name         AS manager_name,
                d.share_path   AS share_path
            FROM departments d
            LEFT JOIN users u
              ON u.user_id = d.manager_id
            ORDER BY d.dept_name
        ";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    

    /** Fetch all users who can be managers (role_id = 2 for Manager) */
    public function fetchAllManagers(): array
    {
        $sql = "
            SELECT u.user_id,
                   u.name,
                   u.email,
                   d.dept_name
            FROM   users u
            LEFT   JOIN departments d ON u.dept_id = d.dept_id
            WHERE  u.role_id = 2
              AND  u.active = 1
            ORDER  BY u.name
        ";

        return $this->select($sql, []);
    }

    // public function createDepartment(string $name, ?int $managerId): int
    // {
    //     $this->runExec(
    //         'INSERT INTO departments (dept_name, manager_id) VALUES (:n, :m)',
    //         [':n' => $name, ':m' => $managerId]
    //     );

    //     return (int)$this->pdo->lastInsertId();
    // }

    public function createDepartment(string $name, ?int $managerId, ?string $path): int {
        $this->runExec(
            'INSERT INTO departments (dept_name, manager_id, share_path) VALUES (:n, :m, :p)',
            [':n' => $name, ':m' => $managerId, ':p' => $path]
        );
        return (int)$this->pdo->lastInsertId();
    }
    
    private function selectOne(string $sql, array $params): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row === false ? null : $row;
    }
    
    /** Single department row (or null). */
public function getDepartmentById(int $deptId): ?array
{
    return $this->selectOne(
        'SELECT dept_id, dept_name FROM departments WHERE dept_id = :d',
        [':d' => $deptId]
    );
}

/** Active users belonging to one department (id, name). */
public function fetchDepartmentMembers(int $deptId): array
{
    return $this->select(
        'SELECT user_id, name
         FROM   users
         WHERE  dept_id = :d AND active = 1
         ORDER  BY name',
        [':d' => $deptId]
    );
}


    // public function updateDepartment(int $id, string $name, ?int $managerId): bool
    // {
    //     return $this->runExec(
    //         'UPDATE departments SET dept_name = :n, manager_id = :m WHERE dept_id = :id',
    //         [':n' => $name, ':m' => $managerId, ':id' => $id]
    //     ) > 0;
    // }

    public function updateDepartment(int $id, string $name, ?int $managerId, ?string $path): bool {
        return $this->runExec(
            'UPDATE departments SET dept_name = :n, manager_id = :m, share_path = :p 
             WHERE dept_id = :id',
            [':n' => $name, ':m' => $managerId, ':p' => $path, ':id' => $id]
        ) > 0;
    }
    

    public function deleteDepartment(int $id): bool
    {
        return $this->runExec('DELETE FROM departments WHERE dept_id = :id', [':id' => $id]) > 0;
    }

    // ---------------------------------------------------------------------
    // 2.  MONTHLY SNAPSHOTS  (department_indicator_monthly)
    // ---------------------------------------------------------------------

    /**
     * Fetch snapshot rows for one department & month.
     *
     * @return array<array<string,mixed>>
     */
    public function fetchDepartmentSnapshots(int $deptId, string $month): array
    {
        $this->assertMonth($month);

        $sql = "
            SELECT dim.*,
                   di.name        AS indicator_name,
                   di.description AS indicator_description,
                   di.unit        AS indicator_unit
            FROM   department_indicator_monthly dim
            LEFT   JOIN department_indicators di
                 ON dim.indicator_id = di.indicator_id
            WHERE  dim.dept_id = :d
              AND  dim.month   = :m
            ORDER  BY dim.snapshot_id
        ";

        return $this->select($sql, [':d' => $deptId, ':m' => $month]);
    }

    /**
     * Add a snapshot entry (master indicator OR custom).
     *
     * Keys in $data (all required unless noted):
     *   indicator_id  (nullable when custom)
     *   custom_name   (nullable when master)
     *   is_custom     (bool)
     *   target_value
     *   weight
     *   unit_of_goal  (nullable)
     *   unit          (nullable)
     *   way_of_measurement (nullable)
     *   created_by
     */
    public function addSnapshot(
        int    $deptId,
        string $month,
        array  $data
    ): int {
        $this->assertMonth($month);

        $sql = "
            INSERT INTO department_indicator_monthly
                (dept_id, month, indicator_id, custom_name, is_custom,
                 target_value, weight,
                 unit_of_goal, unit, way_of_measurement,
                 created_by, created_at)
            VALUES
                (:dept, :month, :ind, :cname, :isc,
                 :tgt, :w,
                 :uog, :unit, :wom,
                 :cb,   NOW())
        ";

        $this->runExec($sql, [
            ':dept'  => $deptId,
            ':month' => $month,
            ':ind'   => $data['indicator_id']      ?? null,
            ':cname' => $data['custom_name']       ?? null,
            ':isc'   => $data['is_custom'] ? 1 : 0,
            ':tgt'   => $data['target_value'],
            ':w'     => $data['weight'],
            ':uog'   => $data['unit_of_goal']      ?? null,
            ':unit'  => $data['unit']              ?? null,
            ':wom'   => $data['way_of_measurement']?? null,
            ':cb'    => $data['created_by'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /** Remove a snapshot row */
    public function removeSnapshot(int $snapshotId): bool
    {
        return $this->runExec(
            'DELETE FROM department_indicator_monthly WHERE snapshot_id = :id',
            [':id' => $snapshotId]
        ) > 0;
    }

    /** Update target & weight of an existing snapshot */
    public function updateSnapshot(
        int   $snapshotId,
        float $target,
        int   $weight
    ): bool {
        return $this->runExec(
            'UPDATE department_indicator_monthly
             SET target_value = :t, weight = :w
             WHERE snapshot_id = :id',
            [':t' => $target, ':w' => $weight, ':id' => $snapshotId]
        ) > 0;
    }

    /**
     * Manager submits actual values + notes for all snapshots of the month.
     *
     * @param array<int,float> $actuals  [ snapshot_id => value ]
     * @param array<int,string> $notes   [ snapshot_id => text ]
     */
    public function submitActuals(
        int    $deptId,
        string $month,
        array  $actuals,
        array  $notes = [],
        array  $paths = []    // Added parameter for file paths
    ): bool {
        $this->assertMonth($month);
        $upd = $this->pdo->prepare(
            "UPDATE department_indicator_monthly
             SET actual_value   = :val,
                 notes          = :note,
                 task_file_path = :path
             WHERE snapshot_id  = :sid
               AND dept_id      = :d
               AND month        = :m"
        );
        $this->pdo->beginTransaction();
        try {
            foreach ($actuals as $sid => $value) {
                $upd->execute([
                    ':val'  => $value,
                    ':note' => $notes[$sid] ?? '',
                    ':path' => $paths[$sid] ?? '',   // bind the file path (or empty string if not provided)
                    ':sid'  => $sid,
                    ':d'    => $deptId,
                    ':m'    => $month,
                ]);
            }
            $this->pdo->commit();

            $evalRepo = new \Backend\EvaluationRepository($this->pdo);

            $stmt = $this->pdo->prepare('SELECT user_id FROM users WHERE dept_id = ? AND active = 1');
            $stmt->execute([$deptId]);
            $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
            foreach ($userIds as $userId) {
                $evalRepo->updateScoresAfterEvaluation((int)$userId, $month);
            }

            return true;
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    // ---------------------------------------------------------------------
    // PRIVATE UTILS
    // ---------------------------------------------------------------------

    private function assertMonth(string $month): void
    {
        if (!preg_match('/^\d{4}-\d{2}-01$/', $month)) {
            throw new Exception('Invalid month format; expected YYYY-MM-01');
        }
    }

    private function select(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function runExec(string $sql, array $params): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }


    
}

