<?php
/**
 * Indicator repository – handles CRUD for both individual-level and department-level
 * performance indicators, reflecting the post-migration database structure.
 *
 * Tables
 *   • individual_indicators
 *   • department_indicators
 *
 * @author   SK-PM
 * @version  1.0 – 2025-05-26
 */

declare(strict_types=1);

namespace Backend;

use PDO;
use PDOException;
use Exception;

require_once __DIR__ . '/db.php';   // provides $pdo (PDO, ERRMODE_EXCEPTION)

/**
 * Class IndicatorRepository
 */
final class IndicatorRepository
{
    /** @var PDO */
    private PDO $pdo;

    // ---------------------------------------------------------------------
    // construction
    // ---------------------------------------------------------------------
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // ---------------------------------------------------------------------
    // INDIVIDUAL INDICATORS (employees & managers)
    // ---------------------------------------------------------------------

    /**
     * Fetch individual indicators, sorted first by `category`
     * (individual → manager) then by `sort_order`.
     *
     * @param bool              $onlyActive          filter by active flag
     * @param null|'individual'|'manager' $category  optional category filter
     * @return array
     */
    public function fetchIndividualIndicators(
        bool $onlyActive = false,
        ?string $category = null
    ): array {
        $sql = "
            SELECT *
            FROM   individual_indicators
        ";
        $clauses = [];
        $params  = [];

        if ($onlyActive) {
            $clauses[] = 'active = 1';
        }
        if ($category !== null) {
            $clauses[] = 'category = ?';
            $params[]  = $category;
        }
        if ($clauses) {
            $sql .= ' WHERE ' . implode(' AND ', $clauses);
        }

        $sql .= "
            ORDER BY FIELD(category,'individual','manager'),
                     sort_order ASC
        ";

        return $this->runSelect($sql, $params);
    }

    /**
     * Create a new individual indicator.
     *
     * Required keys in $data:
     *   name, description, category ('individual'|'manager'),
     *   default_goal, default_weight (nullable), sort_order,
     *   responsible_departments (nullable)
     *
     * @throws Exception on validation fail / PDO failure
     */
    public function createIndividualIndicator(array $data): int
    {
        $this->validateRequired(
            $data,
            ['name','category','default_goal','sort_order']
        );
        if (!\in_array($data['category'], ['individual', 'manager'], true)) {
            throw new Exception('Invalid category for individual indicator.');
        }

        $sql = "
            INSERT INTO individual_indicators
            (name, description, category, responsible_departments,
             default_goal, default_weight, sort_order, active)
            VALUES
            (:name, :description, :category, :resp_depts,
             :default_goal, :default_weight, :sort_order, 1)
        ";

        $this->runExec($sql, [
            ':name'          => $data['name'],
            ':description'   => $data['description']    ?? null,
            ':category'      => $data['category'],
            ':resp_depts'    => $data['responsible_departments'] ?? null,
            ':default_goal'  => $data['default_goal'],
            ':default_weight'=> $data['default_weight'] ?? null,
            ':sort_order'    => $data['sort_order'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update an existing individual indicator.
     *
     * @param int   $id    indicator_id
     * @param array $data  same keys as create
     */
    public function updateIndividualIndicator(int $id, array $data): bool
    {
        $this->validateRequired($data, ['name', 'category']);

        $sql = "
            UPDATE individual_indicators
            SET    name = :name,
                   description = :description,
                   category = :category,
                   responsible_departments = :resp_depts,
                   default_goal = :default_goal,
                   default_weight = :default_weight,
                   sort_order = :sort_order,
                   active = :active
            WHERE  indicator_id = :id
        ";

        return $this->runExec($sql, [
            ':name'          => $data['name'],
            ':description'   => $data['description']    ?? null,
            ':category'      => $data['category'],
            ':resp_depts'    => $data['responsible_departments'] ?? null,
            ':default_goal'  => $data['default_goal']   ?? null,
            ':default_weight'=> $data['default_weight'] ?? null,
            ':sort_order'    => $data['sort_order']     ?? 0,
            ':active'        => $data['active']         ?? 1,
            ':id'            => $id,
        ]) > 0;
    }

    // ---------------------------------------------------------------------
    // DEPARTMENT INDICATORS
    // ---------------------------------------------------------------------

    /**
     * Fetch department-level indicators, ordered by sort_order.
     *
     * @param bool $onlyActive
     * @return array
     */
    public function fetchDepartmentIndicators(bool $onlyActive = false): array
    {
        $sql    = "SELECT * FROM department_indicators";
        $params = [];
        if ($onlyActive) {
            $sql .= " WHERE active = 1";
        }
        $sql .= " ORDER BY sort_order ASC";

        return $this->runSelect($sql, $params);
    }

    /**
     * Create a new department indicator.
     *
     * Required keys:
     *   name, default_goal, sort_order
     * Optional:
     *   description, responsible_departments, unit_of_goal, unit,
     *   way_of_measurement, default_weight
     *
     * @return int new primary key
     * @throws Exception
     */
    public function createDepartmentIndicator(array $data): int
    {
        $this->validateRequired($data, ['name', 'default_goal', 'sort_order']);

        $sql = "
            INSERT INTO department_indicators
            (name, description, responsible_departments,
             default_goal, unit_of_goal, unit, way_of_measurement,
             default_weight, sort_order, active)
            VALUES
            (:name, :description, :resp_depts,
             :default_goal, :unit_of_goal, :unit, :way_of_measurement,
             :default_weight, :sort_order, 1)
        ";

        $this->runExec($sql, [
            ':name'              => $data['name'],
            ':description'       => $data['description']          ?? null,
            ':resp_depts'        => $data['responsible_departments'] ?? null,
            ':default_goal'      => $data['default_goal'],
            ':unit_of_goal'      => $data['unit_of_goal']         ?? null,
            ':unit'              => $data['unit']                 ?? null,
            ':way_of_measurement'=> $data['way_of_measurement']   ?? null,
            ':default_weight'    => $data['default_weight']       ?? null,
            ':sort_order'        => $data['sort_order'],
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * Update a department indicator.
     */
    public function updateDepartmentIndicator(int $id, array $data): bool
    {
        $sql = "
            UPDATE department_indicators
            SET    name = :name,
                   description = :description,
                   responsible_departments = :resp_depts,
                   default_goal = :default_goal,
                   unit_of_goal = :unit_of_goal,
                   unit = :unit,
                   way_of_measurement = :way_of_measurement,
                   default_weight = :default_weight,
                   sort_order = :sort_order,
                   active = :active
            WHERE  indicator_id = :id
        ";

        return $this->runExec($sql, [
            ':name'               => $data['name'],
            ':description'        => $data['description']          ?? null,
            ':resp_depts'         => $data['responsible_departments'] ?? null,
            ':default_goal'       => $data['default_goal']         ?? null,
            ':unit_of_goal'       => $data['unit_of_goal']         ?? null,
            ':unit'               => $data['unit']                 ?? null,
            ':way_of_measurement' => $data['way_of_measurement']   ?? null,
            ':default_weight'     => $data['default_weight']       ?? null,
            ':sort_order'         => $data['sort_order']           ?? 0,
            ':active'             => $data['active']               ?? 1,
            ':id'                 => $id,
        ]) > 0;
    }

    // ---------------------------------------------------------------------
    // SHARED HELPERS
    // ---------------------------------------------------------------------

    /**
     * Soft-delete (archive) an indicator in the chosen scope.
     *
     * @param 'individual'|'department' $scope
     */
    public function archiveIndicator(int $id, string $scope): bool
    {
        $table = $scope === 'individual'
            ? 'individual_indicators'
            : 'department_indicators';

        $sql = "
            UPDATE {$table}
            SET    active = 0,
                   archived_at = NOW()
            WHERE  indicator_id = :id
        ";

        return $this->runExec($sql, [':id' => $id]) > 0;
    }

    // ---------------------------------------------------------------------
    // low-level wrappers
    // ---------------------------------------------------------------------

    /** @param string $sql  @param array $params */
    private function runSelect(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return int number of affected rows */
    private function runExec(string $sql, array $params): int
    {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->rowCount();
        } catch (PDOException $e) {
            // In production you might log $e->getMessage()
            throw $e;
        }
    }

    /** quick required-field validator */
    private function validateRequired(array $data, array $required): void
    {
        $missing = array_filter($required, static fn($k) => !isset($data[$k]) || $data[$k] === '');
        if ($missing) {
            throw new Exception('Missing required fields: ' . implode(', ', $missing));
        }
    }
}

// -------------------------------------------------------------------------
// USAGE EXAMPLE (controllers / pages call these static helpers)
// -------------------------------------------------------------------------
$indicatorRepo = new IndicatorRepository($pdo);

/*
// list active employee & manager indicators
$indicators = $indicatorRepo->fetchIndividualIndicators(true);

// create a department KPI
$newId = $indicatorRepo->createDepartmentIndicator([
    'name'              => 'Website Traffic Growth',
    'default_goal'      => 10.00,
    'sort_order'        => 5,
    'unit_of_goal'      => '%',
    'unit'              => 'visitors',
    'way_of_measurement'=> 'Google Analytics monthly sessions',
]);
*/
