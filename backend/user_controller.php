<?php
require_once __DIR__ . '/db.php';

function fetchAllRoles(): array {
    global $pdo;
    $stmt = $pdo->query('
      SELECT MIN(role_id) AS role_id, role_name
      FROM roles
      GROUP BY role_name
      ORDER BY role_name
    ');
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}



// function createUser($name, $email, $password, $role_id, $dept_id) {
//     global $pdo;
//     $hash = password_hash($password, PASSWORD_BCRYPT);
//     $stmt = $pdo->prepare(
//         'INSERT INTO users (name, email, password_hash, role_id, dept_id)
//          VALUES (?, ?, ?, ?, ?)'
//     );
//     return $stmt->execute([$name, $email, $hash, $role_id, $dept_id]);
// }

function getUser(int $id): ?array
{
    global $pdo;

    $sql = "
    SELECT
        u.user_id,
        u.name,
        u.email,
        u.phone,
        u.position,
        u.birth_date,
        u.hire_date,
        u.rating_window_days,
        /* make sure the chat ID comes through */
        COALESCE(u.telegram_chat_id, ut.telegram_chat_id) AS telegram_chat_id,
        u.role_id,
        r.role_name,
        u.dept_id,
        d.dept_name,
        u.active
    FROM users u
    JOIN roles r             ON r.role_id  = u.role_id
    LEFT JOIN departments d  ON d.dept_id  = u.dept_id
    /* grab chat ID from user_telegram too, in case that table has the latest value */
    LEFT JOIN user_telegram ut ON ut.user_id = u.user_id AND ut.verified = 1
    WHERE u.user_id = :id
    LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user ?: null;
}


// function updateUser($user_id, $name, $email, $role_id, $dept_id, $active) {
//     global $pdo;
//     $stmt = $pdo->prepare(
//         'UPDATE users
//          SET name = ?, email = ?, role_id = ?, dept_id = ?, active = ?
//          WHERE user_id = ?'
//     );
//     return $stmt->execute([$name, $email, $role_id, $dept_id, $active, $user_id]);
// }

function changeUserPassword(int $user_id, string $newPassword): bool {
    global $pdo;
    $hash = password_hash($newPassword, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare(
        'UPDATE users
         SET password_hash = ?
         WHERE user_id = ?'
    );
    return $stmt->execute([$hash, $user_id]);
}

function deleteUser($user_id) {
    global $pdo;
    $stmt = $pdo->prepare('UPDATE users SET active = 0 WHERE user_id = ?');
    return $stmt->execute([$user_id]);
}



function fetchAllUsers() {
    global $pdo;
    $stmt = $pdo->query(
        'SELECT 
            u.user_id,
            u.name,
            u.email,
            u.phone,
            u.position,
            u.birth_date,
            u.hire_date,
            u.rating_window_days,      
            u.telegram_chat_id,
            u.role_id,
            r.role_name,
            u.dept_id,
            d.dept_name,
            u.active
         FROM users u
         JOIN roles r   ON u.role_id = r.role_id
         LEFT JOIN departments d ON u.dept_id = d.dept_id
         ORDER BY u.name'
    );
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


function createUser($name, $email, $password, $role_id, $dept_id, $phone, $position, $birth_date, $hire_date) {
    global $pdo;
    $hash = password_hash($password, PASSWORD_BCRYPT);
    $stmt = $pdo->prepare(
      'INSERT INTO users
         (name, email, password_hash, role_id, dept_id, phone, position, birth_date, hire_date)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    return $stmt->execute([$name,$email,$hash,$role_id,$dept_id,$phone,$position,$birth_date,$hire_date]);
}

function updateUser($user_id, $name, $email, $role_id, $dept_id, $active, $phone, $position, $birth_date, $hire_date) {
    global $pdo;
    $stmt = $pdo->prepare(
      'UPDATE users
         SET name=?, email=?, role_id=?, dept_id=?, active=?, phone=?, position=?, birth_date=?, hire_date=?
       WHERE user_id=?'
    );
    return $stmt->execute([$name,$email,$role_id,$dept_id,$active,$phone,$position,$birth_date,$hire_date,$user_id]);
}











/**
 * Fetch all departments for the <select> in the user form.
 */
function fetchAllDepartments() {
    global $pdo;
    $stmt = $pdo->query(
        "SELECT dept_id, dept_name
         FROM departments
         ORDER BY dept_name"
    );
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


